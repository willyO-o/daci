<?php

namespace Tests\Feature\Acceso;

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * La migración de roles debe dejar a cada rol del sistema anterior con el mismo acceso que tenía y a cada usuario con
 * su rol, y su `down()` debe devolver la base a como estaba.
 */
class MigracionRolesTest extends TestCase
{
    private Migration $migracion;

    protected function setUp(): void
    {
        parent::setUp();

        // Renombra y crea tablas: en MariaDB eso confirma cualquier transacción, así que esta prueba no usa
        // RefreshDatabase; reconstruye la base de pruebas (`migrate:fresh` no ejecuta los down() heredados, que no
        // son reversibles por sus claves foráneas).
        $this->artisan('migrate:fresh', ['--force' => true]);

        $this->migracion = require database_path('migrations/2026_10_22_120000_migrar_roles_a_spatie_permission.php');

        // Volver al estado del sistema anterior: tabla `roles` (nombre, descripcion) y users.role_id.
        $this->migracion->down();
    }

    public function test_cada_rol_anterior_conserva_su_acceso_y_cada_usuario_su_rol(): void
    {
        $idTecnicoDaci = DB::table('roles')->insertGetId(['nombre' => 'tecnico_daci', 'descripcion' => 'Técnico DACI']);
        $idConsultorFelcc = DB::table('roles')->insertGetId(['nombre' => 'consultor_felcc', 'descripcion' => null]);

        $tecnico = User::factory()->create();
        $consultor = User::factory()->create();
        DB::table('users')->where('id', $tecnico->id)->update(['role_id' => $idTecnicoDaci]);
        DB::table('users')->where('id', $consultor->id)->update(['role_id' => $idConsultorFelcc]);

        $this->migracion->up();

        $this->assertTrue(Schema::hasTable('roles_legado'));
        $this->assertDatabaseHas('roles', ['id' => $idTecnicoDaci, 'name' => 'tecnico_daci', 'descripcion' => 'Técnico DACI']);

        $tecnico = $tecnico->fresh();
        $this->assertSame('tecnico_daci', $tecnico->nombreRol());
        // `tecnico_daci` tenía personas_crear/listar/editar pero no personas_eliminar.
        $this->assertTrue($tecnico->can('personas.crear'));
        $this->assertTrue($tecnico->can('personas.editar'));
        $this->assertFalse($tecnico->can('personas.eliminar'));
        // Botón «Imprimir ficha» e importaciones dependían del nombre del rol.
        $this->assertTrue($tecnico->can('registro_criminal.ficha'));
        $this->assertTrue($tecnico->can('importar.vehiculos'));
        $this->assertFalse($tecnico->can('reportes.exportar'));

        $consultor = $consultor->fresh();
        $this->assertSame('consultor_felcc', $consultor->nombreRol());
        $this->assertTrue($consultor->can('consultas.mandamientos'));
        $this->assertTrue($consultor->can('mandamientos.ver'));
        $this->assertFalse($consultor->can('mandamientos.listar'));
    }

    public function test_se_detiene_sin_cambios_si_ya_existe_una_tabla_de_spatie(): void
    {
        Schema::create('permissions', fn ($tabla) => $tabla->id());
        DB::table('permissions')->insert(['id' => 99]);

        $error = null;

        try {
            $this->migracion->up();
        } catch (\RuntimeException $capturado) {
            $error = $capturado;
        }

        $this->assertNotNull($error, 'La migración debía detenerse.');
        $this->assertStringContainsString('permissions', $error->getMessage());
        $this->assertTrue(Schema::hasColumn('roles', 'nombre'));
        $this->assertFalse(Schema::hasTable('roles_legado'));
        $this->assertSame(1, DB::table('permissions')->count());

        Schema::drop('permissions');
    }

    public function test_un_fallo_a_mitad_deja_la_base_como_estaba(): void
    {
        // Un rol escrito con otras mayúsculas choca con el rol base en el índice único de Spatie (la colación no
        // distingue mayúsculas): el error llega después de crear tablas e insertar datos.
        $idRol = DB::table('roles')->insertGetId(['nombre' => 'Tecnico_DACI']);
        $usuario = User::factory()->create();
        DB::table('users')->where('id', $usuario->id)->update(['role_id' => $idRol]);

        $error = null;

        try {
            $this->migracion->up();
        } catch (QueryException $capturado) {
            $error = $capturado;
        }

        $this->assertNotNull($error, 'La migración debía fallar.');
        $this->assertTrue(Schema::hasColumn('roles', 'nombre'));
        $this->assertFalse(Schema::hasTable('roles_legado'));
        $this->assertFalse(Schema::hasTable('permissions'));
        $this->assertSame(1, DB::table('users')->where('role_id', $idRol)->count());

        // Quitar el rol conflictivo para que tearDown deje el esquema migrado.
        DB::table('users')->where('id', $usuario->id)->update(['role_id' => null]);
        DB::table('roles')->where('id', $idRol)->delete();
    }

    public function test_down_devuelve_la_tabla_de_roles_anterior(): void
    {
        $this->migracion->up();
        $this->migracion->down();

        $this->assertTrue(Schema::hasColumn('roles', 'nombre'));
        $this->assertFalse(Schema::hasTable('roles_legado'));
        $this->assertFalse(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasColumn('users', 'role_id'));
    }

    protected function tearDown(): void
    {
        // Dejar el esquema migrado para el resto de la suite.
        if (! Schema::hasTable('permissions')) {
            $this->migracion->up();
        }

        parent::tearDown();
    }
}
