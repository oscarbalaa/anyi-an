<?php

namespace App\Servicios\Personas;

use App\Exceptions\ErrorRegla;
use App\Support\Auditoria;
use App\Support\Excel;
use App\Support\PerfilUsuario;
use App\Support\Reglas;
use App\Support\ReglasPersona;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Options as OpcionesEscritura;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

/**
 * CU-11: importar estudiantes desde Excel (RF-30). Valida cada fila contra docs/validaciones.md y la BD,
 * y registra solo las válidas en una sola transacción. No guarda el archivo: en cada paso se vuelve a enviar.
 */
class ImportacionEstudiantes
{
    public const COLUMNAS = [
        'tipo_documento', 'numero_documento', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo',
        'nivel', 'grado', 'seccion', 'correo', 'celular', 'direccion', 'codigo',
    ];

    public const MAX_FILAS = 1000;

    private const EJEMPLO = ['DNI', '78123456', 'Diego Alonso', 'Ramírez Torres', '14/05/2012', 'M', 'SECUNDARIA', '3', 'B', 'dramirez@mail.com', '987654321', 'Av. Pacífico 123', ''];

    public function __construct(private readonly Estudiantes $estudiantes) {}

    /** Plantilla con los encabezados y una fila de ejemplo (columnas de texto, para no perder ceros) */
    public function plantilla(): string
    {
        return $this->escribir([self::COLUMNAS, self::EJEMPLO]);
    }

    /** @return array{filas:int, validas:list<array>, errores:list<array{fila:int, mensajes:list<string>, valores:array}>} */
    public function revisar(string $ruta, bool $matricular): array
    {
        $filas = $this->leer($ruta);
        $periodo = DB::table('periodo_academico')->where('estado', 'EN_CURSO')->first(['id', 'nombre']);
        if ($matricular && ! $periodo) {
            throw new ErrorRegla('No hay un periodo en curso para matricular.', 'matricular');
        }
        $aulas = $periodo ? $this->aulasDelPeriodo($periodo->id) : collect();
        $vistos = ['documento' => [], 'codigo' => [], 'correo' => []];
        $cupos = [];
        $validas = [];
        $errores = [];

        foreach ($filas as $numero => $valores) {
            [$datos, $mensajes] = $this->validarFila($valores, $aulas);

            // Repetidos dentro del mismo archivo
            foreach (['documento' => $datos['tipo_documento'].$datos['numero_documento'], 'codigo' => $datos['codigo'] ?? null, 'correo' => $datos['correo'] ?? null] as $tipo => $clave) {
                if (! $clave) {
                    continue;
                }
                if (isset($vistos[$tipo][$clave])) {
                    $mensajes[] = ['documento' => 'El documento', 'codigo' => 'El código', 'correo' => 'El correo'][$tipo]." se repite en la fila {$vistos[$tipo][$clave]}.";
                } else {
                    $vistos[$tipo][$clave] = $numero;
                }
            }

            // Cupo del aula (RN-04), solo si se va a matricular
            if (! $mensajes && $matricular && $aula = $datos['aula'] ?? null) {
                $cupos[$aula->id] ??= $aula->matriculados;
                if ($cupos[$aula->id] >= $aula->capacidad) {
                    $mensajes[] = "El aula {$aula->nombre} se llena ({$aula->capacidad}/{$aula->capacidad}): no entra esta fila.";
                } else {
                    $cupos[$aula->id]++;
                }
            }

            if ($mensajes) {
                $errores[] = ['fila' => $numero, 'mensajes' => $mensajes, 'valores' => $valores];
            } else {
                $validas[] = $datos;
            }
        }

        return ['filas' => count($filas), 'validas' => $validas, 'errores' => $errores, 'periodo' => $periodo?->nombre];
    }

    /** Registra solo las filas válidas; devuelve las credenciales para entregarlas */
    public function importar(string $ruta, bool $matricular, bool $crearUsuario, int $adminId): array
    {
        $revision = $this->revisar($ruta, $matricular);
        if (! $revision['validas']) {
            throw new ErrorRegla('El archivo no tiene filas válidas para importar.', 'archivo');
        }

        $credenciales = DB::transaction(function () use ($revision, $matricular, $crearUsuario, $adminId) {
            $lista = [];
            foreach ($revision['validas'] as $d) {
                $r = $this->estudiantes->registrar(
                    $d + ['aula_id' => $matricular ? $d['aula']->id : null],
                    $adminId,
                    'EXCEL',
                    $crearUsuario,
                );
                $lista[] = ['codigo' => $r['codigo'], 'nombre' => "{$d['apellidos']}, {$d['nombres']}", 'contrasena' => $crearUsuario ? $r['contrasena'] : null];
            }
            Auditoria::registrar('IMPORTAR', 'estudiante', null, ['filas' => $revision['filas'], 'importados' => count($lista), 'matricular' => $matricular]);

            return $lista;
        });

        return [
            'importados' => count($credenciales),
            'con_error' => count($revision['errores']),
            'credenciales' => $crearUsuario ? $credenciales : [],
        ];
    }

    /** Las filas con error, con una última columna que explica cada error */
    public function archivoDeErrores(string $ruta, bool $matricular): string
    {
        $revision = $this->revisar($ruta, $matricular);
        $filas = [array_merge(self::COLUMNAS, ['errores'])];
        foreach ($revision['errores'] as $e) {
            $filas[] = array_merge(array_values($e['valores']), [implode(' ', $e['mensajes'])]);
        }

        return $this->escribir($filas);
    }

    /** @return array<int, array<string,string>> número de fila de Excel => valores por columna */
    private function leer(string $ruta): array
    {
        $lector = new Reader;
        try {
            $lector->open($ruta);
            $filas = [];
            $encabezados = null;
            foreach ($lector->getSheetIterator() as $hoja) {
                foreach ($hoja->getRowIterator() as $i => $fila) {
                    $celdas = array_map(fn ($c) => $this->texto($c), $fila->toArray());
                    if ($encabezados === null) {
                        $encabezados = array_map(fn ($c) => Str::of($c)->lower()->ascii()->trim()->replace(' ', '_')->toString(), $celdas);
                        $faltan = array_diff(self::COLUMNAS, $encabezados);
                        if ($faltan) {
                            throw new ErrorRegla('Al archivo le faltan columnas de la plantilla: '.implode(', ', $faltan).'.', 'archivo');
                        }

                        continue;
                    }
                    if (implode('', $celdas) === '') {
                        continue;
                    }
                    $valores = [];
                    foreach (self::COLUMNAS as $columna) {
                        $valores[$columna] = $celdas[array_search($columna, $encabezados, true)] ?? '';
                    }
                    $filas[$i] = $valores;
                    if (count($filas) > self::MAX_FILAS) {
                        throw new ErrorRegla('El archivo tiene más de '.self::MAX_FILAS.' filas. Divídelo en partes.', 'archivo');
                    }
                }
                break;
            }
        } catch (ErrorRegla $e) {
            throw $e;
        } catch (Throwable) {
            throw new ErrorRegla('El archivo no es un Excel (.xlsx) válido.', 'archivo');
        } finally {
            $lector->close();
        }
        if ($encabezados === null) {
            throw new ErrorRegla('El archivo está vacío.', 'archivo');
        }
        if (! $filas) {
            throw new ErrorRegla('El archivo no tiene filas de estudiantes.', 'archivo');
        }

        return $filas;
    }

    /** Valida una fila con las mismas reglas del formulario; devuelve [datos limpios, mensajes] */
    private function validarFila(array $v, $aulas): array
    {
        $mensajes = [];
        $tipo = mb_strtoupper(trim($v['tipo_documento'])) ?: 'DNI';
        $numero = mb_strtoupper(str_replace(' ', '', $v['numero_documento']));
        // Excel guarda el DNI como número y pierde los ceros de la izquierda (uno o dos, no más)
        if ($tipo === 'DNI' && ctype_digit($numero) && in_array(strlen($numero), [6, 7], true)) {
            $numero = str_pad($numero, 8, '0', STR_PAD_LEFT);
        }
        $fecha = $this->fecha($v['fecha_nacimiento']);
        if ($fecha === false) {
            $mensajes[] = "La fecha «{$v['fecha_nacimiento']}» no es válida (usa DD/MM/AAAA).";
        }

        $datos = [
            'tipo_documento' => $tipo,
            'numero_documento' => $numero,
            'nombres' => Reglas::nombrePropio($v['nombres']),
            'apellidos' => Reglas::nombrePropio($v['apellidos']),
            'fecha_nacimiento' => $fecha ?: null,
            'sexo' => mb_strtoupper(trim($v['sexo'])),
            'correo' => mb_strtolower(trim($v['correo'])) ?: null,
            'celular' => str_replace(' ', '', $v['celular']) ?: null,
            'direccion' => Reglas::limpiar($v['direccion']) ?: null,
            'codigo' => mb_strtoupper(trim($v['codigo'])) ?: null,
        ];

        $reglas = ReglasPersona::reglas($tipo, null, true) + [
            'correo' => ['nullable', 'max:120', 'regex:'.Reglas::CORREO, 'unique:usuario,email'],
            'celular' => ['nullable', 'regex:'.Reglas::CELULAR],
            'codigo' => ['nullable', 'regex:/^E-\d{4}-\d{3}$/D', 'unique:usuario,username'],
        ];
        $validador = Validator::make(array_filter($datos, fn ($x) => $x !== null) + ['fecha_nacimiento' => $fecha ?: null], $reglas, ReglasPersona::mensajes($tipo) + [
            'correo.unique' => 'Ese correo ya lo usa otra cuenta.',
            'correo.*' => Reglas::MENSAJES['correo'],
            'celular.*' => Reglas::MENSAJES['celular'],
            'codigo.unique' => 'Ese código ya está registrado.',
            'codigo.*' => 'El código tiene el formato E-AAAA-NNN (por ejemplo, E-2026-001).',
        ]);
        foreach ($validador->errors()->toArray() as $campo => $lista) {
            // La fecha ya tiene su mensaje si no se pudo leer
            if ($campo === 'fecha_nacimiento' && $fecha === false) {
                continue;
            }
            $mensajes[] = $lista[0];
        }

        // Aula del periodo en curso: nivel + grado + sección
        $nivel = mb_strtoupper(Str::ascii(trim($v['nivel'])));
        $seccion = Str::ascii(mb_strtoupper(trim($v['seccion']))) === 'UNICA' ? 'Única' : mb_strtoupper(trim($v['seccion']));
        if (! in_array($nivel, ['PRIMARIA', 'SECUNDARIA'], true)) {
            $mensajes[] = 'El nivel es PRIMARIA o SECUNDARIA.';
        } elseif (! ctype_digit(trim($v['grado'])) || (int) $v['grado'] < 1 || (int) $v['grado'] > ($nivel === 'PRIMARIA' ? 6 : 5)) {
            $mensajes[] = 'El grado es un número de 1 a '.($nivel === 'PRIMARIA' ? 6 : 5).' en '.mb_strtolower($nivel).'.';
        } else {
            $grado = (int) $v['grado'];
            $nombreGrado = $grado.'.º '.mb_strtolower($nivel);
            $aula = $aulas->first(fn ($a) => $a->nivel === $nivel && $a->numero === $grado && $a->seccion === $seccion);
            if (! $aula) {
                $mensajes[] = $seccion === '' ? "Falta la sección de {$nombreGrado}." : "La sección «{$seccion}» no existe en {$nombreGrado}.";
            } else {
                $datos['aula'] = $aula;
                if ($fecha && $error = ReglasPersona::edadIncoherente($fecha, $nivel)) {
                    $mensajes[] = $error;
                }
            }
        }

        return [$datos, array_values(array_unique($mensajes))];
    }

    private function aulasDelPeriodo(int $periodoId)
    {
        $ocupados = DB::table('matricula')->where('periodo_id', $periodoId)->where('estado', 'ACTIVA')
            ->selectRaw('aula_id, COUNT(*) as total')->groupBy('aula_id')->pluck('total', 'aula_id');

        return DB::table('aula')->join('grado', 'grado.id', '=', 'aula.grado_id')->join('seccion', 'seccion.id', '=', 'aula.seccion_id')
            ->where('aula.periodo_id', $periodoId)
            ->get(['aula.id', 'aula.capacidad', 'grado.numero', 'grado.nivel', 'seccion.nombre as seccion'])
            ->map(function ($a) use ($ocupados) {
                $a->nombre = PerfilUsuario::nombreAula($a->numero, $a->nivel, $a->seccion);
                $a->matriculados = (int) ($ocupados[$a->id] ?? 0);

                return $a;
            });
    }

    /** "14/05/2012", "2012-05-14" o una fecha de Excel → "2012-05-14"; false si no es válida */
    private function fecha(string $valor): string|false
    {
        $valor = trim($valor);
        // Fecha guardada como número de serie de Excel (días desde el 30/12/1899)
        if (preg_match('/^\d{4,5}$/D', $valor)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $valor)->toDateString();
        }
        foreach (['d/m/Y', 'Y-m-d'] as $formato) {
            $f = \DateTime::createFromFormat('!'.$formato, $valor);
            if ($f && $f->format($formato) === $valor) {
                return $f->format('Y-m-d');
            }
        }

        return $valor === '' ? '' : false;
    }

    private function texto(mixed $celda): string
    {
        $valor = $celda instanceof \OpenSpout\Common\Entity\Cell ? $celda->getValue() : $celda;

        return match (true) {
            $valor instanceof DateTimeInterface => $valor->format('d/m/Y'),
            is_float($valor) && floor($valor) === $valor => (string) (int) $valor,
            is_bool($valor) => $valor ? '1' : '0',
            default => trim((string) $valor),
        };
    }

    /** Escribe un .xlsx temporal (todo como texto) y devuelve su ruta */
    private function escribir(array $filas): string
    {
        $carpeta = storage_path('app/tmp');
        if (! is_dir($carpeta)) {
            mkdir($carpeta, 0775, true);
        }
        $ruta = $carpeta.'/'.Str::random(20).'.xlsx';
        $opciones = new OpcionesEscritura;
        $opciones->setColumnWidth(18, ...range(1, count($filas[0])));
        $escritor = new Writer($opciones);
        $escritor->openToFile($ruta);
        $encabezado = (new Style)->setFontBold()->setBackgroundColor('F4EDE6');
        $texto = (new Style)->setFormat('@');
        foreach ($filas as $i => $fila) {
            $escritor->addRow(Excel::fila(array_map('strval', $fila), $i === 0 ? $encabezado : $texto));
        }
        $escritor->close();

        return $ruta;
    }

    public static function hoy(): string
    {
        return Carbon::today()->toDateString();
    }
}
