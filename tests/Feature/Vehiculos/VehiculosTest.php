<?php

namespace Tests\Feature\Vehiculos;

use App\Models\Multimedia;
use App\Models\Persona;
use App\Models\Vehiculo;
use App\Models\VehiculoCaso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehiculosTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_el_vehiculo_con_sus_personas_y_fotos(): void
    {
        Storage::fake('public');
        $this->actingAs($this->usuarioConPermisos(['vehiculos.crear']));
        $persona = Persona::factory()->create();

        $this->post(route('vehiculos.store'), [
            'placa' => '1234ABC',
            'personas_asociadas' => [['persona_id' => $persona->id, 'tipo' => 'ruat', 'caso' => 'caso-9']],
            'fotosVehiculos' => [UploadedFile::fake()->image('frente.jpg')],
        ])->assertSessionHas('success', 'Vehículo creado correctamente');

        $vehiculo = Vehiculo::where('placa', '1234ABC')->firstOrFail();
        $this->assertDatabaseHas('vehiculo_caso', ['vehiculo_id' => $vehiculo->id, 'persona_id' => $persona->id, 'tipo' => 'RUAT', 'numero_informacion' => 'CASO-9']);
        $foto = Multimedia::where('id_vehiculo', $vehiculo->id)->firstOrFail();
        $this->assertStringStartsWith('vehiculos/', $foto->ruta);
    }

    public function test_vincular_guarda_el_caso_y_no_duplica_el_tipo(): void
    {
        $this->actingAs($this->usuarioConPermisos(['vehiculos.vincular']));
        $vehiculo = Vehiculo::create(['placa' => '5555XYZ']);
        $persona = Persona::factory()->create();

        $this->post(route('vehiculos.vincular-persona', $vehiculo), ['persona_id' => $persona->id, 'tipo' => 'SOAT', 'caso' => '77'])
            ->assertSessionHas('success');
        $this->post(route('vehiculos.vincular-persona', $vehiculo), ['persona_id' => $persona->id, 'tipo' => 'SOAT'])
            ->assertSessionHasErrors(['persona_id' => 'Esta persona ya está vinculada con este tipo']);

        $this->assertSame('77', VehiculoCaso::where('vehiculo_id', $vehiculo->id)->sole()->numero_informacion);
    }

    public function test_la_placa_no_se_repite(): void
    {
        $this->actingAs($this->usuarioConPermisos(['vehiculos.crear']));
        Vehiculo::create(['placa' => '9999AAA']);

        $this->post(route('vehiculos.store'), ['placa' => '9999AAA'])
            ->assertSessionHasErrors(['placa' => 'Ya existe un vehículo con esta placa.']);
    }

    public function test_el_detalle_trae_carguios_e_itv(): void
    {
        $this->actingAs($this->usuarioConPermisos(['vehiculos.ver']));
        $vehiculo = Vehiculo::create(['placa' => '1111BBB']);

        $this->getJson(route('vehiculos.show', $vehiculo))
            ->assertOk()
            ->assertJsonPath('placa', '1111BBB')
            ->assertJsonStructure(['cargios', 'inspecciones', 'personas', 'multimedia']);
    }
}
