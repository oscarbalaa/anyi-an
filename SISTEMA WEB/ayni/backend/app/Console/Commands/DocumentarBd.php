<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Genera el diccionario de datos y el diagrama ER (Mermaid) leyendo la BD real. */
class DocumentarBd extends Command
{
    protected $signature = 'ayni:documentar-bd {--salida=docs : Carpeta donde se escriben los archivos}';

    protected $description = 'Genera diccionario.md y diagrama-er.md a partir de la base de datos';

    private const OMITIR = ['migrations', 'personal_access_tokens'];

    private const GRUPOS = [
        'Seguridad' => ['rol', 'usuario', 'auditoria', 'recuperacion_password', 'captcha_desafio'],
        'Personas' => ['persona', 'personal_administrativo', 'docente', 'estudiante', 'apoderado'],
        'Configuración' => ['institucion', 'escala_literal'],
        'Estructura académica' => ['periodo_academico', 'subperiodo', 'grado', 'seccion', 'aula', 'coordinacion', 'matricula', 'asignatura', 'curso_aula', 'horario'],
        'Seguimiento académico' => ['tipo_evaluacion', 'archivo', 'evaluacion', 'evaluacion_material', 'entrega', 'nota_evaluacion', 'calificacion_periodo', 'sesion_clase', 'asistencia', 'tipo_incidencia', 'incidencia'],
        'Riesgo, alertas y notificaciones' => ['modelo_ml', 'prediccion_riesgo', 'factor_riesgo', 'recomendacion', 'alerta_estudiantil', 'intervencion', 'historial_alerta', 'alerta_administrativa', 'notificacion'],
    ];

    public function handle(): int
    {
        $salida = rtrim($this->option('salida'), '/\\');
        if (! is_dir($salida)) {
            mkdir($salida, 0775, true);
        }

        $tablas = $this->tablas();
        file_put_contents("{$salida}/diccionario.md", $this->diccionario($tablas));
        file_put_contents("{$salida}/diagrama-er.md", $this->diagrama($tablas));

        $this->info(count($tablas).' tablas documentadas en '.$salida);

        return self::SUCCESS;
    }

    private function tablas(): Collection
    {
        $esquema = DB::getDatabaseName();
        $columnas = collect(DB::select('SELECT table_name AS tabla, column_name AS campo, column_type AS tipo, is_nullable AS nulo, column_key AS clave, column_default AS defecto, extra AS extra, column_comment AS comentario FROM information_schema.columns WHERE table_schema = ? ORDER BY table_name, ordinal_position', [$esquema]))->groupBy('tabla');
        $fks = collect(DB::select('SELECT table_name AS tabla, column_name AS campo, referenced_table_name AS ref_tabla, referenced_column_name AS ref_campo FROM information_schema.key_column_usage WHERE table_schema = ? AND referenced_table_name IS NOT NULL', [$esquema]))->groupBy('tabla');
        $checks = collect(DB::select('SELECT tc.table_name AS tabla, cc.constraint_name AS nombre, cc.check_clause AS condicion FROM information_schema.check_constraints cc JOIN information_schema.table_constraints tc ON tc.constraint_schema = cc.constraint_schema AND tc.constraint_name = cc.constraint_name WHERE cc.constraint_schema = ?', [$esquema]))->groupBy('tabla');
        $comentarios = collect(DB::select('SELECT table_name AS tabla, table_comment AS comentario FROM information_schema.tables WHERE table_schema = ?', [$esquema]))->pluck('comentario', 'tabla');

        $orden = array_merge(...array_values(self::GRUPOS));

        return $columnas->keys()
            ->reject(fn ($t) => in_array($t, self::OMITIR, true))
            ->sortBy(fn ($t) => array_search($t, $orden, true) === false ? 999 : array_search($t, $orden, true))
            ->mapWithKeys(fn ($t) => [$t => (object) [
                'nombre' => $t,
                'comentario' => $comentarios[$t] ?? '',
                'columnas' => $columnas[$t],
                'fks' => ($fks[$t] ?? collect())->keyBy('campo'),
                'checks' => $checks[$t] ?? collect(),
            ]]);
    }

    private function diccionario(Collection $tablas): string
    {
        $md = "# Diccionario de datos de AYNI\n\n";
        $md .= "Generado desde la base de datos con `php artisan ayni:documentar-bd`. **No editar a mano.**\n\n";
        $md .= "{$tablas->count()} tablas · MySQL 8 / MariaDB 10.6+ · `utf8mb4`\n\n";

        foreach (self::GRUPOS as $grupo => $nombres) {
            $md .= "## {$grupo}\n\n";
            foreach ($nombres as $nombre) {
                if (! isset($tablas[$nombre])) {
                    continue;
                }
                $t = $tablas[$nombre];
                $md .= "### `{$t->nombre}`\n\n{$t->comentario}\n\n";
                $md .= "| Campo | Tipo | Nulo | Clave | Por defecto | Descripción |\n|---|---|---|---|---|---|\n";
                foreach ($t->columnas as $c) {
                    $clave = match ($c->clave) {
                        'PRI' => 'PK',
                        'UNI' => 'Única',
                        default => '',
                    };
                    if (isset($t->fks[$c->campo])) {
                        $fk = $t->fks[$c->campo];
                        $clave = trim($clave.' FK → '.$fk->ref_tabla);
                    }
                    $descripcion = $c->comentario;
                    if (preg_match('/(STORED|VIRTUAL) GENERATED/', (string) $c->extra) && ! str_starts_with($descripcion, 'Calculada')) {
                        $descripcion = trim($descripcion.' (columna calculada)');
                    }
                    $defecto = $c->defecto === null ? '' : '`'.str_replace('|', '\|', $c->defecto).'`';
                    $md .= "| `{$c->campo}` | {$c->tipo} | ".($c->nulo === 'YES' ? 'Sí' : 'No')." | {$clave} | {$defecto} | ".str_replace('|', '\|', $descripcion)." |\n";
                }
                if ($t->checks->isNotEmpty()) {
                    $md .= "\n**Restricciones:**\n\n";
                    foreach ($t->checks->sortBy('nombre') as $ck) {
                        $md .= "- `{$ck->nombre}`: `".$this->condicion($ck->condicion)."`\n";
                    }
                }
                $md .= "\n";
            }
        }

        return $md;
    }

    private function condicion(string $sql): string
    {
        $sql = str_replace(['`', "\'", '_utf8mb4'], ['', "'", ''], $sql);

        return trim(preg_replace('/\s+/', ' ', $sql));
    }

    private function diagrama(Collection $tablas): string
    {
        $md = "# Diagrama entidad-relación de AYNI\n\n";
        $md .= "Generado desde la base de datos con `php artisan ayni:documentar-bd`. GitHub y VS Code muestran el diagrama directamente.\n\n";

        foreach (self::GRUPOS as $grupo => $nombres) {
            $incluidas = array_values(array_filter($nombres, fn ($n) => isset($tablas[$n])));
            $md .= "## {$grupo}\n\n```mermaid\nerDiagram\n";
            $relacionadas = [];
            foreach ($incluidas as $nombre) {
                foreach ($tablas[$nombre]->fks as $fk) {
                    $relacionadas[$fk->ref_tabla] = true;
                    $md .= "    {$fk->ref_tabla} ||--o{ {$nombre} : \"{$fk->campo}\"\n";
                }
            }
            foreach (array_unique(array_merge($incluidas, array_keys($relacionadas))) as $nombre) {
                $t = $tablas[$nombre];
                $md .= "    {$nombre} {\n";
                $propia = in_array($nombre, $incluidas, true);
                foreach ($t->columnas as $c) {
                    $esPk = $c->clave === 'PRI';
                    $esFk = isset($t->fks[$c->campo]);
                    if (! $propia && ! $esPk) {
                        continue;
                    }
                    $tipo = strtolower(preg_split('/[\s(]/', $c->tipo)[0]);
                    $marca = $esPk ? ' PK' : ($esFk ? ' FK' : ($c->clave === 'UNI' ? ' UK' : ''));
                    $md .= "        {$tipo} {$c->campo}{$marca}\n";
                }
                $md .= "    }\n";
            }
            $md .= "```\n\n";
        }

        return $md;
    }
}
