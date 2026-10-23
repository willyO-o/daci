<?php

namespace Tests\Feature\RegistroCriminal;

use App\Models\AuditarConsultas;
use App\Models\Division;
use App\Models\FichaRegistro;
use App\Models\FotosRegistro;
use App\Models\Persona;
use App\Models\RegistroCriminal;
use Database\Seeders\PlantillaFichaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistroCriminalTest extends TestCase
{
    use RefreshDatabase;

    private function registro(array $cambios = []): RegistroCriminal
    {
        return RegistroCriminal::create([
            'fecha_registro' => '2026-01-10',
            'id_persona' => Persona::factory()->create(['nombres' => 'PEDRO', 'apellidos' => 'MAMANI'])->id,
            'id_division' => Division::create(['division' => 'DIVISION ROBOS'])->id,
            'alias' => 'EL ZORRO',
            'especialidad' => 'ROBO',
            ...$cambios,
        ]);
    }

    public function test_registra_con_persona_nueva_y_fotos_en_webp(): void
    {
        Storage::fake('public');
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.crear']));
        $division = Division::create(['division' => 'DIVISION HOMICIDIOS']);

        $this->post(route('registro-criminal.store'), [
            'fecha_registro' => '2026-02-01',
            'nombres' => 'JUAN',
            'apellidos' => 'QUISPE',
            'ci' => '8877665',
            'genero' => 'MASCULINO',
            'id_division' => $division->id,
            'telefono' => '76543210',
            'foto_frente' => UploadedFile::fake()->image('frente.jpg', 300, 300),
        ])->assertRedirect(route('registro-criminal.index'));

        $persona = Persona::where('ci', '8877665')->firstOrFail();
        $registro = RegistroCriminal::where('id_persona', $persona->id)->firstOrFail();
        $this->assertSame(1, $registro->nro_registro);
        $this->assertDatabaseHas('telefono', ['numero_celular' => '76543210', 'persona_id' => $persona->id]);

        $foto = FotosRegistro::where('id_registro_criminal', $registro->id)->firstOrFail();
        $this->assertSame('FRONTAL', $foto->tipo);
        $this->assertMatchesRegularExpression('#^registro-criminal/img_.+\.webp$#', $foto->ruta_archivo);
        Storage::disk('public')->assertExists($foto->ruta_archivo);
    }

    public function test_guardar_y_registrar_nuevo_vuelve_al_formulario(): void
    {
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.crear']));
        $persona = Persona::factory()->create();

        $this->from(route('registro-criminal.create'))->post(route('registro-criminal.store'), [
            'fecha_registro' => '2026-02-01',
            'id_persona' => $persona->id,
            'nombres' => $persona->nombres,
            'genero' => 'FEMENINO',
            'id_division' => Division::create(['division' => 'DIVISION X'])->id,
            'accion' => 'guardar_nuevo',
        ])->assertRedirect(route('registro-criminal.create'));

        $this->assertSame(1, RegistroCriminal::where('id_persona', $persona->id)->count());
    }

    public function test_exige_division_y_genero(): void
    {
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.crear']))
            ->post(route('registro-criminal.store'), ['fecha_registro' => '2026-02-01', 'nombres' => 'ANA'])
            ->assertSessionHasErrors(['id_division', 'genero']);
    }

    public function test_la_consulta_queda_auditada(): void
    {
        $this->actingAs($this->usuarioConPermisos(['consultas.registro_criminal', 'registro_criminal.ver']));
        $registro = $this->registro();

        $this->getJson(route('consultas.registro-criminal', ['filtro' => 'alias', 'search' => 'ZORRO', 'identificador' => 'rc-1', 'nuevo_filtro' => 1]))
            ->assertJsonPath('total', 1);
        $this->getJson(route('registro-criminal.show', ['registroCriminal' => $registro->id, 'identificador' => 'rc-1']))->assertOk();

        $auditoria = AuditarConsultas::where('identificador', 'rc-1')->firstOrFail();
        $this->assertSame('consulta_registros', $auditoria->modulo);
        $this->assertSame($registro->id, $auditoria->ids_accedidos[0]['id']);
    }

    public function test_la_ficha_se_numera_y_genera_su_pdf(): void
    {
        $this->seed(PlantillaFichaSeeder::class);
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.ficha']));
        $registro = $this->registro();

        $vista = $this->getJson(route('registro.vista-previa', $registro))->assertOk();
        $this->assertStringContainsString('PEDRO MAMANI', $vista->json('campos.persona'));
        $this->assertStringContainsString('DIVISION ROBOS', $vista->json('campos.resultado_busqueda'));

        $respuesta = $this->postJson(route('ficha-registro.store'), [
            ...$vista->json('campos'),
            'registro_criminal_id' => $registro->id,
        ])->assertOk();

        $ficha = FichaRegistro::firstOrFail();
        $this->assertSame(1, $ficha->numero_ficha);

        $this->get($respuesta->json('data.url'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_sin_permiso_de_ficha_no_genera(): void
    {
        $this->actingAs($this->usuarioConPermisos(['registro_criminal.listar', 'registro_criminal.ver']));
        $registro = $this->registro();

        $this->getJson(route('registro.vista-previa', $registro))->assertForbidden();
    }
}
