<?php

namespace Tests\Feature\Acceso;

use App\Models\Rol;
use App\Models\User;
use App\Support\Acceso\CatalogoPermisos;
use App\Support\Acceso\SincronizadorAcceso;
use Database\Seeders\PermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermisosSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_el_catalogo_completo_y_se_puede_repetir_sin_cambios(): void
    {
        $this->seed(PermisosSeeder::class);
        $this->seed(PermisosSeeder::class);

        $this->assertSame(count(CatalogoPermisos::permisos()), Permission::count());
        $this->assertSame(count(CatalogoPermisos::rolesBase()), Rol::count());
        $this->assertSame(
            count(CatalogoPermisos::permisos()),
            Rol::findByName(CatalogoPermisos::ROL_SUPERADMIN)->permissions()->count(),
        );
    }

    public function test_restituye_un_permiso_faltante_sin_tocar_roles_usuarios_ni_otros_datos(): void
    {
        $this->seed(PermisosSeeder::class);

        $tecnico = Rol::findByName(CatalogoPermisos::ROL_TECNICO_DACI);
        $tecnico->syncPermissions(['personas.listar', 'personas.ver']);
        $usuario = User::factory()->create();
        $usuario->assignRole($tecnico);
        $personas = DB::table('persona')->count();

        Permission::findByName('reportes.exportar')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seed(PermisosSeeder::class);

        $this->assertDatabaseHas('permissions', ['name' => 'reportes.exportar']);
        $this->assertTrue(Rol::findByName(CatalogoPermisos::ROL_SUPERADMIN)->hasPermissionTo('reportes.exportar'));
        // El resto queda igual: el técnico no gana permisos y el usuario conserva su rol.
        $this->assertEqualsCanonicalizing(['personas.listar', 'personas.ver'], $tecnico->fresh()->permissions->pluck('name')->all());
        $this->assertSame(CatalogoPermisos::ROL_TECNICO_DACI, $usuario->fresh()->nombreRol());
        $this->assertSame($personas, DB::table('persona')->count());
    }

    public function test_respeta_los_permisos_quitados_en_la_pantalla_de_roles(): void
    {
        $this->seed(PermisosSeeder::class);
        $administrador = Rol::findByName(CatalogoPermisos::ROL_ADMINISTRADOR);
        $administrador->syncPermissions(['usuarios.listar']);

        $this->seed(PermisosSeeder::class);

        $this->assertSame(['usuarios.listar'], $administrador->fresh()->permissions->pluck('name')->all());
    }

    public function test_un_permiso_nuevo_va_a_los_roles_declarados_solo_al_crearse(): void
    {
        $this->seed(PermisosSeeder::class);
        Permission::findByName('reportes.exportar')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $sincronizador = new SincronizadorAcceso(app(PermissionRegistrar::class), [
            'reportes.exportar' => [CatalogoPermisos::ROL_CONSULTOR_DACI, 'rol_inexistente'],
        ]);

        $resultado = $sincronizador->sincronizar();

        $this->assertSame(['reportes.exportar'], $resultado['permisos_creados']);
        $this->assertSame([CatalogoPermisos::ROL_CONSULTOR_DACI => ['reportes.exportar']], $resultado['asignaciones_iniciales']);
        $this->assertTrue(Rol::findByName(CatalogoPermisos::ROL_CONSULTOR_DACI)->hasPermissionTo('reportes.exportar'));
        $this->assertDatabaseMissing('roles', ['name' => 'rol_inexistente']);

        // Si después se lo quitan, una nueva corrida no se lo devuelve.
        Rol::findByName(CatalogoPermisos::ROL_CONSULTOR_DACI)->revokePermissionTo('reportes.exportar');
        $sincronizador->sincronizar();

        $this->assertFalse(Rol::findByName(CatalogoPermisos::ROL_CONSULTOR_DACI)->fresh()->hasPermissionTo('reportes.exportar'));
    }

    public function test_informa_los_permisos_obsoletos_sin_borrarlos(): void
    {
        $this->seed(PermisosSeeder::class);
        $viejo = Permission::create(['name' => 'modulo.retirado', 'guard_name' => CatalogoPermisos::GUARD]);
        Rol::findByName(CatalogoPermisos::ROL_ADMINISTRADOR)->givePermissionTo($viejo);

        $resultado = app(SincronizadorAcceso::class)->sincronizar();

        $this->assertSame(['modulo.retirado'], $resultado['obsoletos']);
        $this->assertDatabaseHas('permissions', ['name' => 'modulo.retirado']);
        $this->assertTrue(Rol::findByName(CatalogoPermisos::ROL_ADMINISTRADOR)->hasPermissionTo('modulo.retirado'));
    }

    public function test_el_comando_corre_el_seeder(): void
    {
        $this->artisan('acceso:sincronizar')->assertSuccessful();

        $this->assertSame(count(CatalogoPermisos::permisos()), Permission::count());
    }
}
