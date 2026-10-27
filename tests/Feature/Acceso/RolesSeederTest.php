<?php

namespace Tests\Feature\Acceso;

use App\Models\Delito;
use App\Models\Juzgado;
use App\Models\Mandamiento;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\TipoMandamiento;
use App\Models\User;
use App\Support\Acceso\CatalogoPermisos;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Rol predefinido `pol_110`: consulta mandamientos y registra personas, teléfonos, IMEIs y vehículos desde las
 * páginas «Registrar …», sin ver ningún listado.
 */
class RolesSeederTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISOS_POL_110 = [
        'consultas.mandamientos', 'mandamientos.ver', 'personas.crear', 'telefonos.crear', 'imeis.crear', 'vehiculos.crear',
    ];

    private function usuarioPol110(): User
    {
        $this->seed(RolesSeeder::class);
        $usuario = User::factory()->create();
        $usuario->asignarRol(Rol::findByName('pol_110'));

        return $usuario;
    }

    public function test_crea_el_rol_con_exactamente_sus_permisos_y_se_puede_repetir(): void
    {
        $this->seed(RolesSeeder::class);
        $tecnico = Rol::findByName(CatalogoPermisos::ROL_TECNICO_DACI)->permissions()->pluck('name')->sort()->values()->all();

        $this->seed(RolesSeeder::class);

        $rol = Rol::findByName('pol_110');
        $this->assertEqualsCanonicalizing(self::PERMISOS_POL_110, $rol->permissions()->pluck('name')->all());
        $this->assertSame(1, Rol::where('name', 'pol_110')->count());
        $this->assertNotEmpty($rol->descripcion);
        // Los demás roles no cambian.
        $this->assertSame($tecnico, Rol::findByName(CatalogoPermisos::ROL_TECNICO_DACI)->permissions()->pluck('name')->sort()->values()->all());
    }

    public function test_un_rol_existente_no_se_modifica_al_volver_a_sembrar(): void
    {
        $this->seed(RolesSeeder::class);
        $rol = Rol::findByName('pol_110');
        $rol->revokePermissionTo('imeis.crear');
        $rol->givePermissionTo('telefonos.listar');

        $this->seed(RolesSeeder::class);

        $permisos = $rol->fresh()->permissions()->pluck('name')->all();
        $this->assertNotContains('imeis.crear', $permisos);
        $this->assertContains('telefonos.listar', $permisos);
    }

    public function test_consulta_mandamientos_y_registra_sin_ver_los_listados(): void
    {
        $this->actingAs($this->usuarioPol110());

        foreach (['consultas.mandamientos', 'personas.create', 'telefonos.create', 'imeis.create', 'vehiculos.create'] as $ruta) {
            $this->get(route($ruta))->assertOk();
        }

        foreach (['mandamientos.index', 'mandamientos.create', 'personas.index', 'telefonos.index', 'imeis.index', 'vehiculos.index',
            'registro-criminal.index', 'faltas-contravenciones.index', 'consultas.registro-criminal', 'usuarios.index'] as $ruta) {
            $this->get(route($ruta))->assertForbidden();
        }

        // El detalle de un mandamiento se abre desde la consulta.
        $mandamiento = Mandamiento::create([
            'estado' => 'POR EJECUTAR',
            'id_persona' => Persona::factory()->create()->id,
            'id_juzgado' => Juzgado::create(['nombre_juzgado' => 'J1', 'estado_juzgado' => 'ACTIVO'])->id,
            'id_delito' => Delito::create(['nombre_delito' => 'ROBO', 'estado_delito' => 'ACTIVO'])->id,
            'id_tipo_mandamiento' => TipoMandamiento::create(['tipo_mandamiento' => 'APREHENSION', 'estado_tipo_mandamiento' => 'ACTIVO'])->id,
        ]);
        $this->getJson(route('mandamientos.show', $mandamiento->id))->assertOk();
        $this->putJson(route('mandamientos.update', $mandamiento->id), [])->assertForbidden();
    }

    public function test_guarda_desde_las_paginas_de_registro(): void
    {
        $this->actingAs($this->usuarioPol110());

        $this->from(route('telefonos.create'))
            ->post(route('telefonos.store'), ['numero_celular' => '71112233'])
            ->assertRedirect(route('telefonos.create'))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('telefono', ['numero_celular' => '71112233']);

        $this->getJson(route('imeis.telefonos-search', ['q' => '7111']))->assertOk();

        $this->get(route('dashboard'))->assertInertia(fn (Assert $pagina) => $pagina
            ->where('auth.can', fn ($can) => collect($can)->has('personas.crear') && ! collect($can)->has('personas.listar')));
    }

    public function test_el_rol_no_queda_espejado_en_roles_legado(): void
    {
        $usuario = $this->usuarioPol110();

        // Es un rol nuevo de DACI: no existe en la tabla de roles del sistema anterior.
        $this->assertNull(DB::table('users')->where('id', $usuario->id)->value('role_id'));
        $this->assertSame('pol_110', $usuario->fresh()->nombreRol());
    }

    public function test_conciliacion_ciudadana_se_crea_con_exactamente_sus_permisos(): void
    {
        $this->seed(RolesSeeder::class);

        $this->assertEqualsCanonicalizing([
            'consultas.mandamientos', 'mandamientos.ver',
            'faltas_contravenciones.listar', 'faltas_contravenciones.ver', 'faltas_contravenciones.crear',
            'faltas_contravenciones.editar', 'faltas_contravenciones.ficha', 'consultas.faltas_contravenciones',
        ], Rol::findByName('conciliacion_ciudadana')->permissions()->pluck('name')->all());
        $this->assertSame('Conciliación Ciudadana', Rol::findByName('conciliacion_ciudadana')->descripcion);
    }

    public function test_faltas_y_contravenciones_es_solo_de_administradores_y_conciliacion_ciudadana(): void
    {
        $this->seed(RolesSeeder::class);

        $conModulo = Rol::query()
            ->whereHas('permissions', fn ($q) => $q->where('name', 'like', '%faltas_contravenciones%'))
            ->pluck('name')
            ->all();

        $this->assertEqualsCanonicalizing([CatalogoPermisos::ROL_SUPERADMIN, CatalogoPermisos::ROL_ADMINISTRADOR, 'conciliacion_ciudadana'], $conModulo);
    }

    public function test_conciliacion_ciudadana_trabaja_faltas_y_contravenciones_y_consulta_mandamientos(): void
    {
        $this->seed(RolesSeeder::class);
        $usuario = User::factory()->create();
        $usuario->asignarRol(Rol::findByName('conciliacion_ciudadana'));
        $this->actingAs($usuario);

        foreach (['faltas-contravenciones.index', 'faltas-contravenciones.create', 'consultas.faltas-contravenciones', 'consultas.mandamientos'] as $ruta) {
            $this->get(route($ruta))->assertOk();
        }

        foreach (['registro-criminal.index', 'mandamientos.index', 'personas.index', 'telefonos.index', 'vehiculos.index', 'usuarios.index'] as $ruta) {
            $this->get(route($ruta))->assertForbidden();
        }

        $this->deleteJson(route('faltas-contravenciones.destroy', 1))->assertForbidden();
    }
}
