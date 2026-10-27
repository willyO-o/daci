<?php

namespace Tests\Feature\Antecedentes;

use App\Models\AuditarConsultas;
use App\Models\Division;
use App\Models\FaltaContravencion;
use App\Models\Persona;
use App\Models\RegistroCriminal;
use App\Models\User;
use App\Support\Antecedentes\ModuloAntecedente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Faltas y Contravenciones: funciona como Registro Criminal sobre sus propias tablas, y nunca ve ni toca los
 * registros de Registro Criminal.
 */
class AntecedentesTest extends TestCase
{
    use RefreshDatabase;

    private ModuloAntecedente $modulo = ModuloAntecedente::FaltaContravencion;

    private function registro(array $cambios = []): FaltaContravencion
    {
        return FaltaContravencion::create([
            'fecha_registro' => '2026-01-10',
            'id_persona' => Persona::factory()->create(['nombres' => 'PEDRO', 'apellidos' => 'MAMANI', 'ci' => '4455667'])->id,
            'id_division' => Division::create(['division' => 'DIVISION PATRULLAJE'])->id,
            'alias' => 'EL ZORRO',
            'especialidad' => 'ESCANDALO',
            ...$cambios,
        ]);
    }

    /**
     * @param  list<string>  $acciones
     */
    private function usuarioCon(array $acciones): User
    {
        return $this->usuarioConPermisos(array_map(fn (string $a) => $a === 'consulta' ? $this->modulo->permisoConsulta() : $this->modulo->permiso($a), $acciones));
    }

    public function test_cada_pantalla_exige_su_permiso(): void
    {
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.listar', 'registro_criminal.crear', 'consultas.registro_criminal']));
        $this->get(route('faltas-contravenciones.index'))->assertForbidden();
        $this->get(route('faltas-contravenciones.create'))->assertForbidden();
        $this->get(route('consultas.faltas-contravenciones'))->assertForbidden();

        $this->actingAs($this->usuarioCon(['listar', 'crear', 'consulta']));
        $this->get(route('faltas-contravenciones.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Antecedentes/Index')->where('modulo.titulo', 'Faltas y Contravenciones'));
        $this->get(route('faltas-contravenciones.create'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Antecedentes/Formulario')->where('modulo.titulo', 'Faltas y Contravenciones'));
        $this->get(route('consultas.faltas-contravenciones'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Consultas/Antecedentes'));
    }

    public function test_registra_con_persona_nueva_y_foto_en_su_carpeta(): void
    {
        Storage::fake('public');
        $this->actingAs($this->usuarioCon(['listar', 'crear']));

        $this->post(route('faltas-contravenciones.store'), [
            'fecha_registro' => '2026-02-01',
            'nombres' => 'JUAN',
            'apellidos' => 'QUISPE',
            'ci' => '8877665',
            'genero' => 'MASCULINO',
            'id_division' => Division::create(['division' => 'DIVISION X'])->id,
            'telefono' => '76543210',
            'foto_frente' => UploadedFile::fake()->image('frente.jpg', 300, 300),
        ])->assertRedirect(route('faltas-contravenciones.index'))->assertSessionHas('success', 'Registro creado exitosamente.');

        $persona = Persona::where('ci', '8877665')->firstOrFail();
        $registro = FaltaContravencion::where('id_persona', $persona->id)->firstOrFail();
        $this->assertSame(1, $registro->nro_registro);
        $this->assertDatabaseHas('telefono', ['numero_celular' => '76543210', 'persona_id' => $persona->id]);

        $foto = $registro->fotos()->firstOrFail();
        $this->assertSame('FRONTAL', $foto->tipo);
        $this->assertMatchesRegularExpression('#^faltas-contravenciones/img_.+\.webp$#', $foto->ruta_archivo);
        Storage::disk('public')->assertExists($foto->ruta_archivo);

        // Nada se escribió en Registro Criminal.
        $this->assertSame(0, RegistroCriminal::count());
    }

    public function test_sin_ver_el_listado_vuelve_al_formulario_tras_guardar(): void
    {
        $this->actingAs($this->usuarioCon(['crear']));
        $persona = Persona::factory()->create();

        $this->from(route('faltas-contravenciones.create'))->post(route('faltas-contravenciones.store'), [
            'fecha_registro' => '2026-02-01',
            'id_persona' => $persona->id,
            'nombres' => $persona->nombres,
            'genero' => 'FEMENINO',
            'id_division' => Division::create(['division' => 'DIVISION X'])->id,
        ])->assertRedirect(route('faltas-contravenciones.create'));
    }

    public function test_la_numeracion_es_propia_y_no_reutiliza_numeros_de_eliminados(): void
    {
        $this->actingAs($this->usuarioConPermisos([]));
        RegistroCriminal::create(['fecha_registro' => '2026-01-01', 'id_persona' => Persona::factory()->create()->id, 'id_division' => Division::create(['division' => 'D'])->id]);

        $primero = $this->registro();
        $this->assertSame(1, $primero->nro_registro);
        $primero->delete();

        $this->assertSame(2, $this->registro()->nro_registro);
    }

    public function test_no_ve_ni_toca_registros_de_registro_criminal(): void
    {
        $this->actingAs($this->usuarioConPermisos([]));
        $criminal = RegistroCriminal::create([
            'fecha_registro' => '2026-01-01',
            'id_persona' => Persona::factory()->create(['ci' => '4455667'])->id,
            'id_division' => Division::create(['division' => 'D'])->id,
        ]);

        $this->actingAs($this->usuarioCon(['listar', 'ver', 'editar', 'eliminar', 'ficha', 'consulta']));

        $this->getJson(route('faltas-contravenciones.index'))->assertJsonPath('total', 0);
        $this->getJson(route('consultas.faltas-contravenciones', ['filtro' => 'ci', 'search' => '4455667']))->assertJsonPath('total', 0);
        $this->getJson(route('faltas-contravenciones.show', $criminal->id))->assertNotFound();
        $this->get(route('faltas-contravenciones.edit', $criminal->id))->assertNotFound();
        $this->deleteJson(route('faltas-contravenciones.destroy', $criminal->id))->assertNotFound();
        $this->getJson(route('faltas-contravenciones.vista-previa', $criminal->id))->assertNotFound();
        $this->get(route('faltas-contravenciones.showByCodigo', md5((string) $criminal->id)))->assertNotFound();

        $this->assertNotNull($criminal->fresh());
    }

    public function test_los_permisos_de_registro_criminal_no_dan_acceso(): void
    {
        $this->actingAs($this->usuarioConPermisos([]));
        $registro = $this->registro();

        $this->actingAs($this->usuarioConPermisos(['registro_criminal.ver', 'registro_criminal.eliminar']));

        $this->getJson(route('faltas-contravenciones.show', $registro->id))->assertForbidden();
        $this->deleteJson(route('faltas-contravenciones.destroy', $registro->id))->assertForbidden();
        $this->assertNotNull($registro->fresh());
    }

    public function test_la_consulta_queda_auditada_con_el_modulo(): void
    {
        $this->actingAs($this->usuarioConPermisos([]));
        $registro = $this->registro();
        $this->actingAs($this->usuarioCon(['ver', 'consulta']));

        $this->getJson(route('consultas.faltas-contravenciones', ['filtro' => 'ci', 'search' => '4455667', 'identificador' => 'c-1', 'nuevo_filtro' => 1]))
            ->assertJsonPath('total', 1);
        $this->getJson(route('faltas-contravenciones.show', ['registro' => $registro->id, 'identificador' => 'c-1']))->assertOk();

        $auditoria = AuditarConsultas::where('identificador', 'c-1')->firstOrFail();
        $this->assertSame('consulta_faltas_contravenciones', $auditoria->modulo);
        $this->assertSame($registro->id, $auditoria->ids_accedidos[0]['id']);
    }

    public function test_la_ficha_usa_su_plantilla_se_numera_y_genera_su_pdf(): void
    {
        $this->actingAs($this->usuarioConPermisos([]));
        $registro = $this->registro();
        $this->actingAs($this->usuarioCon(['ficha']));

        $this->getJson(route('faltas-contravenciones.vista-previa', $registro->id))
            ->assertOk()
            ->assertJsonPath('registro_id', $registro->id)
            ->assertJsonPath('nro_ficha', '001/'.date('Y'));

        $respuesta = $this->postJson(route('faltas-contravenciones.ficha.store'), [
            'registro_id' => $registro->id,
            'caso_cud' => 'CUD-1',
            'introduccion' => 'Texto 🙂 con emoji',
            'persona' => 'PEDRO',
        ])->assertOk();

        $ficha = $registro->fichas()->firstOrFail();
        $this->assertSame(1, $ficha->numero_ficha);
        $this->assertSame('Texto  con emoji', $ficha->introduccion);

        $this->get($respuesta->json('data.url'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_corregir_una_ficha_exige_que_sea_del_mismo_registro(): void
    {
        $this->actingAs($this->usuarioConPermisos([]));
        $uno = $this->registro();
        $otro = $this->registro();
        $fichaDeUno = $uno->fichas()->create(['caso_cud' => 'ORIGINAL']);
        $this->actingAs($this->usuarioCon(['ficha']));

        $this->postJson(route('faltas-contravenciones.ficha.store'), [
            'registro_id' => $otro->id,
            'codigo' => md5((string) $fichaDeUno->id),
            'caso_cud' => 'CAMBIADO',
        ])->assertOk();

        $this->assertSame('ORIGINAL', $fichaDeUno->fresh()->caso_cud);
        $this->assertSame(1, $otro->fichas()->count());
    }

    public function test_eliminar_borra_registro_fotos_y_fichas_y_sus_archivos(): void
    {
        Storage::fake('public');
        $this->actingAs($this->usuarioConPermisos([]));
        $registro = $this->registro();
        $ruta = 'faltas-contravenciones/img_prueba.webp';
        Storage::disk('public')->put($ruta, 'x');
        $registro->fotos()->create(['tipo' => 'FRONTAL', 'ruta_archivo' => $ruta, 'id_usuario' => auth()->id()]);
        $registro->fichas()->create(['caso_cud' => 'CUD']);

        $this->actingAs($this->usuarioCon(['eliminar']))
            ->deleteJson(route('faltas-contravenciones.destroy', $registro->id))
            ->assertOk()
            ->assertJsonPath('success', 'Registro eliminado exitosamente.');

        $this->assertDatabaseMissing('falta_contravencion', ['id' => $registro->id]);
        $this->assertDatabaseCount('foto_falta_contravencion', 0);
        $this->assertDatabaseCount('ficha_falta_contravencion', 0);
        Storage::disk('public')->assertMissing($ruta);
    }

    public function test_eliminar_o_unir_una_persona_incluye_sus_faltas_y_contravenciones(): void
    {
        $this->actingAs($this->usuarioConPermisos([]));
        $aUnir = $this->registro();
        $aBorrar = $this->registro();
        $aBorrar->fotos()->create(['tipo' => 'FRONTAL', 'ruta_archivo' => 'faltas-contravenciones/img_a.webp', 'id_usuario' => auth()->id()]);
        $aBorrar->fichas()->create(['caso_cud' => 'CUD']);

        // Unir: el registro pasa a otra persona.
        $destino = Persona::factory()->create();
        $aUnir->persona->migrateRelationsTo($destino);
        $this->assertSame($destino->id, $aUnir->fresh()->id_persona);

        // Eliminar completo: se va el registro con su foto y su ficha, y se informa el archivo a borrar.
        $persona = $aBorrar->persona;
        $this->assertSame(1, $persona->getResumenDatosRelacionados()['faltas_contravenciones']);
        $archivos = $persona->deleteRelationsData();
        $persona->forceDelete();

        $this->assertContains('faltas-contravenciones/img_a.webp', $archivos);
        $this->assertDatabaseMissing('falta_contravencion', ['id' => $aBorrar->id]);
        $this->assertDatabaseCount('foto_falta_contravencion', 0);
        $this->assertDatabaseCount('ficha_falta_contravencion', 0);
    }

    public function test_al_editar_se_actualizan_el_estado_civil_y_la_ocupacion_de_la_persona(): void
    {
        $this->actingAs($this->usuarioConPermisos([]));
        $registro = $this->registro();
        $registro->persona->update(['estado_civil' => 'SOLTERO', 'ocupacion' => 'ESTUDIANTE']);

        $this->actingAs($this->usuarioCon(['editar', 'listar']))
            ->put(route('faltas-contravenciones.update', $registro->id), [
                'fecha_registro' => '2026-01-10',
                'id_division' => $registro->id_division,
                'estado_civil' => 'CASADO',
                'ocupacion' => 'COMERCIANTE',
            ])->assertRedirect(route('faltas-contravenciones.index'));

        $this->assertSame('CASADO', $registro->persona->fresh()->estado_civil);
        $this->assertSame('COMERCIANTE', $registro->persona->fresh()->ocupacion);
    }

    public function test_sin_plantilla_propia_la_ficha_usa_el_texto_estandar_con_el_nombre_del_modulo(): void
    {
        $this->actingAs($this->usuarioConPermisos([]));
        $registro = $this->registro();
        DB::table('ficha_falta_contravencion_plantilla')->delete();

        $this->actingAs($this->usuarioCon(['ficha']))
            ->getJson(route('faltas-contravenciones.vista-previa', $registro->id))
            ->assertOk()
            ->assertJsonPath('campos.requerimiento', fn ($texto) => str_contains($texto, 'REGISTRO DE FALTAS Y CONTRAVENCIONES'));
    }
}
