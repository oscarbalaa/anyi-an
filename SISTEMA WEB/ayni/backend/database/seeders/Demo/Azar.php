<?php

namespace Database\Seeders\Demo;

/** Azar reproducible (misma semilla = mismos datos) y nombres ficticios. */
final class Azar
{
    private const NOMBRES_M = [
        'Mateo', 'Santiago', 'Sebastián', 'Diego', 'Thiago', 'Gael', 'Adrián', 'Fabricio', 'Joaquín', 'Daniel',
        'Gabriel', 'Leonardo', 'Rodrigo', 'Franco', 'Alonso', 'Bruno', 'Iker', 'Emiliano', 'Ángel', 'Josué',
        'Nicolás', 'Matías', 'Renato', 'Piero', 'Valentino', 'Samuel', 'Elías', 'Jhon', 'Kevin', 'Luciano',
    ];

    private const NOMBRES_F = [
        'Valeria', 'Camila', 'Lucía', 'Ximena', 'Fernanda', 'Mía', 'Sofía', 'Valentina', 'Isabella', 'Daniela',
        'Antonella', 'Alessandra', 'Mariana', 'Luciana', 'Allison', 'Nicole', 'Abigail', 'Romina', 'Danna', 'Kiara',
        'Ariana', 'Fátima', 'Zoe', 'Renata', 'Ana Lucía', 'María José', 'Milagros', 'Brianna', 'Jimena', 'Rafaela',
    ];

    private const APELLIDOS = [
        'Quispe', 'Flores', 'Sánchez', 'Rodríguez', 'García', 'Rojas', 'Huamán', 'Mendoza', 'Ramírez', 'Torres',
        'Chávez', 'Vásquez', 'Castillo', 'Díaz', 'Mamani', 'Gutiérrez', 'Romero', 'Salazar', 'Cruz', 'Paredes',
        'Espinoza', 'Ruiz', 'Vargas', 'Herrera', 'Medina', 'Lozano', 'Aguilar', 'Campos', 'Mori', 'Vega',
        'Soto', 'Aranda', 'Huertas', 'Navarro', 'Ccahuana', 'Ticona', 'Villanueva', 'Chumpitaz', 'Alvarado', 'Benites',
        'Cárdenas', 'Ponce', 'León', 'Palacios', 'Zevallos', 'Bustamante', 'Carhuamaca', 'Llanos', 'Moreno', 'Saavedra',
    ];

    public function __construct(int $semilla)
    {
        mt_srand($semilla);
    }

    public function entero(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    public function probabilidad(float $p): bool
    {
        return mt_rand() / mt_getrandmax() < $p;
    }

    /** @template T @param list<T> $lista @return T */
    public function elegir(array $lista): mixed
    {
        return $lista[mt_rand(0, count($lista) - 1)];
    }

    public function mezclar(array $lista): array
    {
        for ($i = count($lista) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$lista[$i], $lista[$j]] = [$lista[$j], $lista[$i]];
        }

        return $lista;
    }

    /** Distribución normal aproximada (Box-Muller). */
    public function normal(float $media, float $desviacion): float
    {
        $u = max(mt_rand() / mt_getrandmax(), 1e-9);
        $v = mt_rand() / mt_getrandmax();

        return $media + $desviacion * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }

    public function nombre(string $sexo): string
    {
        return $this->elegir($sexo === 'F' ? self::NOMBRES_F : self::NOMBRES_M);
    }

    public function apellido(): string
    {
        return $this->elegir(self::APELLIDOS);
    }

    public function celular(): string
    {
        return '9'.str_pad((string) mt_rand(0, 99_999_999), 8, '0', STR_PAD_LEFT);
    }
}
