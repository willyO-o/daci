<?php

namespace Tests\Feature\Mandamientos;

use App\Models\Delito;
use App\Models\Juzgado;
use App\Models\Mandamiento;
use App\Models\Multimedia;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\TipoMandamiento;
use App\Support\Acceso\CatalogoPermisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Botón «Estado de ejecución» de Consultas › Mandamientos (permiso `mandamientos.estado`).
 */
class EstadoMandamientoTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISOS = ['consultas.mandamientos', 'mandamientos.estado'];

    private function mandamiento(array $cambios = []): Mandamiento
    {
        return Mandamiento::create([
            'hoja_ruta' => 'HR-200',
            'estado' => 'POR EJECUTAR',
            'tipo_documento' => 'FOTOCOPIA',
            'asignado' => 'SGTO. PEREZ',
            'id_persona' => Persona::factory()->create()->id,
            'id_juzgado' => Juzgado::create(['nombre_juzgado' => 'JUZGADO 1', 'estado_juzgado' => 'ACTIVO'])->id,
            'id_delito' => Delito::create(['nombre_delito' => 'ROBO', 'estado_delito' => 'ACTIVO'])->id,
            'id_tipo_mandamiento' => TipoMandamiento::create(['tipo_mandamiento' => 'APREHENSION', 'estado_tipo_mandamiento' => 'ACTIVO'])->id,
            ...$cambios,
        ]);
    }

    private function enviar(Mandamiento $mandamiento, array $datos)
    {
        return $this->post(
            route('consultas.mandamientos.estado', $mandamiento),
            ['_method' => 'PATCH', ...$datos],
            ['Accept' => 'application/json'],
        );
    }

    public function test_sin_permiso_no_cambia_el_estado(): void
    {
        $this->actingAs($this->usuarioConPermisos(['consultas.mandamientos', 'mandamientos.editar']));
        $mandamiento = $this->mandamiento();

        $this->enviar($mandamiento, ['estado' => 'PROFUGO'])->assertForbidden();

        $this->assertSame('POR EJECUTAR', $mandamiento->fresh()->estado);
    }

    public function test_otro_estado_solo_cambia_el_estado_y_conserva_lo_demas(): void
    {
        $this->actingAs($this->usuarioConPermisos(self::PERMISOS));
        $mandamiento = $this->mandamiento([
            'fecha_ejecucion' => '2025-01-10',
            'ejecutado_por' => 'CBO. ROJAS',
            'detalle_ejecucion' => 'EN SU DOMICILIO',
        ]);

        $this->enviar($mandamiento, [
            'estado' => ' se continua con la busqueda ',
            // Llegan aunque el estado no sea EJECUTADO: se ignoran.
            'ejecutado_por' => 'OTRO',
            'hoja_ruta' => 'HR-CAMBIADA',
        ])->assertOk()
            ->assertJsonPath('success', 'Estado actualizado correctamente.')
            ->assertJsonPath('datos.estado', 'SE CONTINUA CON LA BUSQUEDA');

        $mandamiento->refresh();
        $this->assertSame('SE CONTINUA CON LA BUSQUEDA', $mandamiento->estado);
        $this->assertSame('2025-01-10', $mandamiento->fecha_ejecucion->toDateString());
        $this->assertSame('CBO. ROJAS', $mandamiento->ejecutado_por);
        $this->assertSame('EN SU DOMICILIO', $mandamiento->detalle_ejecucion);
        $this->assertSame('HR-200', $mandamiento->hoja_ruta);
        $this->assertSame('SGTO. PEREZ', $mandamiento->asignado);
    }

    public function test_ejecutado_exige_fecha_ejecutor_y_acta(): void
    {
        $this->actingAs($this->usuarioConPermisos(self::PERMISOS));
        $mandamiento = $this->mandamiento();

        $this->enviar($mandamiento, ['estado' => 'ejecutado'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fecha_ejecucion' => 'Indique la fecha de ejecución.',
                'ejecutado_por' => 'Indique quién ejecutó el mandamiento.',
                'acta_ejecucion' => 'Suba el acta de ejecución.',
            ]);

        $this->enviar($mandamiento, [
            'estado' => 'EJECUTADO',
            'fecha_ejecucion' => now()->addDay()->toDateString(),
            'ejecutado_por' => 'X',
            'acta_ejecucion' => UploadedFile::fake()->create('acta.docx', 10),
        ])->assertJsonValidationErrors(['fecha_ejecucion', 'acta_ejecucion']);

        $this->assertSame('POR EJECUTAR', $mandamiento->fresh()->estado);
    }

    public function test_ejecutado_guarda_los_datos_y_el_acta_en_su_carpeta(): void
    {
        Storage::fake('public');
        $this->actingAs($this->usuarioConPermisos(self::PERMISOS));
        $mandamiento = $this->mandamiento();

        $this->enviar($mandamiento, [
            'estado' => 'EJECUTADO EN LA CIUDAD DE POTOSI',
            'fecha_ejecucion' => '2026-10-20',
            'ejecutado_por' => 'sgto. mamani',
            'detalle_ejecucion' => 'Aprehendido en su domicilio.',
            'acta_ejecucion' => UploadedFile::fake()->create('acta.pdf', 100, 'application/pdf'),
        ])->assertOk()->assertJsonPath('datos.acta_ejecucion.tipo_archivo', 'pdf');

        $mandamiento->refresh();
        $this->assertSame('EJECUTADO EN LA CIUDAD DE POTOSI', $mandamiento->estado);
        $this->assertSame('2026-10-20', $mandamiento->fecha_ejecucion->toDateString());
        $this->assertSame('SGTO. MAMANI', $mandamiento->ejecutado_por);
        $this->assertSame('Aprehendido en su domicilio.', $mandamiento->detalle_ejecucion);

        $acta = Multimedia::where('id_mandamiento', $mandamiento->id)->where('tipo', 'acta_ejecucion')->firstOrFail();
        $this->assertStringStartsWith('mandamientos-pdf-actas/', $acta->ruta);
        Storage::disk('public')->assertExists($acta->ruta);
    }

    public function test_con_acta_previa_no_exige_otra_y_la_conserva(): void
    {
        Storage::fake('public');
        $this->actingAs($this->usuarioConPermisos(self::PERMISOS));
        $mandamiento = $this->mandamiento();
        Multimedia::create([
            'tipo' => 'acta_ejecucion', 'ruta' => 'mandamientos-img-actas/previa.jpg', 'nombre_archivo' => 'previa.jpg',
            'id_mandamiento' => $mandamiento->id, 'tipo_archivo' => 'jpg',
        ]);

        $this->enviar($mandamiento, [
            'estado' => 'EJECUTADO',
            'fecha_ejecucion' => '2026-10-01',
            'ejecutado_por' => 'CBO. ROJAS',
        ])->assertOk();

        $this->assertSame('EJECUTADO', $mandamiento->fresh()->estado);
        $this->assertSame(1, Multimedia::where('id_mandamiento', $mandamiento->id)->count());
    }

    public function test_el_boton_solo_recibe_sugerencias_con_el_permiso(): void
    {
        $this->actingAs($this->usuarioConPermisos(self::PERMISOS));
        $this->mandamiento(['estado' => 'PROFUGO']);

        $this->get(route('consultas.mandamientos'))
            ->assertInertia(fn (Assert $pagina) => $pagina->component('Consultas/Mandamientos')->where('estados', ['PROFUGO']));

        $this->actingAs($this->usuarioConPermisos(['consultas.mandamientos']))
            ->get(route('consultas.mandamientos'))
            ->assertInertia(fn (Assert $pagina) => $pagina->where('estados', []));
    }

    public function test_los_roles_que_editaban_reciben_el_permiso_nuevo(): void
    {
        // Base con los permisos ya sembrados y sin `mandamientos.estado`: como producción antes de este cambio.
        $this->artisan('acceso:sincronizar');
        Permission::findByName('mandamientos.estado')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->artisan('acceso:sincronizar');

        foreach ([CatalogoPermisos::ROL_ADMINISTRADOR, CatalogoPermisos::ROL_TECNICO_FELCC, CatalogoPermisos::ROL_TECNICO_DACI] as $rol) {
            $this->assertTrue(Rol::findByName($rol)->hasPermissionTo('mandamientos.estado'), $rol);
        }
        $this->assertFalse(Rol::findByName(CatalogoPermisos::ROL_CONSULTOR_FELCC)->hasPermissionTo('mandamientos.estado'));
    }
}
