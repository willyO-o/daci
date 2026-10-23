<?php

namespace Tests\Feature\Revision;

use App\Models\AuditarConsultas;
use App\Models\Division;
use App\Models\FichaRegistro;
use App\Models\FotosRegistro;
use App\Models\Imei;
use App\Models\Mandamiento;
use App\Models\Multimedia;
use App\Models\Persona;
use App\Models\RegistroCriminal;
use App\Models\Telefono;
use App\Models\Vehiculo;
use App\Models\VehiculoCaso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regresiones de la revisión del 2026-10-22: borrados que fallaban por claves foráneas, ediciones que perdían
 * vínculos ocultos, datos heredados que no pasaban la validación y permisos de archivos.
 */
class IntegridadDatosTest extends TestCase
{
    use RefreshDatabase;

    private function registroConFotoYFicha(Persona $persona): RegistroCriminal
    {
        $registro = RegistroCriminal::create([
            'fecha_registro' => '2026-01-10',
            'id_persona' => $persona->id,
            'id_division' => Division::create(['division' => 'DIVISION ROBOS'])->id,
        ]);
        Storage::disk('public')->put('registro-criminal/img_prueba.webp', 'x');
        FotosRegistro::create(['tipo' => 'FRONTAL', 'ruta_archivo' => 'registro-criminal/img_prueba.webp', 'id_registro_criminal' => $registro->id, 'id_usuario' => auth()->id()]);
        FichaRegistro::create(['registro_criminal_id' => $registro->id, 'persona' => 'PRUEBA']);

        return $registro;
    }

    public function test_eliminar_completo_borra_una_persona_con_registro_fotos_ficha_y_vehiculo(): void
    {
        Storage::fake('public');
        $this->actingAs($this->usuarioConPermisos(['personas.eliminar']));
        $persona = Persona::factory()->create();
        $registro = $this->registroConFotoYFicha($persona);
        $vehiculo = Vehiculo::create(['placa' => '1111AAA']);
        VehiculoCaso::create(['vehiculo_id' => $vehiculo->id, 'persona_id' => $persona->id, 'registro_criminal_id' => $registro->id, 'tipo' => 'RUAT']);
        Telefono::create(['numero_celular' => '70000001', 'persona_id' => $persona->id]);
        $mandamiento = Mandamiento::create(['id_persona' => $persona->id, 'estado' => 'POR EJECUTAR']);
        Storage::disk('public')->put('mandamientos/acta.jpg', 'x');
        Multimedia::create(['tipo' => 'mandamiento', 'ruta' => 'mandamientos/acta.jpg', 'nombre_archivo' => 'acta.jpg', 'id_mandamiento' => $mandamiento->id]);

        $this->delete(route('personas.destroy', $persona->id), ['eliminar_completo' => 1])
            ->assertSessionHas('success', 'Persona eliminada correctamente.');

        $this->assertDatabaseMissing('persona', ['id' => $persona->id]);
        $this->assertDatabaseMissing('registro_criminal', ['id' => $registro->id]);
        $this->assertDatabaseMissing('ficha_registro', ['registro_criminal_id' => $registro->id]);
        $this->assertDatabaseMissing('vehiculo_caso', ['vehiculo_id' => $vehiculo->id]);
        $this->assertDatabaseMissing('mandamiento', ['id' => $mandamiento->id]);
        $this->assertDatabaseHas('vehiculo', ['id' => $vehiculo->id]);
        Storage::disk('public')->assertMissing('registro-criminal/img_prueba.webp');
        Storage::disk('public')->assertMissing('mandamientos/acta.jpg');
    }

    public function test_no_se_migran_datos_a_una_persona_eliminada(): void
    {
        $this->actingAs($this->usuarioConPermisos(['personas.eliminar']));
        $persona = Persona::factory()->create();
        $eliminada = Persona::factory()->create();
        $eliminada->delete();

        $this->delete(route('personas.destroy', $persona->id), ['persona_migrar_id' => $eliminada->id])
            ->assertSessionHasErrors('persona_migrar_id');

        $this->assertNotSoftDeleted($persona);
    }

    public function test_se_puede_editar_una_persona_con_un_ci_heredado_largo(): void
    {
        $this->actingAs($this->usuarioConPermisos(['personas.editar']));
        $persona = Persona::factory()->create(['ci' => 'DNI 47847529 - E-11608201']);

        $this->put(route('personas.update', $persona), ['nombres' => 'NUEVO', 'ci' => $persona->ci])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('NUEVO', $persona->fresh()->nombres);
    }

    public function test_eliminar_un_registro_con_ficha_y_vehiculo_vinculado(): void
    {
        Storage::fake('public');
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.eliminar']));
        $persona = Persona::factory()->create();
        $registro = $this->registroConFotoYFicha($persona);
        $vinculo = VehiculoCaso::create(['vehiculo_id' => Vehiculo::create(['placa' => '2222BBB'])->id, 'persona_id' => $persona->id, 'registro_criminal_id' => $registro->id, 'tipo' => 'RUAT']);

        $this->deleteJson(route('registro-criminal.destroy', $registro))->assertOk();

        $this->assertDatabaseMissing('registro_criminal', ['id' => $registro->id]);
        $this->assertDatabaseMissing('ficha_registro', ['registro_criminal_id' => $registro->id]);
        $this->assertNull($vinculo->fresh()->registro_criminal_id);
        Storage::disk('public')->assertMissing('registro-criminal/img_prueba.webp');
    }

    public function test_el_registro_rechaza_textos_mas_largos_que_su_columna_y_fotos_que_no_son_imagen(): void
    {
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.crear']))
            ->post(route('registro-criminal.store'), [
                'fecha_registro' => '2026-02-01',
                'nombres' => 'ANA',
                'genero' => 'FEMENINO',
                'id_division' => Division::create(['division' => 'DIVISION X'])->id,
                'especialidad' => str_repeat('A', 251),
                'foto_frente' => UploadedFile::fake()->create('foto.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors(['especialidad', 'foto_frente']);

        $this->assertSame(0, RegistroCriminal::count());
    }

    public function test_editar_un_vehiculo_conserva_el_vinculo_con_una_persona_eliminada(): void
    {
        $this->actingAs($this->usuarioConPermisos(['vehiculos.editar']));
        $vehiculo = Vehiculo::create(['placa' => '3333CCC']);
        $eliminada = Persona::factory()->create();
        $vigente = Persona::factory()->create();
        VehiculoCaso::create(['vehiculo_id' => $vehiculo->id, 'persona_id' => $eliminada->id, 'tipo' => 'RUAT']);
        VehiculoCaso::create(['vehiculo_id' => $vehiculo->id, 'persona_id' => $vigente->id, 'tipo' => 'SOAT']);
        $eliminada->delete();

        // El formulario sólo muestra (y devuelve) a la persona vigente; el usuario la quita.
        $this->put(route('vehiculos.update', $vehiculo), ['placa' => '3333CCC', 'personas_asociadas' => []])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehiculo_caso', ['vehiculo_id' => $vehiculo->id, 'persona_id' => $eliminada->id]);
        $this->assertDatabaseMissing('vehiculo_caso', ['vehiculo_id' => $vehiculo->id, 'persona_id' => $vigente->id]);
    }

    public function test_editar_un_imei_conserva_el_vinculo_con_un_telefono_eliminado(): void
    {
        $this->actingAs($this->usuarioConPermisos(['imeis.editar']));
        $imei = Imei::create(['imei' => '356938035643809']);
        $eliminado = Telefono::create(['numero_celular' => '70000002']);
        $imei->telefonos()->attach($eliminado->id);
        $eliminado->delete();

        $this->put(route('imeis.update', $imei), ['imei' => $imei->imei, 'telefono_id' => []])->assertSessionHas('success');

        $this->assertDatabaseHas('imei_telefono', ['imei_id' => $imei->id, 'telefono_id' => $eliminado->id]);
    }

    public function test_borrar_una_foto_exige_el_permiso_del_registro_al_que_pertenece(): void
    {
        Storage::fake('public');
        $soloVehiculos = $this->usuarioConPermisos(['vehiculos.editar']);
        $this->actingAs($soloVehiculos);
        $persona = Persona::factory()->create();
        $foto = Multimedia::create(['tipo' => 'persona', 'ruta' => 'personas/f.jpg', 'nombre_archivo' => 'f.jpg', 'id_persona' => $persona->id]);

        $this->deleteJson(route('multimedia.destroy', $foto))
            ->assertForbidden();
        $this->assertModelExists($foto);

        $this->actingAs($this->usuarioConPermisos(['personas.editar']))
            ->deleteJson(route('multimedia.destroy', $foto))
            ->assertOk();
        $this->assertModelMissing($foto);
    }

    public function test_un_archivo_que_no_se_puede_leer_se_rechaza_sin_error_500(): void
    {
        $this->actingAs($this->usuarioConPermisos(['importar.vehiculos']))
            ->postJson(route('vehiculos.carguios.importar.store'), [
                'archivo' => UploadedFile::fake()->createWithContent('carguios.xlsx', 'esto no es un excel'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('archivo');
    }

    public function test_un_identificador_ajeno_no_altera_la_auditoria_de_otro_usuario(): void
    {
        $duenio = $this->usuarioConPermisos();
        $auditoria = AuditarConsultas::create(['user_id' => $duenio->id, 'rol_usuario' => 'consultor_felcc', 'modulo' => 'consulta_mandamientos', 'identificador' => 'ajeno-1', 'criterios_consulta' => [], 'user_agent' => [], 'cantidad_resultados' => 0, 'ip_usuario' => '127.0.0.1']);

        $this->actingAs($this->usuarioConPermisos());
        AuditarConsultas::agregarIdsAccedidos('ajeno-1', 99, Mandamiento::class);

        $this->assertNull($auditoria->fresh()->ids_accedidos);
    }
}
