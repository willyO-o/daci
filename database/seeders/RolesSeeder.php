<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Support\Acceso\CatalogoPermisos;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea los roles predefinidos de DACI ({@see CatalogoPermisos::rolesPredefinidos()}), hoy `pol_110`. Se puede correr
 * cuantas veces se quiera, también en producción: sólo crea el rol que FALTE, con sus permisos. Un rol que ya existe no
 * se toca (lo que se le dé o quite en la pantalla de roles se respeta). Nunca borra roles, permisos ni usuarios.
 *
 *     php artisan db:seed --class=RolesSeeder --force
 */
class RolesSeeder extends Seeder
{
    public function run(PermissionRegistrar $registrar): void
    {
        // Los permisos tienen que existir antes de asignarlos.
        $this->call(PermisosSeeder::class);

        $definiciones = CatalogoPermisos::rolesPredefinidos();

        foreach ($definiciones as $nombre => $definicion) {
            $desconocidos = array_diff($definicion['permisos'], CatalogoPermisos::permisos());

            if ($desconocidos !== []) {
                throw new \RuntimeException("El rol {$nombre} pide permisos que no están en el catálogo: ".implode(', ', $desconocidos));
            }
        }

        $registrar->forgetCachedPermissions();

        $creados = DB::transaction(function () use ($definiciones) {
            $creados = [];

            foreach ($definiciones as $nombre => $definicion) {
                if (Rol::query()->where('name', $nombre)->where('guard_name', CatalogoPermisos::GUARD)->exists()) {
                    continue;
                }

                Rol::create([
                    'name' => $nombre,
                    'guard_name' => CatalogoPermisos::GUARD,
                    'descripcion' => $definicion['descripcion'],
                ])->givePermissionTo($definicion['permisos']);

                $creados[] = $nombre;
            }

            return $creados;
        });

        $registrar->forgetCachedPermissions();

        foreach (array_keys($definiciones) as $nombre) {
            $this->command?->info(in_array($nombre, $creados, true)
                ? "Rol {$nombre} creado con: ".implode(', ', $definiciones[$nombre]['permisos'])
                : "Rol {$nombre}: ya existía, no se modificó.");
        }
    }
}
