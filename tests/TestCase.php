<?php

namespace Tests;

use App\Models\Rol;
use App\Models\User;
use App\Support\Acceso\CatalogoPermisos;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Candado: las pruebas reconstruyen la base, así que jamás corren sobre la de producción (`felcc1`). Usan
     * `felcc_pruebas` (MariaDB, igual que producción: el código heredado usa CONCAT, JSON_OBJECT, MD5…).
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $base = config('database.connections.'.config('database.default').'.database');

        if ($base !== 'felcc_pruebas') {
            throw new \RuntimeException("Las pruebas sólo corren sobre felcc_pruebas (se intentó usar «{$base}»).");
        }
    }

    /**
     * Usuario activo con un rol de prueba que tiene exactamente estos permisos.
     *
     * @param  list<string>  $permisos
     */
    protected function usuarioConPermisos(array $permisos = [], array $atributos = []): User
    {
        $this->artisan('acceso:sincronizar');

        $rol = Rol::create(['name' => 'rol_prueba_'.uniqid(), 'guard_name' => CatalogoPermisos::GUARD]);
        $rol->syncPermissions($permisos);

        $usuario = User::factory()->create($atributos);
        $usuario->assignRole($rol);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $usuario;
    }

    protected function superadmin(): User
    {
        $this->artisan('acceso:sincronizar');

        $usuario = User::factory()->create();
        $usuario->assignRole(CatalogoPermisos::ROL_SUPERADMIN);

        return $usuario;
    }
}
