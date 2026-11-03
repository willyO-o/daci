<?php

namespace Database\Seeders;

use App\Support\Casos\MigradorCasos;
use Illuminate\Database\Seeder;

/**
 * Casos, FASE 2 (D-037): registra cada caso/CUD escrito como texto en la tabla `caso` y pone su id en `id_caso`, tabla
 * por tabla. Seguro en producción: sólo escribe `id_caso` donde está nulo, verifica cada tabla y la deshace si algo no
 * cuadra. Se puede correr cuantas veces se quiera. Detalle en {@see MigradorCasos}.
 *
 *     php artisan casos:migrar --simular                  # ver qué haría, sin guardar
 *     php artisan casos:migrar --tabla=telefono           # una sola tabla (fase por fase)
 *     php artisan db:seed --class=CasosSeeder --force     # todas las tablas
 */
class CasosSeeder extends Seeder
{
    /** Los usa `casos:migrar`. */
    public bool $simular = false;

    public ?string $tabla = null;

    public function run(MigradorCasos $migrador): void
    {
        $resultado = $migrador->migrar($this->simular, $this->tabla);

        if (! $this->command) {
            return;
        }

        $consola = $this->command;

        if ($this->simular) {
            $consola->warn('SIMULACIÓN: no se guardó nada.');
        }

        $consola->table(
            ['Tabla.columna', 'Filas', 'Sin caso (nulo/vacío)', 'Relleno (id nulo)', 'Ya tenían id_caso', 'Asignadas ahora', 'Casos nuevos', 'No coinciden'],
            collect($resultado)->map(fn (array $r, string $tabla) => [
                "{$tabla}.{$r['columna']}", $r['filas'], $r['sin_caso'], $r['relleno'], $r['ya_tenian'], $r['asignadas'], $r['casos_creados'], $r['no_coinciden'],
            ])->values()->all(),
        );

        foreach ($resultado as $tabla => $r) {
            if ($r['omitidos'] !== []) {
                $consola->warn("{$tabla}: textos tomados como «sin caso» (quedan en su columna, id_caso nulo):");
                foreach ($r['omitidos'] as $valor => $cantidad) {
                    $consola->line("  · «{$valor}» × {$cantidad}");
                }
            }
        }

        if (collect($resultado)->sum('no_coinciden') > 0) {
            $consola->warn('«No coinciden»: filas cuyo id_caso no es el caso de su texto (lo cambió un usuario). No se tocan.');
        }

        $consola->info('Verificado por tabla: sólo cambió id_caso y cada fila con caso tiene el suyo.');
    }
}
