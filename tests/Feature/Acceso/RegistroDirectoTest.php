<?php

namespace Tests\Feature\Acceso;

use App\Models\Imei;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\Telefono;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Páginas «Registrar …» para quien sólo tiene el permiso de registrar (`*.crear`), sin ver el listado: igual que
 * «Registrar Mandamiento». Se abren desde el Dashboard y, sin el listado, desde el menú.
 */
class RegistroDirectoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function modulos(): array
    {
        return [
            'personas' => ['personas', 'Personas/Registrar'],
            'telefonos' => ['telefonos', 'Telefonos/Registrar'],
            'imeis' => ['imeis', 'Imeis/Registrar'],
            'vehiculos' => ['vehiculos', 'Vehiculos/Registrar'],
        ];
    }

    #[DataProvider('modulos')]
    public function test_solo_con_registrar_abre_la_pagina_pero_no_el_listado(string $modulo, string $componente): void
    {
        $this->actingAs($this->usuarioConPermisos(["{$modulo}.crear"]));

        $this->get(route("{$modulo}.create"))->assertOk()->assertInertia(fn (Assert $pagina) => $pagina->component($componente));
        $this->get(route("{$modulo}.index"))->assertForbidden();
    }

    #[DataProvider('modulos')]
    public function test_sin_registrar_no_abre_la_pagina(string $modulo): void
    {
        $this->actingAs($this->usuarioConPermisos(["{$modulo}.listar"]))
            ->get(route("{$modulo}.create"))
            ->assertForbidden();
    }

    public function test_registrar_persona_recibe_las_nacionalidades_y_guarda(): void
    {
        $pais = Pais::create(['pais' => 'Bolivia', 'gentilicio' => 'boliviano/a']);
        $this->actingAs($this->usuarioConPermisos(['personas.crear']));

        $this->get(route('personas.create'))
            ->assertInertia(fn (Assert $pagina) => $pagina->where('paises.0.gentilicio', 'boliviano/a'));

        $this->from(route('personas.create'))
            ->post(route('personas.store'), ['nombres' => 'JUAN', 'apellidos' => 'PEREZ', 'id_pais' => $pais->id])
            ->assertRedirect(route('personas.create'))
            ->assertSessionHas('success', 'Persona creada correctamente.');

        $this->assertDatabaseHas('persona', ['nombres' => 'JUAN', 'apellidos' => 'PEREZ']);
    }

    public function test_desde_la_pagina_se_registran_telefono_imei_y_vehiculo_volviendo_a_ella(): void
    {
        $this->actingAs($this->usuarioConPermisos(['telefonos.crear', 'imeis.crear', 'vehiculos.crear']));
        $persona = Persona::factory()->create();

        $this->from(route('telefonos.create'))
            ->post(route('telefonos.store'), ['numero_celular' => '71234567', 'persona_id' => $persona->id])
            ->assertRedirect(route('telefonos.create'))
            ->assertSessionHas('success');
        $telefono = Telefono::where('numero_celular', '71234567')->firstOrFail();

        // El buscador de teléfonos del formulario de IMEI responde con sólo el permiso de registrar.
        $this->getJson(route('imeis.telefonos-search', ['q' => '7123']))->assertOk();

        $this->from(route('imeis.create'))
            ->post(route('imeis.store'), ['imei' => '490154203237518', 'telefono_id' => [$telefono->id]])
            ->assertRedirect(route('imeis.create'))
            ->assertSessionHas('success');
        $this->assertSame([$telefono->id], Imei::where('imei', '490154203237518')->firstOrFail()->telefonos()->pluck('telefono.id')->all());

        $this->from(route('vehiculos.create'))
            ->post(route('vehiculos.store'), ['placa' => '1234ABC', 'personas_asociadas' => [['persona_id' => $persona->id, 'tipo' => 'RUAT']]])
            ->assertRedirect(route('vehiculos.create'))
            ->assertSessionHas('success');
        $this->assertTrue(Vehiculo::where('placa', '1234ABC')->exists());
    }
}
