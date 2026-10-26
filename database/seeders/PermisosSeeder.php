<?php

namespace Database\Seeders;

use App\Support\Acceso\CatalogoPermisos;
use App\Support\Acceso\SincronizadorAcceso;
use Illuminate\Database\Seeder;

/**
 * Actualiza los permisos con {@see CatalogoPermisos}. Se puede correr cuantas veces se quiera, también sobre la base
 * de producción: sólo AGREGA lo que falta (permisos, roles base, permisos del superadmin) y nunca borra ni quita
 * nada. Detalle en {@see SincronizadorAcceso}.
 *
 *     php artisan db:seed --class=PermisosSeeder --force
 */
class PermisosSeeder extends Seeder
{
    public function run(SincronizadorAcceso $sincronizador): void
    {
        $resultado = $sincronizador->sincronizar();

        if (! $this->command) {
            return;
        }

        $consola = $this->command;

        $consola->info(sprintf(
            'Permisos al día: %d en el catálogo, %d nuevo(s).',
            count(CatalogoPermisos::permisos()),
            count($resultado['permisos_creados']),
        ));

        foreach ($resultado['permisos_creados'] as $permiso) {
            $consola->line("  + permiso {$permiso}");
        }

        foreach ($resultado['roles_creados'] as $rol) {
            $consola->line("  + rol base {$rol}");
        }

        foreach ($resultado['asignaciones_iniciales'] as $rol => $permisos) {
            $consola->line("  + {$rol} recibe: ".implode(', ', $permisos));
        }

        if ($resultado['usuarios_corregidos'] > 0) {
            $consola->line("  · {$resultado['usuarios_corregidos']} usuario(s) con role_id corregido");
        }

        if ($resultado['obsoletos'] !== []) {
            $consola->warn('Permisos en la base que ya no están en el catálogo (no se borran): '.implode(', ', $resultado['obsoletos']));
        }
    }
}
