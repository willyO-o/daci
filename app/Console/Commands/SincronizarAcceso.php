<?php

namespace App\Console\Commands;

use App\Models\Rol;
use App\Models\User;
use App\Support\Acceso\CatalogoPermisos;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pone la base al día con {@see CatalogoPermisos}: crea los permisos que falten, crea los roles base que no existan
 * le da al superadmin todos los permisos y deja `users.role_id` en espejo del rol de Spatie. Es idempotente y NUNCA
 * quita permisos a un rol: lo que el administrador configuró en la pantalla de roles se respeta.
 */
#[Signature('acceso:sincronizar')]
#[Description('Crea los permisos nuevos del catálogo y se los asigna al rol superadmin')]
class SincronizarAcceso extends Command
{
    public function handle(PermissionRegistrar $registrar): int
    {
        $registrar->forgetCachedPermissions();

        $creados = 0;

        foreach (CatalogoPermisos::permisos() as $nombre) {
            $permiso = Permission::findOrCreate($nombre, CatalogoPermisos::GUARD);
            $creados += $permiso->wasRecentlyCreated ? 1 : 0;
        }

        foreach (CatalogoPermisos::rolesBase() as $nombreRol) {
            Rol::findOrCreate($nombreRol, CatalogoPermisos::GUARD);
        }

        Rol::findByName(CatalogoPermisos::ROL_SUPERADMIN, CatalogoPermisos::GUARD)
            ->givePermissionTo(CatalogoPermisos::permisos());

        $registrar->forgetCachedPermissions();

        $corregidos = $this->sincronizarRolLegado();

        $this->components->info("Acceso sincronizado: {$creados} permiso(s) nuevo(s), {$corregidos} usuario(s) con role_id corregido.");

        return self::SUCCESS;
    }

    /**
     * Deja `users.role_id` en espejo del rol de Spatie (id del mismo rol en `roles_legado`, o null si es un rol
     * creado en DACI). Incluye usuarios eliminados. Un usuario SIN rol en Spatie no se toca: su `role_id` es el
     * único dato de rol que tiene.
     */
    private function sincronizarRolLegado(): int
    {
        if (! Schema::hasTable('roles_legado')) {
            return 0;
        }

        $idLegadoPorNombre = DB::table('roles_legado')->pluck('id', 'nombre');
        $corregidos = 0;

        DB::table('users')
            ->join('model_has_roles', function ($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', (new User)->getMorphClass());
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->orderBy('users.id')
            ->get(['users.id', 'users.role_id', 'roles.name'])
            ->each(function (object $fila) use ($idLegadoPorNombre, &$corregidos) {
                $esperado = isset($idLegadoPorNombre[$fila->name]) ? (int) $idLegadoPorNombre[$fila->name] : null;
                $actual = $fila->role_id === null ? null : (int) $fila->role_id;

                if ($actual === $esperado) {
                    return;
                }

                DB::table('users')->where('id', $fila->id)->update(['role_id' => $esperado]);
                $corregidos++;
            });

        return $corregidos;
    }
}
