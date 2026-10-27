<?php

namespace Tests\Feature\Personas;

use App\Models\Division;
use App\Models\FaltaContravencion;
use App\Models\Persona;
use App\Models\RegistroCriminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Búsqueda por C.I. + complemento con COALESCE: aviso en vivo de las altas (`personas.check-ci`), elección de la persona
 * al registrar y filtro de C.I. del listado de Personas.
 */
class BusquedaCiTest extends TestCase
{
    use RefreshDatabase;

    private function persona(string $ci, ?string $complemento, string $nombres): Persona
    {
        $persona = Persona::factory()->create(['nombres' => $nombres]);
        // Directo a la base: así se prueban también '' y espacios, que el formulario no guardaría.
        DB::table('persona')->where('id', $persona->id)->update(['ci' => $ci, 'complemento' => $complemento]);

        return $persona;
    }

    public function test_sin_complemento_encuentra_a_quien_lo_tiene_nulo_o_vacio(): void
    {
        $this->actingAs($this->usuarioConPermisos([]));
        $nulo = $this->persona('5544332', null, 'NULO');
        $this->persona('6655443', '', 'VACIO');

        $this->getJson(route('personas.check-ci', ['ci' => '5544332']))->assertJsonPath('data.id', $nulo->id);
        $this->getJson(route('personas.check-ci', ['ci' => '6655443', 'complemento' => '']))->assertJsonPath('data.nombres', 'VACIO');
    }

    public function test_distingue_complementos_y_tolera_espacios(): void
    {
        $this->actingAs($this->usuarioConPermisos([]));
        $sinComplemento = $this->persona('1234567', null, 'SIN');
        $conA = $this->persona(' 1234567 ', 'A ', 'CON A');

        $this->getJson(route('personas.check-ci', ['ci' => '1234567']))->assertJsonPath('data.id', $sinComplemento->id);
        $this->getJson(route('personas.check-ci', ['ci' => '1234567 ', 'complemento' => 'a']))->assertJsonPath('data.id', $conA->id);
        $this->getJson(route('personas.check-ci', ['ci' => '1234567', 'complemento' => 'B']))->assertJsonPath('data', null);
        $this->getJson(route('personas.check-ci', ['ci' => '   ']))->assertJsonPath('data', null);
        // Al editar una persona se excluye a sí misma.
        $this->getJson(route('personas.check-ci', ['ci' => '1234567', 'excluir' => $sinComplemento->id]))->assertJsonPath('data', null);
    }

    public function test_cuenta_los_registros_del_modulo_solo_con_permiso_de_registrar(): void
    {
        $this->actingAs($this->usuarioConPermisos([]));
        $persona = $this->persona('7788990', null, 'PEDRO');
        RegistroCriminal::create(['fecha_registro' => '2026-01-01', 'id_persona' => $persona->id, 'id_division' => Division::create(['division' => 'D'])->id]);

        $this->actingAs($this->usuarioConPermisos(['registro_criminal.crear']));
        $this->getJson(route('personas.check-ci', ['ci' => '7788990', 'contar' => 'registro_criminal']))
            ->assertJsonPath('data.registros', 1);
        // Sin permiso del módulo, o con un módulo fuera de la lista, no se cuenta.
        $this->getJson(route('personas.check-ci', ['ci' => '7788990', 'contar' => 'faltas_contravenciones']))
            ->assertJsonPath('data.registros', null);
        $this->getJson(route('personas.check-ci', ['ci' => '7788990', 'contar' => 'users']))
            ->assertJsonPath('data.registros', null);
    }

    public function test_el_alta_de_registro_criminal_usa_la_persona_del_mismo_ci_y_complemento(): void
    {
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.crear', 'registro_criminal.listar']));
        $sinComplemento = $this->persona('3344556', null, 'SIN');
        $conA = $this->persona('3344556', 'A', 'CON A');
        $division = Division::create(['division' => 'D'])->id;
        $datos = ['fecha_registro' => '2026-02-01', 'nombres' => 'OTRO', 'genero' => 'MASCULINO', 'id_division' => $division, 'ci' => '3344556'];

        $this->post(route('registro-criminal.store'), [...$datos, 'complemento' => 'A'])->assertSessionHasNoErrors();
        $this->post(route('registro-criminal.store'), $datos)->assertSessionHasNoErrors();

        $this->assertSame(1, RegistroCriminal::where('id_persona', $conA->id)->count());
        $this->assertSame(1, RegistroCriminal::where('id_persona', $sinComplemento->id)->count());
        $this->assertSame(2, Persona::count(), 'No debía crear personas nuevas.');
    }

    public function test_el_alta_de_faltas_y_contravenciones_usa_la_persona_del_mismo_ci_y_complemento(): void
    {
        $this->actingAs($this->usuarioConPermisos(['faltas_contravenciones.crear', 'faltas_contravenciones.listar']));
        $conB = $this->persona('9988776', 'B', 'CON B');

        $this->post(route('faltas-contravenciones.store'), [
            'fecha_registro' => '2026-02-01', 'nombres' => 'OTRO', 'genero' => 'FEMENINO',
            'id_division' => Division::create(['division' => 'D'])->id, 'ci' => '9988776', 'complemento' => 'b',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, FaltaContravencion::where('id_persona', $conB->id)->count());
        $this->assertSame(1, Persona::count());
    }

    public function test_el_filtro_de_ci_del_listado_encuentra_personas_sin_complemento(): void
    {
        $this->actingAs($this->usuarioConPermisos(['personas.listar']));
        $this->persona('4433221', null, 'SIN');
        $this->persona('4433221', 'X', 'CON X');

        // Antes `CONCAT(ci, '-', complemento)` daba NULL sin complemento y «4433221-» no encontraba a nadie.
        $this->get(route('personas.index', ['filtro' => 'ci', 'search' => '4433221-']))
            ->assertInertia(fn (Assert $p) => $p->where('personas.total', 2));
        $this->get(route('personas.index', ['filtro' => 'ci', 'search' => '4433221-X']))
            ->assertInertia(fn (Assert $p) => $p->where('personas.total', 1));
    }
}
