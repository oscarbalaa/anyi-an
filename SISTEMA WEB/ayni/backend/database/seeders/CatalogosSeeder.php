<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Datos fijos que el sistema necesita para funcionar (también en el hosting). */
class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('rol')->insert([
            ['codigo' => 'ADMIN', 'nombre' => 'Administrador'],
            ['codigo' => 'DOCENTE', 'nombre' => 'Docente'],
            ['codigo' => 'ESTUDIANTE', 'nombre' => 'Estudiante'],
        ]);

        DB::table('institucion')->insert([
            'nombre' => 'IEP El Señor es mi Pastor',
            'ugel' => 'UGEL Santa',
            'direccion' => 'Nuevo Chimbote, Áncash',
        ]);

        DB::table('escala_literal')->insert([
            ['codigo' => 'AD', 'descripcion' => 'Logro destacado', 'nota_minima' => 18, 'nota_maxima' => 20, 'orden' => 1],
            ['codigo' => 'A', 'descripcion' => 'Logro esperado', 'nota_minima' => 14, 'nota_maxima' => 17.99, 'orden' => 2],
            ['codigo' => 'B', 'descripcion' => 'En proceso', 'nota_minima' => 11, 'nota_maxima' => 13.99, 'orden' => 3],
            ['codigo' => 'C', 'descripcion' => 'En inicio', 'nota_minima' => 0, 'nota_maxima' => 10.99, 'orden' => 4],
        ]);

        $ordinales = ['Primero', 'Segundo', 'Tercero', 'Cuarto', 'Quinto', 'Sexto'];
        $grados = [];
        foreach ($ordinales as $i => $nombre) {
            $grados[] = ['nivel' => 'PRIMARIA', 'numero' => $i + 1, 'nombre' => $nombre, 'orden' => $i + 1];
        }
        foreach (array_slice($ordinales, 0, 5) as $i => $nombre) {
            $grados[] = ['nivel' => 'SECUNDARIA', 'numero' => $i + 1, 'nombre' => $nombre, 'orden' => $i + 7];
        }
        DB::table('grado')->insert($grados);

        DB::table('seccion')->insert(array_map(fn ($s) => ['nombre' => $s], ['Única', 'A', 'B', 'C']));

        DB::table('tipo_evaluacion')->insert(array_map(
            fn ($t) => ['nombre' => $t],
            ['Práctica', 'Proyecto', 'Examen', 'Semanal', 'Parcial'],
        ));

        DB::table('tipo_incidencia')->insert([
            ['nombre' => 'Conducta', 'descripcion' => 'Faltas de disciplina o conducta en aula'],
            ['nombre' => 'Académica', 'descripcion' => 'Incumplimiento de tareas o trabajos'],
            ['nombre' => 'Puntualidad', 'descripcion' => 'Tardanzas reiteradas'],
            ['nombre' => 'Salud', 'descripcion' => 'Situaciones de salud del estudiante'],
            ['nombre' => 'Familiar', 'descripcion' => 'Situaciones familiares relevantes'],
        ]);

        DB::table('modelo_ml')->insert([
            'nombre' => 'reglas',
            'version' => 'v0',
            'algoritmo' => 'Reglas por umbrales (Unidad 1)',
            'activo' => true,
        ]);
    }
}
