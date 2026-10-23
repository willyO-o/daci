<?php

namespace Tests\Feature\Personas;

use App\Models\Mandamiento;
use App\Models\Multimedia;
use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonasTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_listado_combina_la_busqueda_por_ci_con_el_filtro_de_genero(): void
    {
        $this->actingAs($this->usuarioConPermisos(['personas.listar']));
        Persona::factory()->create(['ci' => '4455661', 'genero' => 'FEMENINO']);
        Persona::factory()->create(['ci' => '4455662', 'genero' => 'MASCULINO']);

        $this->get(route('personas.index', ['search' => '445566', 'filtro' => 'ci', 'genero' => 'FEMENINO']))
            ->assertInertia(fn (Assert $pagina) => $pagina
                ->component('Personas/Index')
                ->where('personas.total', 1)
                ->where('personas.data.0.ci', '4455661'));
    }

    public function test_quien_no_puede_restaurar_no_ve_eliminados(): void
    {
        $this->actingAs($this->usuarioConPermisos(['personas.listar']));
        Persona::factory()->create()->delete();

        $this->get(route('personas.index', ['visible' => 'eliminados']))
            ->assertInertia(fn (Assert $pagina) => $pagina->where('personas.total', 0)->where('filtros.visible', 'activos'));
    }

    public function test_registra_una_persona_con_sus_fotos_en_la_carpeta_de_siempre(): void
    {
        Storage::fake('public');
        $usuario = $this->usuarioConPermisos(['personas.crear']);

        $this->actingAs($usuario)
            ->post(route('personas.store'), [
                'nombres' => 'JUAN',
                'apellidos' => 'PEREZ',
                'ci' => '1234567',
                'genero' => 'MASCULINO',
                'fotos' => [UploadedFile::fake()->image('frente.jpg')],
            ])
            ->assertSessionHas('success', 'Persona creada correctamente.');

        $persona = Persona::where('ci', '1234567')->firstOrFail();
        $this->assertSame($usuario->id, $persona->user_id);
        $foto = Multimedia::where('id_persona', $persona->id)->firstOrFail();
        $this->assertSame('persona', $foto->tipo);
        $this->assertStringStartsWith('personas/', $foto->ruta);
        Storage::disk('public')->assertExists($foto->ruta);
    }

    public function test_el_alta_al_vuelo_devuelve_la_persona_en_json(): void
    {
        $this->actingAs($this->usuarioConPermisos(['personas.crear']))
            ->postJson(route('personas.store'), ['nombres' => 'ANA'])
            ->assertCreated()
            ->assertJsonPath('datos.nombres', 'ANA');
    }

    public function test_valida_nombres_obligatorio_y_genero_permitido(): void
    {
        $this->actingAs($this->usuarioConPermisos(['personas.crear']))
            ->post(route('personas.store'), ['genero' => 'OTRO'])
            ->assertSessionHasErrors(['nombres', 'genero']);
    }

    public function test_eliminar_migrando_los_datos_a_otra_persona(): void
    {
        $this->actingAs($this->usuarioConPermisos(['personas.eliminar']));
        $duplicada = Persona::factory()->create();
        $principal = Persona::factory()->create();
        $mandamiento = Mandamiento::create(['id_persona' => $duplicada->id, 'estado' => 'PENDIENTE']);

        $this->delete(route('personas.destroy', $duplicada), ['persona_migrar_id' => $principal->id, 'eliminar_completo' => 1])
            ->assertSessionHas('success', 'Persona eliminada correctamente.');

        $this->assertSame($principal->id, $mandamiento->fresh()->id_persona);
        $this->assertDatabaseMissing('persona', ['id' => $duplicada->id]);
    }

    public function test_eliminar_sin_marcar_completo_deja_la_persona_recuperable(): void
    {
        $this->actingAs($this->usuarioConPermisos(['personas.eliminar', 'personas.restaurar']));
        $persona = Persona::factory()->create();

        $this->delete(route('personas.destroy', $persona))->assertSessionHas('success');
        $this->assertSoftDeleted($persona);

        $this->post(route('personas.restore', $persona))->assertSessionHas('success', 'Persona restaurada correctamente.');
        $this->assertNotSoftDeleted($persona);
    }

    public function test_sin_permiso_de_eliminar_no_borra(): void
    {
        $this->actingAs($this->usuarioConPermisos(['personas.listar', 'personas.editar']));
        $persona = Persona::factory()->create();

        $this->delete(route('personas.destroy', $persona))->assertForbidden();
        $this->assertNotSoftDeleted($persona);
    }

    public function test_el_detalle_responde_json_para_el_modal(): void
    {
        $this->actingAs($this->usuarioConPermisos(['personas.ver']));
        $persona = Persona::factory()->create(['nombres' => 'MARIA']);

        $this->getJson(route('personas.show', $persona))
            ->assertOk()
            ->assertJsonPath('nombres', 'MARIA')
            ->assertJsonStructure(['registro_criminal', 'mandamientos', 'vehiculos', 'telefonos', 'multimedia']);
    }

    public function test_verifica_ci_duplicado_respetando_el_complemento(): void
    {
        $this->actingAs($this->usuarioConPermisos([]));
        Persona::factory()->create(['ci' => '7654321', 'complemento' => null]);

        $this->getJson(route('personas.check-ci', ['ci' => '7654321']))->assertJsonPath('data.ci', '7654321');
        $this->getJson(route('personas.check-ci', ['ci' => '7654321', 'complemento' => '1A']))->assertJsonPath('data', null);
    }
}
