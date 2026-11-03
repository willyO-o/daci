<?php

namespace Tests\Feature\Casos;

use App\Models\Caso;
use App\Models\Division;
use App\Models\FaltaContravencion;
use App\Models\Persona;
use App\Models\RegistroCriminal;
use App\Models\Telefono;
use App\Models\Vehiculo;
use App\Models\VehiculoCaso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * El selector de caso de los formularios (D-037, fase 3): cada formulario guarda `id_caso` y copia el código en su
 * columna de texto; quitar el caso deja el texto nulo, pero una fila con texto heredado sin caso lo conserva; el buscador
 * y el alta rápida los usa quien registra en el módulo, sin permisos de casos.
 */
class FormulariosCasoTest extends TestCase
{
    use RefreshDatabase;

    public function test_telefono_guarda_id_caso_y_copia_el_codigo(): void
    {
        $this->actingAs($this->usuarioConPermisos(['telefonos.crear', 'telefonos.editar']));
        $caso = Caso::create(['codigo' => 'CASO KEYTON']);
        $otro = Caso::create(['codigo' => 'OTRO']);

        $this->post(route('telefonos.store'), ['numero_celular' => '70000001', 'id_caso' => $caso->id])->assertSessionHasNoErrors();
        $telefono = Telefono::where('numero_celular', '70000001')->sole();
        $this->assertSame([$caso->id, 'CASO KEYTON'], [(int) $telefono->id_caso, $telefono->caso]);

        $this->put(route('telefonos.update', $telefono), ['numero_celular' => '70000001', 'id_caso' => $otro->id])->assertSessionHasNoErrors();
        $this->assertSame([$otro->id, 'OTRO'], [(int) $telefono->fresh()->id_caso, $telefono->fresh()->caso]);

        // Quitar el caso: sin caso y sin texto.
        $this->put(route('telefonos.update', $telefono), ['numero_celular' => '70000001', 'id_caso' => null])->assertSessionHasNoErrors();
        $this->assertSame([null, null], [$telefono->fresh()->id_caso, $telefono->fresh()->caso]);
    }

    public function test_editar_una_fila_con_texto_heredado_sin_caso_no_lo_pierde(): void
    {
        $this->actingAs($this->usuarioConPermisos(['telefonos.editar']));
        $telefono = Telefono::create(['numero_celular' => '70000001', 'caso' => 'S/N', 'empresa' => 'ENTEL']);

        $this->put(route('telefonos.update', $telefono), ['numero_celular' => '70000001', 'empresa' => 'TIGO', 'id_caso' => null])->assertSessionHasNoErrors();

        $this->assertSame(['TIGO', 'S/N', null], [$telefono->fresh()->empresa, $telefono->fresh()->caso, $telefono->fresh()->id_caso]);
    }

    public function test_un_caso_que_no_existe_se_rechaza(): void
    {
        $this->actingAs($this->usuarioConPermisos(['telefonos.crear']));

        $this->post(route('telefonos.store'), ['numero_celular' => '70000001', 'id_caso' => 999999])
            ->assertSessionHasErrors(['id_caso' => 'El caso elegido ya no existe.']);
        $this->assertSame(0, Telefono::count());
    }

    public function test_registro_criminal_y_faltas_guardan_el_cud_por_id_caso(): void
    {
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.crear', 'registro_criminal.editar', 'faltas_contravenciones.crear']));
        $cud = Caso::create(['codigo' => '201102012600756']);
        $persona = Persona::factory()->create();
        $division = Division::create(['division' => 'D'])->id;
        $datos = ['fecha_registro' => '2026-02-01', 'id_persona' => $persona->id, 'nombres' => $persona->nombres, 'genero' => 'FEMENINO', 'id_division' => $division, 'id_caso' => $cud->id];

        $this->post(route('registro-criminal.store'), $datos)->assertSessionHasNoErrors();
        $this->post(route('faltas-contravenciones.store'), $datos)->assertSessionHasNoErrors();

        $registro = RegistroCriminal::where('id_persona', $persona->id)->sole();
        $falta = FaltaContravencion::where('id_persona', $persona->id)->sole();
        $this->assertSame([$cud->id, '201102012600756'], [(int) $registro->id_caso, $registro->cud]);
        $this->assertSame([$cud->id, '201102012600756'], [(int) $falta->id_caso, $falta->cud]);

        // Al editar, el formulario recibe el caso para mostrarlo.
        $this->get(route('registro-criminal.edit', $registro))
            ->assertInertia(fn (Assert $p) => $p->where('registro.id_caso', $cud->id)->where('registro.caso_codigo', '201102012600756'));

        $this->put(route('registro-criminal.update', $registro), ['fecha_registro' => '2026-02-01', 'id_division' => $division, 'id_caso' => null])->assertSessionHasNoErrors();
        $this->assertSame([null, null], [$registro->fresh()->id_caso, $registro->fresh()->cud]);
    }

    public function test_vehiculo_y_sus_personas_guardan_su_caso(): void
    {
        $this->actingAs($this->usuarioConPermisos(['vehiculos.crear', 'vehiculos.editar', 'vehiculos.listar']));
        $caso = Caso::create(['codigo' => 'ROBO CHINO']);
        $largo = Caso::create(['codigo' => str_repeat('A', 200)]);
        $persona = Persona::factory()->create();
        $otra = Persona::factory()->create();

        $this->post(route('vehiculos.store'), [
            'placa' => '1234ABC',
            'id_caso' => $caso->id,
            'personas_asociadas' => [['persona_id' => $persona->id, 'tipo' => 'RUAT', 'id_caso' => $largo->id]],
        ])->assertSessionHasNoErrors();

        $vehiculo = Vehiculo::where('placa', '1234ABC')->sole();
        $this->assertSame([$caso->id, 'ROBO CHINO'], [(int) $vehiculo->id_caso, $vehiculo->caso_relacionado]);
        $vinculo = VehiculoCaso::where('vehiculo_id', $vehiculo->id)->sole();
        $this->assertSame($largo->id, (int) $vinculo->id_caso);
        $this->assertSame(140, mb_strlen($vinculo->numero_informacion), 'La copia se recorta a la columna (140).');

        // Un vínculo con texto heredado y sin caso conserva su texto al editar el vehículo.
        VehiculoCaso::create(['vehiculo_id' => $vehiculo->id, 'persona_id' => $otra->id, 'tipo' => 'SOAT', 'numero_informacion' => 'CASO VIEJO']);
        $this->put(route('vehiculos.update', $vehiculo), [
            'placa' => '1234ABC',
            'id_caso' => $caso->id,
            'personas_asociadas' => [
                ['persona_id' => $persona->id, 'tipo' => 'RUAT', 'id_caso' => $caso->id],
                ['persona_id' => $otra->id, 'tipo' => 'SOAT', 'id_caso' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('ROBO CHINO', VehiculoCaso::where('persona_id', $persona->id)->sole()->numero_informacion);
        $this->assertSame('CASO VIEJO', VehiculoCaso::where('persona_id', $otra->id)->sole()->numero_informacion);

        // El listado trae el código del caso de cada vínculo.
        $this->get(route('vehiculos.index'))
            ->assertInertia(fn (Assert $p) => $p->where('vehiculos.data.0.caso_vinculado.codigo', 'ROBO CHINO')
                ->where('vehiculos.data.0.personas', fn ($personas) => collect($personas)->pluck('pivot.caso_codigo')->contains('ROBO CHINO')));
    }

    public function test_el_buscador_y_el_alta_rapida_no_piden_permisos_de_casos(): void
    {
        $this->actingAs($this->usuarioConPermisos(['telefonos.crear']));
        Caso::create(['codigo' => 'EAL 1 MILLON']);
        Caso::create(['codigo' => 'EAL MILLON']);

        $this->getJson(route('casos.buscar', ['q' => 'eal millon']))->assertOk()
            ->assertJsonPath('0.codigo', 'EAL MILLON');

        $this->postJson(route('casos.store-rapido'), ['codigo' => ' - nuevo  caso - '])->assertCreated()
            ->assertJsonPath('codigo', 'NUEVO CASO')->assertJsonPath('existente', false);
        $this->postJson(route('casos.store-rapido'), ['codigo' => 'Nuevo Caso'])->assertOk()
            ->assertJsonPath('existente', true);
        $this->postJson(route('casos.store-rapido'), ['codigo' => 'S/N'])->assertUnprocessable()->assertJsonValidationErrors('codigo');
        $this->assertSame(1, Caso::where('codigo', 'NUEVO CASO')->count());
        $this->assertSame(auth()->id(), Caso::where('codigo', 'NUEVO CASO')->value('user_id'));
    }

    public function test_sin_permiso_de_ningun_formulario_no_busca_ni_crea(): void
    {
        $this->actingAs($this->usuarioConPermisos(['telefonos.listar']));

        $this->getJson(route('casos.buscar', ['q' => 'x']))->assertForbidden();
        $this->postJson(route('casos.store-rapido'), ['codigo' => 'X'])->assertForbidden();
    }

    public function test_las_importaciones_vinculan_el_caso(): void
    {
        $this->actingAs($this->usuarioConPermisos(['importar.telefonos', 'importar.vehiculos']));
        Caso::create(['codigo' => 'CASO KEYTON']);

        $this->postJson(route('telefonos.importar.store'), ['archivo' => UploadedFile::fake()->createWithContent('t.csv', "NUMERO_DE_CELULAR,CASO\n70000001,caso keyton\n70000002,S/N\n")])->assertOk();
        $this->postJson(route('vehiculos.importar.store'), ['archivo' => UploadedFile::fake()->createWithContent('v.csv', "PLACA,CASO_RELACIONADO\n1234ABC,robo nuevo\n")])->assertOk();

        $this->assertSame('CASO KEYTON', Telefono::where('numero_celular', '70000001')->sole()->casoVinculado?->codigo);
        $this->assertNull(Telefono::where('numero_celular', '70000002')->sole()->id_caso);
        $this->assertSame('ROBO NUEVO', Vehiculo::where('placa', '1234ABC')->sole()->casoVinculado?->codigo);
        $this->assertSame(1, Caso::where('codigo', 'CASO KEYTON')->count(), 'Si el caso existe, se toma ése.');
    }
}
