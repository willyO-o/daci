<?php

namespace Tests\Feature\RegistroCriminal;

use App\Models\Division;
use App\Models\FichaFaltaContravencionPlantilla;
use App\Models\FichaRegistroPlantilla;
use App\Models\Persona;
use App\Models\RegistroCriminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

/**
 * «Plantilla de la Ficha»: editar el texto base de la ficha de Registro Criminal y de Faltas y Contravenciones.
 */
class PlantillaFichaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function datos(array $cambios = []): array
    {
        return [...FichaRegistroPlantilla::TEXTO_ESTANDAR, ...$cambios];
    }

    public function test_sin_permiso_no_se_ve_ni_se_guarda(): void
    {
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.ficha']));

        $this->get(route('plantilla-ficha.edit'))->assertForbidden();
        $this->put(route('plantilla-ficha.update', 'registro_criminal'), $this->datos())->assertForbidden();
        $this->assertSame(0, FichaRegistroPlantilla::count());
    }

    public function test_muestra_solo_los_modulos_permitidos_y_el_texto_estandar_si_no_hay_plantilla(): void
    {
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.plantilla']));

        $this->get(route('plantilla-ficha.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('PlantillaFicha/Editar')
                ->has('modulos', 1)
                ->where('modulos.0.clave', 'registro_criminal')
                ->where('modulos.0.existe', false)
                ->where('modulos.0.campos.persona', FichaRegistroPlantilla::TEXTO_ESTANDAR['persona'])
                ->has('definicion.resultado_busqueda.variables.division'));
    }

    public function test_guardar_crea_la_plantilla_si_falta_y_luego_actualiza_la_misma_fila(): void
    {
        // La auditoría no registra lo que corre por consola (`audit.console`), como estas pruebas.
        config(['audit.console' => true]);
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.plantilla']));
        $inactiva = FichaRegistroPlantilla::create([...FichaRegistroPlantilla::TEXTO_ESTANDAR, 'nota_general' => 'VIEJA', 'estado' => 'INACTIVO']);

        $this->put(route('plantilla-ficha.update', 'registro_criminal'), $this->datos(['nota_general' => 'NOTA NUEVA']))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->put(route('plantilla-ficha.update', 'registro_criminal'), $this->datos(['nota_general' => 'NOTA CORREGIDA']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, FichaRegistroPlantilla::where('estado', 'ACTIVO')->count());
        $this->assertSame('NOTA CORREGIDA', FichaRegistroPlantilla::where('estado', 'ACTIVO')->value('nota_general'));
        // La fila inactiva no se toca.
        $this->assertSame('VIEJA', $inactiva->fresh()->nota_general);
        // El cambio queda en la Auditoría de cambios.
        $this->assertTrue(Audit::where('auditable_type', FichaRegistroPlantilla::class)->where('event', 'updated')->exists());
    }

    public function test_rechaza_variables_que_no_existen_y_acepta_las_del_bloque(): void
    {
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.plantilla']));

        $this->put(route('plantilla-ficha.update', 'registro_criminal'), $this->datos(['persona' => '{nombre_persona} {apellido}']))
            ->assertSessionHasErrors(['persona' => '«Persona» tiene variables que no existen: {apellido}. Puede usar: {nombre_persona}, {ci_persona}.']);
        $this->put(route('plantilla-ficha.update', 'registro_criminal'), $this->datos(['nota_general' => 'Hasta {division}']))
            ->assertSessionHasErrors(['nota_general' => '«Nota general» tiene variables que no existen: {division}. Este bloque no admite variables.']);
        $this->assertSame(0, FichaRegistroPlantilla::count());

        $this->put(route('plantilla-ficha.update', 'registro_criminal'), $this->datos(['persona' => 'SR. {nombre_persona} ({ci_persona})']))
            ->assertSessionHasNoErrors();
    }

    public function test_el_permiso_de_un_modulo_no_alcanza_para_el_otro(): void
    {
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.plantilla']));

        $this->put(route('plantilla-ficha.update', 'faltas_contravenciones'), $this->datos())->assertForbidden();

        $this->actingAs($this->usuarioConPermisos(['faltas_contravenciones.plantilla']));
        $this->put(route('plantilla-ficha.update', 'faltas_contravenciones'), $this->datos(['nota_general' => 'NOTA FALTAS']))->assertSessionHasNoErrors();
        $this->assertSame('NOTA FALTAS', FichaFaltaContravencionPlantilla::where('estado', 'ACTIVO')->value('nota_general'));
        $this->assertSame(0, FichaRegistroPlantilla::count());
    }

    public function test_la_ficha_usa_el_texto_guardado(): void
    {
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.plantilla', 'registro_criminal.ficha']));
        $this->put(route('plantilla-ficha.update', 'registro_criminal'), $this->datos(['persona' => 'IDENTIFICADO: {nombre_persona}']));
        $registro = RegistroCriminal::create([
            'fecha_registro' => '2026-01-10',
            'id_persona' => Persona::factory()->create(['nombres' => 'PEDRO', 'apellidos' => 'MAMANI'])->id,
            'id_division' => Division::create(['division' => 'D'])->id,
        ]);

        $this->getJson(route('registro.vista-previa', $registro))
            ->assertOk()
            ->assertJsonPath('campos.persona', '•  IDENTIFICADO: PEDRO MAMANI');
    }

    public function test_no_acepta_otros_modulos_en_la_ruta(): void
    {
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.plantilla']));

        $this->put('/plantilla-ficha/users', $this->datos())->assertNotFound();
        $this->assertSame(0, DB::table('ficha_registro_plantilla')->count());
    }
}
