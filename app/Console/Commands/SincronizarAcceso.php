<?php

namespace App\Console\Commands;

use App\Models\Rol;
use App\Support\Acceso\CatalogoPermisos;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pone la base al día con {@see CatalogoPermisos}: crea los permisos que falten, crea los roles base que no existan
 * y le da al superadmin todos los permisos. Es idempotente y NUNCA quita permisos a un rol: lo que el
 * administrador configuró en la pantalla de roles se respeta.
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

        $this->components->info("Acceso sincronizado: {$creados} permiso(s) nuevo(s).");

        return self::SUCCESS;
    }
}
