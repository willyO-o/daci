<?php

namespace Tests\Feature\Telefonos;

use App\Models\Imei;
use App\Models\Persona;
use App\Models\Telefono;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TelefonosImeisTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_numero_celular_no_se_repite(): void
    {
        $this->actingAs($this->usuarioConPermisos(['telefonos.crear']));
        Telefono::create(['numero_celular' => '71234567']);

        $this->post(route('telefonos.store'), ['numero_celular' => '71234567'])
            ->assertSessionHasErrors(['numero_celular' => 'Este número ya está registrado.']);
    }

    public function test_busca_telefonos_por_imei(): void
    {
        $this->actingAs($this->usuarioConPermisos(['telefonos.listar']));
        $conImei = Telefono::create(['numero_celular' => '70000001']);
        Telefono::create(['numero_celular' => '70000002']);
        Imei::create(['imei' => '356938035643809'])->telefonos()->attach($conImei);

        $this->get(route('telefonos.index', ['search' => '3569380', 'filtro' => 'imei']))
            ->assertInertia(fn (Assert $pagina) => $pagina->where('telefonos.total', 1)->where('telefonos.data.0.numero_celular', '70000001'));
    }

    public function test_vincular_persona_exige_permiso_de_editar(): void
    {
        $this->actingAs($this->usuarioConPermisos(['telefonos.listar']));
        $telefono = Telefono::create(['numero_celular' => '70000003']);
        $persona = Persona::factory()->create();

        $this->post(route('telefonos.vincular-persona', $telefono), ['persona_id' => $persona->id])->assertForbidden();
        $this->assertNull($telefono->fresh()->persona_id);
    }

    public function test_un_imei_se_vincula_a_varios_telefonos(): void
    {
        $this->actingAs($this->usuarioConPermisos(['imeis.crear']));
        $uno = Telefono::create(['numero_celular' => '70000004']);
        $dos = Telefono::create(['numero_celular' => '70000005']);

        $this->post(route('imeis.store'), ['imei' => '490154203237518', 'telefono_id' => [$uno->id, $dos->id]])
            ->assertSessionHas('success', 'IMEI registrado correctamente.');

        $this->assertEqualsCanonicalizing([$uno->id, $dos->id], Imei::where('imei', '490154203237518')->firstOrFail()->telefonos->pluck('id')->all());
    }

    public function test_el_buscador_de_telefonos_esta_acotado(): void
    {
        $this->actingAs($this->usuarioConPermisos(['imeis.crear']));
        foreach (range(1, 35) as $indice) {
            Telefono::create(['numero_celular' => '7100'.str_pad((string) $indice, 4, '0', STR_PAD_LEFT)]);
        }

        $this->getJson(route('imeis.telefonos-search', ['q' => '7100']))->assertOk()->assertJsonCount(30);
    }
}
