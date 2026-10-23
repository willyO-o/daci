<?php

namespace Tests\Feature\Mandamientos;

use App\Models\AuditarConsultas;
use App\Models\Delito;
use App\Models\Juzgado;
use App\Models\Mandamiento;
use App\Models\Multimedia;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\TipoMandamiento;
use App\Models\User;
use App\Support\Acceso\CatalogoPermisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MandamientosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function datosValidos(array $cambios = []): array
    {
        return [
            'hoja_ruta' => 'HR-100',
            'estado' => 'PENDIENTE',
            'tipo_documento' => 'FOTOCOPIA',
            'id_persona' => Persona::factory()->create()->id,
            'id_juzgado' => Juzgado::create(['nombre_juzgado' => 'JUZGADO 1', 'estado_juzgado' => 'ACTIVO'])->id,
            'id_delito' => Delito::create(['nombre_delito' => 'ROBO', 'estado_delito' => 'ACTIVO'])->id,
            'id_tipo_mandamiento' => TipoMandamiento::create(['tipo_mandamiento' => 'MANDAMIENTO DE APREHENSION', 'estado_tipo_mandamiento' => 'ACTIVO'])->id,
            ...$cambios,
        ];
    }

    public function test_el_listado_devuelve_tarjetas_paginadas_en_json(): void
    {
        $this->actingAs($this->usuarioConPermisos(['mandamientos.listar']));
        Mandamiento::create($this->datosValidos());

        $this->getJson(route('mandamientos.index', ['size' => 6]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('datos.0.hoja_ruta', 'HR-100')
            ->assertJsonPath('datos.0.nombre_delito', 'ROBO');
    }

    public function test_registra_con_el_pdf_del_mandamiento_en_su_carpeta(): void
    {
        Storage::fake('public');
        $usuario = $this->usuarioConPermisos(['mandamientos.crear']);

        $this->actingAs($usuario)
            ->post(route('mandamientos.store'), $this->datosValidos([
                'imagen_mandamiento' => UploadedFile::fake()->create('orden.pdf', 120, 'application/pdf'),
            ]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', 'Mandamiento guardado correctamente.');

        $mandamiento = Mandamiento::firstOrFail();
        $this->assertSame($usuario->id, $mandamiento->id_usuario);
        $archivo = Multimedia::where('id_mandamiento', $mandamiento->id)->firstOrFail();
        $this->assertSame('mandamiento', $archivo->tipo);
        $this->assertStringStartsWith('mandamientos-pdf/', $archivo->ruta);
        Storage::disk('public')->assertExists($archivo->ruta);
    }

    public function test_ejecutado_exige_quien_lo_ejecuto(): void
    {
        $this->actingAs($this->usuarioConPermisos(['mandamientos.crear']))
            ->postJson(route('mandamientos.store'), $this->datosValidos(['estado' => 'EJECUTADO']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ejecutado_por' => 'Indique quién ejecutó el mandamiento.']);
    }

    public function test_el_tecnico_felcc_registra_pero_no_ve_el_listado(): void
    {
        $this->artisan('acceso:sincronizar');
        $tecnico = User::factory()->create();
        $tecnico->assignRole(Rol::findByName(CatalogoPermisos::ROL_TECNICO_FELCC));
        // El rol base se siembra sin permisos en una base vacía: se le dan los que la migración le asigna.
        $tecnico->roles->first()->syncPermissions(['mandamientos.crear', 'mandamientos.editar', 'mandamientos.ver', 'consultas.mandamientos', 'personas.crear', 'personas.ver']);

        $this->actingAs($tecnico)->get(route('mandamientos.create'))->assertOk();
        $this->actingAs($tecnico)->get(route('mandamientos.index'))->assertForbidden();
    }

    public function test_la_consulta_exige_filtro_y_cuatro_caracteres(): void
    {
        $this->actingAs($this->usuarioConPermisos(['consultas.mandamientos']));
        Mandamiento::create($this->datosValidos());

        $this->getJson(route('consultas.mandamientos', ['tipo_filtro' => 'hoja_ruta', 'search' => 'HR']))
            ->assertJsonPath('total', 0);
        $this->getJson(route('consultas.mandamientos', ['search' => 'HR-100']))
            ->assertJsonPath('total', 0);
    }

    public function test_una_consulta_nueva_queda_auditada_con_los_detalles_abiertos(): void
    {
        $usuario = $this->usuarioConPermisos(['consultas.mandamientos', 'mandamientos.ver']);
        $this->actingAs($usuario);
        $mandamiento = Mandamiento::create($this->datosValidos());

        $this->getJson(route('consultas.mandamientos', [
            'tipo_filtro' => 'hoja_ruta', 'search' => 'HR-100', 'identificador' => 'consulta-1', 'nuevo_filtro' => 1,
        ]))->assertJsonPath('total', 1);

        $this->getJson(route('mandamientos.show', ['mandamiento' => $mandamiento->id, 'identificador' => 'consulta-1']))->assertOk();

        $auditoria = AuditarConsultas::where('identificador', 'consulta-1')->firstOrFail();
        $this->assertSame('consulta_mandamientos', $auditoria->modulo);
        $this->assertSame(1, $auditoria->cantidad_resultados);
        $this->assertSame($mandamiento->id, $auditoria->ids_accedidos[0]['id']);
    }

    public function test_eliminar_borra_el_mandamiento_definitivamente(): void
    {
        $this->actingAs($this->usuarioConPermisos(['mandamientos.eliminar']));
        $mandamiento = Mandamiento::create($this->datosValidos());

        $this->deleteJson(route('mandamientos.destroy', $mandamiento))->assertOk();

        $this->assertDatabaseMissing('mandamiento', ['id' => $mandamiento->id]);
    }

    public function test_sin_permiso_de_eliminar_no_borra(): void
    {
        $this->actingAs($this->usuarioConPermisos(['mandamientos.listar', 'mandamientos.editar']));
        $mandamiento = Mandamiento::create($this->datosValidos());

        $this->deleteJson(route('mandamientos.destroy', $mandamiento))->assertForbidden();
    }
}
