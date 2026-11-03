<?php

namespace Tests\Feature\Casos;

use App\Models\Caso;
use App\Models\Division;
use App\Models\FaltaContravencion;
use App\Models\Persona;
use App\Models\Telefono;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Módulo Casos: listado y detalle por permiso, secciones según el permiso de cada módulo, alta/edición con el código
 * limpio y eliminación sólo sin registros vinculados.
 */
class ModuloCasosTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_permiso_no_se_ve_el_listado(): void
    {
        $this->actingAs($this->usuarioConPermisos(['telefonos.listar']));

        $this->get(route('casos.index'))->assertForbidden();
    }

    public function test_listado_con_busqueda_y_cantidad_de_vinculados(): void
    {
        $this->actingAs($this->usuarioConPermisos(['casos.listar', 'telefonos.ver']));
        $caso = Caso::create(['codigo' => 'CASO KEYTON']);
        Caso::create(['codigo' => 'OTRO']);
        Telefono::create(['numero_celular' => '70000001', 'id_caso' => $caso->id]);
        Telefono::create(['numero_celular' => '70000002', 'id_caso' => $caso->id]);

        $this->get(route('casos.index', ['search' => 'keyton']))
            ->assertInertia(fn (Assert $p) => $p->component('Casos/Index')
                ->has('casos.data', 1)
                ->where('casos.data.0.codigo', 'CASO KEYTON')
                ->where('casos.data.0.telefonos_count', 2)
                ->missing('casos.data.0.faltas_contravenciones_count'));
    }

    public function test_el_detalle_muestra_solo_los_modulos_que_el_usuario_puede_ver(): void
    {
        $this->actingAs($this->usuarioConPermisos(['casos.ver', 'telefonos.ver']));
        $caso = Caso::create(['codigo' => '201102012600756']);
        Telefono::create(['numero_celular' => '70000001', 'id_caso' => $caso->id]);
        FaltaContravencion::create([
            'fecha_registro' => '2026-01-01', 'id_persona' => Persona::factory()->create()->id,
            'id_division' => Division::create(['division' => 'D'])->id, 'id_caso' => $caso->id,
        ]);

        $this->get(route('casos.show', $caso))
            ->assertInertia(fn (Assert $p) => $p->component('Casos/Ver')
                ->where('caso.codigo', '201102012600756')
                ->where('secciones.telefonos.total', 1)
                ->where('secciones.telefonos.filas.0.numero_celular', '70000001')
                ->missing('secciones.faltasContravenciones'));
    }

    public function test_alta_limpia_el_codigo_y_rechaza_relleno_y_repetidos(): void
    {
        $this->actingAs($this->usuarioConPermisos(['casos.listar', 'casos.crear']));

        $this->post(route('casos.store'), ['codigo' => '  - caso   nuevo - ', 'estado' => 'ACTIVO'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('caso', ['codigo' => 'CASO NUEVO', 'user_id' => auth()->id()]);

        $this->post(route('casos.store'), ['codigo' => 'S/N', 'estado' => 'ACTIVO'])->assertSessionHasErrors('codigo');
        $this->post(route('casos.store'), ['codigo' => ' - ', 'estado' => 'ACTIVO'])->assertSessionHasErrors('codigo');
        $this->post(route('casos.store'), ['codigo' => 'Caso Nuevo', 'estado' => 'ACTIVO'])->assertSessionHasErrors('codigo');
        $this->assertSame(1, Caso::count());
    }

    public function test_editar_corrige_el_codigo_para_todas_sus_filas(): void
    {
        $this->actingAs($this->usuarioConPermisos(['casos.editar']));
        $caso = Caso::create(['codigo' => 'EAL MILLON']);
        $telefono = Telefono::create(['numero_celular' => '70000001', 'id_caso' => $caso->id]);

        $this->put(route('casos.update', $caso), ['codigo' => 'EAL 1 MILLON', 'estado' => 'INACTIVO'])->assertSessionHasNoErrors();

        $this->assertSame('EAL 1 MILLON', $telefono->fresh()->casoVinculado->codigo);
        $this->assertSame('INACTIVO', $caso->fresh()->estado);
    }

    public function test_solo_se_elimina_un_caso_sin_registros_vinculados(): void
    {
        $this->actingAs($this->usuarioConPermisos(['casos.listar', 'casos.eliminar']));
        $usado = Caso::create(['codigo' => 'USADO']);
        $telefono = Telefono::create(['numero_celular' => '70000001', 'id_caso' => $usado->id]);
        $telefono->delete(); // aunque el teléfono esté eliminado, el caso sigue en uso
        $libre = Caso::create(['codigo' => 'LIBRE']);

        $this->delete(route('casos.destroy', $usado))->assertSessionHas('error');
        $this->assertDatabaseHas('caso', ['id' => $usado->id]);

        $this->delete(route('casos.destroy', $libre))->assertRedirect(route('casos.index'));
        $this->assertDatabaseMissing('caso', ['id' => $libre->id]);
    }
}
