<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Database\Seeders\Demo\Azar;
use Database\Seeders\Demo\Calendario;
use Database\Seeders\Demo\Horario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Datos de demostración con personas ficticias y fechas relativas al día en que se
 * ejecuta: siempre hay un bimestre en curso (el III), dos cerrados y uno por venir.
 */
class DemostracionSeeder extends Seeder
{
    public const CLAVE_PERSONAL = 'Ayni#2026';

    public const CLAVE_ESTUDIANTE = 'piki2026';

    /** media, desviación, falta, tardanza, no entrega, entrega tardía */
    private const PERFILES = [
        'bueno' => [16.3, 1.8, 0.02, 0.03, 0.02, 0.05],
        'regular' => [13.6, 2.0, 0.05, 0.05, 0.07, 0.10],
        'medio' => [11.6, 2.0, 0.11, 0.07, 0.18, 0.15],
        'alto' => [9.0, 2.3, 0.20, 0.08, 0.32, 0.15],
        'descenso' => [16.0, 1.5, 0.03, 0.04, 0.04, 0.06],
    ];

    private const ASIGNATURAS = [
        'MAT' => ['Matemática', 3],
        'COM' => ['Comunicación', 3],
        'CYT' => ['Ciencia y Tecnología', 2],
        'PS' => ['Personal Social', 2],
        'CCSS' => ['Ciencias Sociales', 2],
        'ING' => ['Inglés', 2],
        'EF' => ['Educación Física', 1],
    ];

    private const TEMAS = [
        'MAT' => ['Números enteros', 'Ecuaciones', 'Proporcionalidad', 'Polinomios', 'Funciones', 'Inecuaciones', 'Geometría', 'Estadística', 'Probabilidad', 'Sistemas de ecuaciones', 'Trigonometría', 'Áreas y perímetros'],
        'COM' => ['Comprensión lectora', 'El texto narrativo', 'Ortografía', 'El párrafo', 'Texto expositivo', 'Ensayo argumentativo', 'Oratoria', 'La noticia', 'Figuras literarias', 'Redacción formal', 'El afiche', 'Debate'],
        'CYT' => ['La célula', 'Los ecosistemas', 'La materia', 'Energía', 'Fuerza y movimiento', 'El cuerpo humano', 'Mezclas', 'Electricidad', 'El agua', 'Reacciones químicas', 'La Tierra', 'Biodiversidad'],
        'PS' => ['Mi familia', 'Mi comunidad', 'Regiones del Perú', 'Derechos del niño', 'Historia local', 'Convivencia', 'Recursos naturales', 'Símbolos patrios', 'Mapas', 'Culturas preincas', 'Normas', 'Medio ambiente'],
        'CCSS' => ['Culturas andinas', 'El Tahuantinsuyo', 'La Colonia', 'La Independencia', 'Economía familiar', 'Geografía del Perú', 'Ciudadanía', 'Globalización', 'La República', 'Gestión de riesgos', 'Demografía', 'Derechos humanos'],
        'ING' => ['Greetings', 'Daily routines', 'Present simple', 'My family', 'Past simple', 'Food and drinks', 'Future plans', 'Comparatives', 'Hobbies', 'Directions', 'Weather', 'Present continuous'],
        'EF' => ['Resistencia', 'Coordinación', 'Vóley', 'Fútbol', 'Básquet', 'Atletismo', 'Flexibilidad', 'Juegos cooperativos', 'Danza', 'Hábitos saludables', 'Gimnasia', 'Recreación'],
    ];

    private Azar $azar;

    private Calendario $cal;

    private CarbonImmutable $ayer;

    private string $hashPersonal;

    private string $hashEstudiante;

    private array $rol = [];

    private int $periodoId;

    private array $bimestreId = [];

    private array $docentes = [];

    private array $aulas = [];

    private array $cursos = [];

    private array $estudiantes = [];

    private array $dnis = [];

    private array $correos = [];

    private int $adminUsuario;

    private ?int $archivoEntrega = null;

    private ?int $archivoMaterial = null;

    public function run(): void
    {
        $this->azar = new Azar(20261017);
        $institucion = DB::table('institucion')->first();
        $this->cal = new Calendario(now(), (int) $institucion->dias_cierre_bimestre);
        $this->ayer = $this->cal->hoy->subDay();
        $this->hashPersonal = Hash::make(self::CLAVE_PERSONAL);
        $this->hashEstudiante = Hash::make(self::CLAVE_ESTUDIANTE);
        $this->rol = DB::table('rol')->pluck('id', 'codigo')->all();
        $this->dnis = ['41234567' => true, '42345678' => true];

        DB::transaction(function () {
            $this->administrativos();
            $this->periodo();
            $this->docentes();
            $this->aulasYCursos();
            $this->horarios();
            $this->coordinaciones();
            $this->estudiantes();
            $this->archivosDemo();
            $this->historial();
            $this->claseSinRegistrar();
            $this->incidencias();
            $this->casosParaAlertas();
        });
    }

    // ------------------------------------------------------------ personas

    private function persona(string $nombres, string $apellidos, string $sexo, string $dni, ?string $nacimiento, ?string $correo, ?string $celular = null): int
    {
        return DB::table('persona')->insertGetId([
            'tipo_documento' => 'DNI',
            'numero_documento' => $dni,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'fecha_nacimiento' => $nacimiento,
            'sexo' => $sexo,
            'celular' => $celular ?? $this->azar->celular(),
            'correo' => $correo,
            'direccion' => $this->azar->elegir(['Av. Pacífico', 'Av. Anchoveta', 'Jr. Los Pinos', 'Urb. Buenos Aires', 'Av. Country', 'Urb. Bruces']).' '.$this->azar->entero(100, 999).', Nuevo Chimbote',
        ]);
    }

    private function usuario(string $rol, string $username, ?string $correo, string $hash): int
    {
        return DB::table('usuario')->insertGetId([
            'rol_id' => $this->rol[$rol],
            'username' => $username,
            'email' => $correo,
            'password_hash' => $hash,
        ]);
    }

    private function dni(string $inicio): string
    {
        do {
            $dni = $inicio.str_pad((string) $this->azar->entero(0, 9_999_999), 7, '0', STR_PAD_LEFT);
        } while (isset($this->dnis[$dni]));
        $this->dnis[$dni] = true;

        return $dni;
    }

    private function correo(string $nombres, string $apellidos): string
    {
        $base = Str::lower(Str::ascii(explode(' ', $nombres)[0].'.'.explode(' ', $apellidos)[0]));
        $correo = "{$base}@ayni.test";
        for ($n = 2; isset($this->correos[$correo]); $n++) {
            $correo = "{$base}{$n}@ayni.test";
        }
        $this->correos[$correo] = true;

        return $correo;
    }

    private function administrativos(): void
    {
        foreach ([['Carlos', 'Medina Rojas', 'M', 'cmedina', 'Administrador'], ['Patricia', 'Romero Salazar', 'F', 'promero', 'Secretaría académica']] as [$n, $a, $s, $user, $cargo]) {
            $correo = $this->correo($n, $a);
            $persona = $this->persona($n, $a, $s, $this->dni('4'), '1982-05-14', $correo);
            $usuario = $this->usuario('ADMIN', $user, $correo, $this->hashPersonal);
            DB::table('personal_administrativo')->insert(['persona_id' => $persona, 'usuario_id' => $usuario, 'cargo' => $cargo]);
            $this->adminUsuario ??= $usuario;
        }
    }

    private function docentes(): void
    {
        $lista = [
            'tutor1' => ['Rosa', 'Huamán Flores', 'F', null, 'Educación Primaria'],
            'tutor2' => ['Miguel', 'Torres Rojas', 'M', null, 'Educación Primaria'],
            'tutor3' => ['Gloria', 'Chávez León', 'F', null, 'Educación Primaria'],
            'tutor4' => ['Pedro', 'Salazar Cruz', 'M', null, 'Educación Primaria'],
            'tutor5' => ['Silvia', 'Navarro Ponce', 'F', null, 'Educación Primaria'],
            'tutor6' => ['Elena', 'Huamán Vega', 'F', null, 'Educación Primaria'],
            'ing_p' => ['Martha', 'Ruiz Campos', 'F', null, 'Inglés'],
            'ef' => ['Javier', 'Gutiérrez Mori', 'M', null, 'Educación Física'],
            'mat_a' => ['Raúl', 'Benites Soto', 'M', null, 'Matemática'],
            'mat_b' => ['Luis', 'Paredes Aranda', 'M', '41234567', 'Matemática'],
            'com_a' => ['Teresa', 'Villanueva Díaz', 'F', null, 'Comunicación'],
            'com_b' => ['Carmen', 'Rivas Mendoza', 'F', '42345678', 'Comunicación'],
            'cyt' => ['Jorge', 'Castillo Ramos', 'M', null, 'Ciencia y Tecnología'],
            'soc' => ['Ana', 'Lozano Vargas', 'F', null, 'Ciencias Sociales'],
            'ing_s' => ['Rocío', 'Díaz Herrera', 'F', null, 'Inglés'],
        ];
        foreach ($lista as $clave => [$n, $a, $s, $dni, $especialidad]) {
            $dni ??= $this->dni('4');
            $correo = $this->correo($n, $a);
            $nacimiento = $this->cal->inicio->subYears($this->azar->entero(28, 55))->subDays($this->azar->entero(0, 360))->toDateString();
            $persona = $this->persona($n, $a, $s, $dni, $nacimiento, $correo);
            $usuario = $this->usuario('DOCENTE', $dni, $correo, $this->hashPersonal);
            $id = DB::table('docente')->insertGetId([
                'persona_id' => $persona,
                'usuario_id' => $usuario,
                'titulo' => 'Licenciado(a) en Educación',
                'especialidad' => $especialidad,
                'fecha_inicio_contrato' => $this->cal->inicio->subDays(30)->toDateString(),
                'fecha_fin_contrato' => $this->cal->fin->addDays(10)->toDateString(),
            ]);
            $this->docentes[$clave] = ['id' => $id, 'usuario' => $usuario];
        }
    }

    // ------------------------------------------------------------ estructura

    private function periodo(): void
    {
        $this->periodoId = DB::table('periodo_academico')->insertGetId([
            'nombre' => $this->cal->nombrePeriodo(),
            'fecha_inicio' => $this->cal->inicio->toDateString(),
            'fecha_fin' => $this->cal->fin->toDateString(),
            'estado' => 'EN_CURSO',
        ]);

        foreach ($this->cal->bimestres as $n => $b) {
            $cerrado = $this->cal->cerrado($n);
            $this->bimestreId[$n] = DB::table('subperiodo')->insertGetId([
                'periodo_id' => $this->periodoId,
                'numero' => $n,
                'nombre' => $b['nombre'],
                'fecha_inicio' => $b['inicio']->toDateString(),
                'fecha_fin' => $b['fin']->toDateString(),
                'fecha_cierre_notas' => $b['cierre']->toDateString(),
                'estado' => $cerrado ? 'CERRADO' : 'ABIERTO',
                'cerrado_en' => $cerrado ? $b['cierre']->addDay()->toDateTimeString() : null,
            ]);
        }
    }

    private function aulasYCursos(): void
    {
        $grado = DB::table('grado')->get()->keyBy(fn ($g) => $g->nivel.$g->numero);
        $seccion = DB::table('seccion')->pluck('id', 'nombre');

        $asignatura = [];
        foreach (self::ASIGNATURAS as $codigo => [$nombre, $bloques]) {
            $asignatura[$codigo] = DB::table('asignatura')->insertGetId(['codigo' => $codigo, 'nombre' => $nombre, 'horas_semanales' => $bloques * 2]);
        }

        $definicion = [];
        for ($g = 1; $g <= 6; $g++) {
            $definicion[] = ['PRIMARIA', $g, 'Única', "tutor{$g}", 25, "Aula P{$g}"];
        }
        foreach ([[1, 'A', 'mat_a', 10], [2, 'A', 'com_a', 11], [3, 'A', 'cyt', 13], [3, 'B', 'com_b', 12], [4, 'A', 'soc', 14], [5, 'A', 'mat_b', 15]] as [$g, $s, $tutor, $n]) {
            $definicion[] = ['SECUNDARIA', $g, $s, $tutor, 30, "Aula {$n}"];
        }

        foreach ($definicion as [$nivel, $g, $s, $tutor, $capacidad, $ambiente]) {
            $id = DB::table('aula')->insertGetId([
                'periodo_id' => $this->periodoId,
                'grado_id' => $grado[$nivel.$g]->id,
                'seccion_id' => $seccion[$s],
                'tutor_docente_id' => $this->docentes[$tutor]['id'],
                'capacidad' => $capacidad,
                'ambiente' => $ambiente,
            ]);
            $clave = $nivel === 'PRIMARIA' ? "{$g}P" : "{$g}{$s}";
            $this->aulas[$clave] = ['id' => $id, 'nivel' => $nivel, 'grado' => $g, 'tutor' => $tutor, 'ambiente' => $ambiente];

            $primaria = $nivel === 'PRIMARIA';
            $grupoA = in_array($clave, ['1A', '2A', '3A'], true);
            $carga = $primaria
                ? ['MAT' => "tutor{$g}", 'COM' => "tutor{$g}", 'CYT' => "tutor{$g}", 'PS' => "tutor{$g}", 'ING' => 'ing_p', 'EF' => 'ef']
                : ['MAT' => $grupoA ? 'mat_a' : 'mat_b', 'COM' => $grupoA ? 'com_a' : 'com_b', 'CYT' => 'cyt', 'CCSS' => 'soc', 'ING' => 'ing_s', 'EF' => 'ef'];

            foreach ($carga as $codigo => $docente) {
                $cursoId = DB::table('curso_aula')->insertGetId([
                    'aula_id' => $id,
                    'asignatura_id' => $asignatura[$codigo],
                    'docente_id' => $this->docentes[$docente]['id'],
                ]);
                $this->cursos[$cursoId] = ['id' => $cursoId, 'aula' => $clave, 'codigo' => $codigo, 'docente' => $docente, 'bloques' => self::ASIGNATURAS[$codigo][1]];
            }
        }
    }

    private function horarios(): void
    {
        $entrada = array_map(fn ($c) => [
            'curso' => $c['id'],
            'aula' => $this->aulas[$c['aula']]['id'],
            'docente' => $this->docentes[$c['docente']]['id'],
            'bloques' => $c['bloques'],
        ], array_values($this->cursos));

        $filas = [];
        foreach ((new Horario($this->azar))->armar($entrada) as $cursoId => $bloques) {
            $curso = $this->cursos[$cursoId];
            $ambiente = match (true) {
                $curso['codigo'] === 'EF' => 'Losa deportiva',
                $curso['codigo'] === 'CYT' && $this->aulas[$curso['aula']]['nivel'] === 'SECUNDARIA' => 'Lab. 1',
                default => $this->aulas[$curso['aula']]['ambiente'],
            };
            foreach ($bloques as $b) {
                $filas[] = ['curso_aula_id' => $cursoId, 'dia_semana' => $b['dia'], 'hora_inicio' => $b['inicio'], 'hora_fin' => $b['fin'], 'ambiente' => $ambiente];
                $this->cursos[$cursoId]['dias'][] = $b['dia'];
            }
        }
        DB::table('horario')->insert($filas);
    }

    private function coordinaciones(): void
    {
        DB::table('coordinacion')->insert([
            ['docente_id' => $this->docentes['mat_b']['id'], 'periodo_id' => $this->periodoId, 'ambito' => 'AULA', 'aula_id' => $this->aulas['3A']['id'], 'grado_id' => null, 'nivel' => null],
            ['docente_id' => $this->docentes['tutor6']['id'], 'periodo_id' => $this->periodoId, 'ambito' => 'NIVEL', 'aula_id' => null, 'grado_id' => null, 'nivel' => 'PRIMARIA'],
        ]);
    }

    // ------------------------------------------------------------ estudiantes

    private function estudiantes(): void
    {
        $clave = [
            '3B' => [
                ['Valeria', 'Quispe Mendoza', 'F', 'bueno', 'E-2026-001', true],
                ['Kevin', 'Aranda Ruiz', 'M', 'alto', null, false],
                ['Lucía', 'Herrera Campos', 'F', 'bueno', null, false],
                ['Andrés', 'Mori Lazo', 'M', 'medio', null, false],
                ['Diego', 'Ramírez Torres', 'M', 'alto', null, false],
                ['Paolo', 'Salas Vega', 'M', 'regular', null, false],
                ['Camila', 'Soto Vargas', 'F', 'medio', null, false],
            ],
            '3A' => [['Rodrigo', 'Sáenz Palacios', 'M', 'alto', null, false]],
            '4P' => [['Mateo', 'Flores Ccahuana', 'M', 'bueno', 'E-2026-002', false]],
        ];
        // Ajustes para que los casos del mockup se noten
        $ajustes = [
            'Andrés Mori Lazo' => [2 => 0.12],
            'Rodrigo Sáenz Palacios' => [2 => 0.24],
            'Kevin Aranda Ruiz' => [0 => 8.5, 2 => 0.08],
            'Camila Soto Vargas' => [4 => 0.35],
            'Diego Ramírez Torres' => [2 => 0.18, 0 => 10.5],
        ];

        $correlativo = [];
        foreach ($this->aulas as $codigoAula => $aula) {
            $cantidad = $aula['nivel'] === 'PRIMARIA' ? $this->azar->entero(10, 12) : $this->azar->entero(13, 15);
            $lista = $clave[$codigoAula] ?? [];
            while (count($lista) < $cantidad) {
                $sexo = $this->azar->probabilidad(0.5) ? 'F' : 'M';
                $lista[] = [$this->azar->nombre($sexo), $this->azar->apellido().' '.$this->azar->apellido(), $sexo, $this->perfilAleatorio(), null, null];
            }

            foreach ($lista as [$nombres, $apellidos, $sexo, $perfil, $codigo, $conCorreo]) {
                $edadBase = $aula['nivel'] === 'PRIMARIA' ? $aula['grado'] + 5 : $aula['grado'] + 11;
                $nacimiento = $this->cal->inicio->subYears($edadBase)->subDays($this->azar->entero(0, 360));

                if (! $codigo) {
                    $anios = $aula['nivel'] === 'PRIMARIA' ? $aula['grado'] - 1 : $aula['grado'] + 5;
                    $anioIngreso = (int) $this->cal->nombrePeriodo() - $this->azar->entero(0, min($anios, 6));
                    $correlativo[$anioIngreso] = ($correlativo[$anioIngreso] ?? 100) + 1;
                    $codigo = sprintf('E-%d-%03d', $anioIngreso, $correlativo[$anioIngreso]);
                }
                $anioIngreso = (int) substr($codigo, 2, 4);
                $conCorreo ??= $aula['nivel'] === 'SECUNDARIA' && $this->azar->probabilidad(0.4);
                $correo = $conCorreo ? $this->correo($nombres, $apellidos) : null;

                $persona = $this->persona($nombres, $apellidos, $sexo, $this->dni($this->azar->elegir(['7', '8'])), $nacimiento->toDateString(), $correo);
                $usuario = $this->usuario('ESTUDIANTE', $codigo, $correo, $this->hashEstudiante);
                $estudianteId = DB::table('estudiante')->insertGetId([
                    'persona_id' => $persona,
                    'usuario_id' => $usuario,
                    'codigo' => $codigo,
                    'fecha_ingreso' => "{$anioIngreso}-03-01",
                ]);
                $matricula = DB::table('matricula')->insertGetId([
                    'estudiante_id' => $estudianteId,
                    'aula_id' => $aula['id'],
                    'periodo_id' => $this->periodoId,
                    'fecha' => $this->cal->inicio->subDays($this->azar->entero(10, 40))->toDateString(),
                    'registrado_por' => $this->adminUsuario,
                ]);
                $this->apoderados($estudianteId, $apellidos);

                $parametros = self::PERFILES[$perfil];
                foreach ($ajustes["{$nombres} {$apellidos}"] ?? [] as $i => $valor) {
                    $parametros[$i] = $valor;
                }
                $this->estudiantes[$matricula] = [
                    'matricula' => $matricula,
                    'usuario' => $usuario,
                    'aula' => $codigoAula,
                    'perfil' => $perfil,
                    'p' => $parametros,
                    'afinidad' => [],
                ];
            }
        }
    }

    private function perfilAleatorio(): string
    {
        $r = $this->azar->entero(1, 100);

        return match (true) {
            $r <= 55 => 'bueno',
            $r <= 80 => 'regular',
            $r <= 90 => 'medio',
            $r <= 96 => 'alto',
            default => 'descenso',
        };
    }

    private function apoderados(int $estudianteId, string $apellidos): void
    {
        [$paterno, $materno] = array_pad(explode(' ', $apellidos, 2), 2, $apellidos);
        $filas = [[
            'estudiante_id' => $estudianteId,
            'nombres' => $this->azar->nombre('F').' '.$materno.' '.$this->azar->apellido(),
            'celular' => $this->azar->celular(),
            'parentesco' => 'Madre',
            'es_principal' => true,
        ]];
        if ($this->azar->probabilidad(0.6)) {
            $filas[] = [
                'estudiante_id' => $estudianteId,
                'nombres' => $this->azar->nombre('M').' '.$paterno.' '.$this->azar->apellido(),
                'celular' => $this->azar->celular(),
                'parentesco' => 'Padre',
                'es_principal' => false,
            ];
        }
        DB::table('apoderado')->insert($filas);
    }

    // ------------------------------------------------------------ historial

    private function archivosDemo(): void
    {
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
        foreach (['entrega' => 'Entrega de ejemplo.pdf', 'material' => 'Guía de la actividad.pdf'] as $tipo => $nombre) {
            $ruta = "demo/{$tipo}-demo.pdf";
            Storage::disk('local')->put($ruta, $pdf);
            $id = DB::table('archivo')->insertGetId([
                'ruta' => $ruta,
                'nombre_original' => $nombre,
                'tipo_mime' => 'application/pdf',
                'tamano_bytes' => strlen($pdf),
                'hash_sha256' => hash('sha256', $pdf),
            ]);
            $tipo === 'entrega' ? $this->archivoEntrega = $id : $this->archivoMaterial = $id;
        }
    }

    private function historial(): void
    {
        $tipos = DB::table('tipo_evaluacion')->pluck('id', 'nombre');
        $bimestreActual = $this->cal->bimestreDeHoy();
        // Dos docentes dejaron de registrar asistencia esta semana (para las alertas administrativas)
        $sinRegistroReciente = ['ing_p', 'soc'];

        foreach ($this->cursos as $curso) {
            $alumnos = array_filter($this->estudiantes, fn ($e) => $e['aula'] === $curso['aula']);
            $usuarioDocente = $this->docentes[$curso['docente']]['usuario'];
            $pendienteDeCalificar = $this->azar->probabilidad(0.25);
            $numeroPractica = 0;
            $temas = $this->azar->mezclar(self::TEMAS[$curso['codigo']]);

            for ($b = 1; $b <= $bimestreActual; $b++) {
                $this->asistencias($curso, $b, $alumnos, $usuarioDocente, in_array($curso['docente'], $sinRegistroReciente, true));

                // Días desde el lunes de inicio: siempre hay al menos una actividad abierta
                $plantillas = [
                    ['Práctica', 2, 11, 1], ['Práctica', 14, 25, 1], ['Proyecto', 21, 46, 2], ['Práctica', 42, 53, 1], ['Examen', 56, 60, 3],
                ];
                $notasBimestre = [];
                // La última actividad ya vencida del bimestre en curso puede quedar sin calificar
                $ultimaVencida = null;
                foreach ($plantillas as $i => [$tipo, , $hasta]) {
                    if ($tipo !== 'Examen' && $this->cal->dia($b, $hasta)->lt($this->cal->hoy)) {
                        $ultimaVencida = $i;
                    }
                }
                foreach ($plantillas as $i => [$tipo, $desde, $hasta, $peso]) {
                    $tema = $temas[(($b - 1) * count($plantillas) + $i) % count($temas)];
                    $titulo = match ($tipo) {
                        'Práctica' => 'Práctica '.(++$numeroPractica)." · {$tema}",
                        'Proyecto' => "Proyecto · {$tema}",
                        default => 'Examen bimestral',
                    };
                    $asignacion = $this->cal->dia($b, $desde);
                    $limite = $this->cal->dia($b, $hasta);

                    $evaluacionId = DB::table('evaluacion')->insertGetId([
                        'curso_aula_id' => $curso['id'],
                        'subperiodo_id' => $this->bimestreId[$b],
                        'tipo_evaluacion_id' => $tipos[$tipo],
                        'titulo' => $titulo,
                        'indicaciones' => $this->indicaciones($tipo),
                        'fecha_asignacion' => $asignacion->toDateString(),
                        'fecha_limite' => $limite->toDateString(),
                        'peso' => $peso,
                        'creado_por' => $usuarioDocente,
                        'created_at' => $asignacion->setTime(7, 30),
                    ]);
                    if ($tipo !== 'Examen' && $b === $bimestreActual) {
                        DB::table('evaluacion_material')->insert(['evaluacion_id' => $evaluacionId, 'archivo_id' => $this->archivoMaterial]);
                    }

                    $pasada = $limite->lt($this->cal->hoy);
                    if (! $pasada && $asignacion->gt($this->cal->hoy)) {
                        continue;
                    }
                    $sinCalificar = ! $pasada || ($b === $bimestreActual && $pendienteDeCalificar && $i === $ultimaVencida);

                    $notasBimestre[] = [$peso, $this->notasYEntregas($evaluacionId, $tipo, $asignacion, $limite, $pasada, $sinCalificar, $alumnos, $curso, $b, $usuarioDocente)];
                }

                if ($this->cal->cerrado($b)) {
                    $this->calificaciones($curso, $b, $alumnos, $notasBimestre);
                }
            }
        }
    }

    private function asistencias(array $curso, int $b, array $alumnos, int $usuarioDocente, bool $sinRegistroReciente): void
    {
        $hasta = $sinRegistroReciente ? $this->cal->hoy->subDays(8) : $this->ayer;
        $fechas = [];
        foreach (array_unique($curso['dias'] ?? []) as $dia) {
            foreach ($this->cal->diasDeClase($b, $dia, $hasta) as $fecha) {
                $fechas[] = $fecha;
            }
        }
        if (! $fechas) {
            return;
        }
        usort($fechas, fn ($x, $y) => $x <=> $y);

        DB::table('sesion_clase')->insert(array_map(fn ($f) => [
            'curso_aula_id' => $curso['id'],
            'subperiodo_id' => $this->bimestreId[$b],
            'fecha' => $f->toDateString(),
            'estado' => 'DEFINITIVO',
            'registrado_por' => $usuarioDocente,
            'created_at' => $f->setTime(13, 0),
            'updated_at' => $f->setTime(13, 0),
        ], $fechas));
        $sesiones = DB::table('sesion_clase')->where('curso_aula_id', $curso['id'])->where('subperiodo_id', $this->bimestreId[$b])->pluck('id', 'fecha');

        $filas = [];
        foreach ($sesiones as $fecha => $sesionId) {
            $avance = $this->avance(CarbonImmutable::parse($fecha));
            foreach ($alumnos as $e) {
                [, , $falta, $tarde] = $e['p'];
                if ($e['perfil'] === 'descenso') {
                    $falta += 0.15 * $avance;
                }
                $r = $this->azar->entero(1, 10_000) / 10_000;
                $estado = match (true) {
                    $r < $falta * 0.9 => 'F',
                    $r < $falta => 'J',
                    $r < $falta + $tarde => 'T',
                    default => 'P',
                };
                $filas[] = [
                    'sesion_clase_id' => $sesionId,
                    'matricula_id' => $e['matricula'],
                    'estado' => $estado,
                    'observacion' => match ($estado) {
                        'T' => 'Llegó '.$this->azar->elegir(['10', '15', '20']).' min tarde',
                        'J' => $this->azar->elegir(['Cita médica con constancia', 'Permiso del apoderado', 'Representó al colegio']),
                        default => null,
                    },
                    'registrado_por' => $usuarioDocente,
                ];
            }
        }
        foreach (array_chunk($filas, 1000) as $lote) {
            DB::table('asistencia')->insert($lote);
        }
    }

    /**
     * El docente demo aún no registra su última clase de cada curso: así siempre puede probar el registro (M8).
     * Se borra después de generarla para no cambiar la secuencia aleatoria del resto de los datos.
     */
    private function claseSinRegistrar(): void
    {
        $cursos = array_keys(array_filter($this->cursos, fn ($c) => $c['docente'] === 'mat_b'));
        foreach ($cursos as $cursoId) {
            DB::table('sesion_clase')->where('curso_aula_id', $cursoId)->where('subperiodo_id', $this->bimestreId[$this->cal->bimestreDeHoy()])
                ->orderByDesc('fecha')->limit(1)->delete();
        }
    }

    /** Qué tan avanzado está el periodo en esa fecha (0 al inicio, 1 hoy) */
    private function avance(CarbonImmutable $fecha): float
    {
        $total = max(1, $this->cal->inicio->diffInDays($this->cal->hoy));

        return min(1, max(0, $this->cal->inicio->diffInDays($fecha) / $total));
    }

    private function notasYEntregas(int $evaluacionId, string $tipo, CarbonImmutable $asignacion, CarbonImmutable $limite, bool $pasada, bool $sinCalificar, array $alumnos, array $curso, int $b, int $usuarioDocente): array
    {
        $avance = $this->avance($limite->min($this->cal->hoy));
        $entregas = [];
        $notas = [];
        $resultado = [];

        foreach ($alumnos as $matricula => $e) {
            [$media, $desv, , , $noEntrega, $tardia] = $e['p'];
            if ($e['perfil'] === 'descenso') {
                $media -= 7 * $avance;
                $noEntrega += 0.25 * $avance;
            }
            $afinidad = $this->estudiantes[$matricula]['afinidad'][$curso['codigo']] ??= $this->azar->normal(0, 1.0);

            if ($tipo === 'Examen') {
                $estado = 'ENTREGADO';
            } elseif (! $pasada) {
                // Actividad en curso: algunos ya entregaron
                if ($e['perfil'] === 'bueno' && $this->azar->probabilidad(0.35)) {
                    $entregadaEn = $asignacion->addDays($this->azar->entero(0, max(0, $asignacion->diffInDays($this->cal->hoy))))->setTime($this->azar->entero(15, 21), $this->azar->entero(0, 59));
                    if ($entregadaEn->lt(now())) {
                        $entregas[] = $this->entrega($evaluacionId, $matricula, $entregadaEn, false);
                    }
                }

                continue;
            } elseif ($this->azar->probabilidad($noEntrega)) {
                $estado = 'NO_ENTREGADO';
            } else {
                $estado = $this->azar->probabilidad($tardia) ? 'TARDIO' : 'ENTREGADO';
                $entregadaEn = $estado === 'TARDIO'
                    ? $limite->addDays($this->azar->entero(1, 2))->setTime($this->azar->entero(8, 20), $this->azar->entero(0, 59))
                    : $limite->subDays($this->azar->entero(0, 2))->setTime($this->azar->entero(15, 23), $this->azar->entero(0, 59));
                if ($entregadaEn->gte(now())) {
                    // Una tardía de ayer no puede quedar en el futuro: primeros minutos del día siguiente al límite
                    $entregadaEn = $limite->addDay()->setTime(0, 5);
                }
                $entregas[] = $this->entrega($evaluacionId, $matricula, $entregadaEn, $estado === 'TARDIO');
            }

            $nota = null;
            if ($estado !== 'NO_ENTREGADO' && ! $sinCalificar) {
                $nota = $this->azar->normal($media + $afinidad, $desv) - ($estado === 'TARDIO' ? 1 : 0);
                $nota = (float) max(0, min(20, round($nota)));
            }
            $resultado[$matricula] = $nota;

            $notas[] = [
                'evaluacion_id' => $evaluacionId,
                'matricula_id' => $matricula,
                'nota' => $nota,
                'estado_entrega' => $estado,
                'observacion' => $estado === 'TARDIO' ? 'Entrega tardía' : null,
                'registrado_por' => $sinCalificar ? null : $usuarioDocente,
                'created_at' => $limite->addDays(3)->min($this->ayer)->setTime(18, 0),
                'updated_at' => $limite->addDays(3)->min($this->ayer)->setTime(18, 0),
            ];
        }

        if ($entregas) {
            DB::table('entrega')->insert($entregas);
        }
        if ($notas) {
            DB::table('nota_evaluacion')->insert($notas);
        }

        return $resultado;
    }

    private function entrega(int $evaluacionId, int $matricula, CarbonImmutable $en, bool $tardia): array
    {
        return [
            'evaluacion_id' => $evaluacionId,
            'matricula_id' => $matricula,
            'archivo_id' => $this->archivoEntrega,
            'estado' => 'ENTREGADA',
            'entregada_en' => $en,
            'es_tardia' => $tardia,
            'created_at' => $en,
            'updated_at' => $en,
        ];
    }

    /** RN-09: promedio ponderado; lo que no tiene nota cuenta como 0 */
    private function calificaciones(array $curso, int $b, array $alumnos, array $notasBimestre): void
    {
        $cierre = $this->cal->bimestres[$b]['cierre'];
        $filas = [];
        foreach ($alumnos as $matricula => $e) {
            $suma = 0;
            $pesos = 0;
            foreach ($notasBimestre as [$peso, $notas]) {
                $suma += ($notas[$matricula] ?? 0) * $peso;
                $pesos += $peso;
            }
            $filas[] = [
                'matricula_id' => $matricula,
                'curso_aula_id' => $curso['id'],
                'subperiodo_id' => $this->bimestreId[$b],
                'promedio' => $pesos ? round($suma / $pesos, 1, PHP_ROUND_HALF_UP) : null,
                'estado' => 'CERRADA',
                'publicado_en' => $cierre->subDays(2)->setTime(17, 0),
                'publicado_por' => $this->docentes[$curso['docente']]['usuario'],
                'cerrado_en' => $cierre->addDay(),
            ];
        }
        DB::table('calificacion_periodo')->insert($filas);
    }

    private function indicaciones(string $tipo): string
    {
        return match ($tipo) {
            'Práctica' => "Resuelve los ejercicios propuestos.\nIncluye el procedimiento en cada respuesta.\nGuarda tu trabajo en un único archivo PDF.",
            'Proyecto' => "Trabaja el proyecto según la guía de apoyo.\nPresenta un informe breve con conclusiones.\nEntrega un único archivo PDF.",
            default => 'Evaluación escrita en clase. Repasa los temas del bimestre.',
        };
    }

    private function incidencias(): void
    {
        $tipos = DB::table('tipo_incidencia')->pluck('id', 'nombre');
        $descripciones = [
            'Conducta' => ['Interrumpe la clase de forma reiterada.', 'Uso del celular durante la clase sin permiso.', 'Discusión con un compañero durante el recreo.'],
            'Académica' => ['No presentó la tarea por tercera vez en el mes.', 'No trae los materiales de trabajo.', 'Copió en la práctica calificada.'],
            'Puntualidad' => ['Tardanzas reiteradas en la primera hora.', 'Llegó tarde después del recreo varias veces.'],
            'Salud' => ['Se retiró temprano por malestar estomacal.'],
            'Familiar' => ['El apoderado informa una situación familiar delicada.'],
        ];

        $filas = [];
        foreach ($this->estudiantes as $matricula => $e) {
            $cantidad = match ($e['perfil']) {
                'alto' => $this->azar->entero(1, 3),
                'medio' => $this->azar->entero(0, 1),
                'descenso' => $this->azar->entero(0, 2),
                default => $this->azar->probabilidad(0.04) ? 1 : 0,
            };
            for ($k = 0; $k < $cantidad; $k++) {
                $tipo = $e['perfil'] === 'alto' ? $this->azar->elegir(['Conducta', 'Académica', 'Puntualidad']) : $this->azar->elegir(array_keys($descripciones));
                $fecha = $this->cal->bimestres[2]['inicio']->addDays($this->azar->entero(0, (int) $this->cal->bimestres[2]['inicio']->diffInDays($this->ayer)));
                if ($fecha->isWeekend()) {
                    $fecha = $fecha->previous(CarbonImmutable::FRIDAY);
                }
                $filas[] = [
                    'matricula_id' => $matricula,
                    'tipo_incidencia_id' => $tipos[$tipo],
                    'registrado_por' => $this->docentes[$this->aulas[$e['aula']]['tutor']]['usuario'],
                    'fecha' => $fecha->toDateString(),
                    'gravedad' => $e['perfil'] === 'alto' && $this->azar->probabilidad(0.25) ? 'GRAVE' : ($this->azar->probabilidad(0.3) ? 'MODERADA' : 'LEVE'),
                    'descripcion' => $this->azar->elegir($descripciones[$tipo]),
                ];
            }
        }
        DB::table('incidencia')->insert($filas);
    }

    /** Situaciones que el administrador debe atender (alertas del M11) */
    private function casosParaAlertas(): void
    {
        // Estudiantes importados que aún no tienen matrícula
        for ($k = 1; $k <= 4; $k++) {
            $sexo = $k % 2 ? 'M' : 'F';
            $nombres = $this->azar->nombre($sexo);
            $apellidos = $this->azar->apellido().' '.$this->azar->apellido();
            $codigo = sprintf('E-%s-%03d', $this->cal->nombrePeriodo(), 900 + $k);
            $persona = $this->persona($nombres, $apellidos, $sexo, $this->dni('7'), $this->cal->inicio->subYears(13)->toDateString(), null);
            $usuario = $this->usuario('ESTUDIANTE', $codigo, null, $this->hashEstudiante);
            DB::table('usuario')->where('id', $usuario)->update(['debe_cambiar_password' => true]);
            $id = DB::table('estudiante')->insertGetId(['persona_id' => $persona, 'usuario_id' => $usuario, 'codigo' => $codigo, 'fecha_ingreso' => $this->cal->hoy->subDays(20)->toDateString(), 'origen' => 'EXCEL']);
            $this->apoderados($id, $apellidos);
        }

        // Dos cuentas bloqueadas por intentos fallidos (no son las de demostración)
        $bloquear = array_slice(array_filter($this->estudiantes, fn ($e) => $e['aula'] === '1A'), 0, 2);
        foreach ($bloquear as $e) {
            DB::table('usuario')->where('id', $e['usuario'])->update(['estado' => 'BLOQUEADO', 'intentos_fallidos' => 5]);
        }
    }
}
