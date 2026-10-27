<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Mandamiento;
use App\Models\Persona;
use App\Models\RegistroCriminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Cada pantalla del sistema se arma sin errores para el superadmin y renderiza el componente Vue esperado.
 */
class PaginasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function pantallas(): array
    {
        return [
            'dashboard' => ['dashboard', 'Dashboard/Index'],
            'perfil' => ['perfil', 'Perfil/Index'],
            'usuarios' => ['usuarios.index', 'Usuarios/Index'],
            'roles' => ['roles.index', 'Roles/Index'],
            'personas' => ['personas.index', 'Personas/Index'],
            'mandamientos' => ['mandamientos.index', 'Mandamientos/Index'],
            'registrar mandamiento' => ['mandamientos.create', 'Mandamientos/Registrar'],
            'consulta mandamientos' => ['consultas.mandamientos', 'Consultas/Mandamientos'],
            'consulta registro' => ['consultas.registro-criminal', 'Consultas/RegistroCriminal'],
            'registro criminal' => ['registro-criminal.index', 'RegistroCriminal/Index'],
            'nuevo registro' => ['registro-criminal.create', 'RegistroCriminal/Formulario'],
            'telefonos' => ['telefonos.index', 'Telefonos/Index'],
            'imeis' => ['imeis.index', 'Imeis/Index'],
            'vehiculos' => ['vehiculos.index', 'Vehiculos/Index'],
            'registrar persona' => ['personas.create', 'Personas/Registrar'],
            'registrar telefono' => ['telefonos.create', 'Telefonos/Registrar'],
            'registrar imei' => ['imeis.create', 'Imeis/Registrar'],
            'registrar vehiculo' => ['vehiculos.create', 'Vehiculos/Registrar'],
            'importar mandamientos' => ['importar.mandamientos.index', 'Importar/Mandamientos'],
            'importar personas' => ['personas.importar.index', 'Importar/Personas'],
            'importar telefonos' => ['telefonos.importar.index', 'Importar/Telefonos'],
            'importar vehiculos' => ['vehiculos.importar.index', 'Importar/Vehiculos'],
            'reportes' => ['reportes.index', 'Reportes/Index'],
            'auditoria consultas' => ['auditar-consultas.index', 'AuditarConsultas/Index'],
            'auditoria cambios' => ['auditorias.index', 'Auditorias/Index'],
            'plantilla de la ficha' => ['plantilla-ficha.edit', 'PlantillaFicha/Editar'],
        ];
    }

    #[DataProvider('pantallas')]
    public function test_la_pantalla_carga(string $ruta, string $componente): void
    {
        $this->actingAs($this->superadmin())
            ->get(route($ruta))
            ->assertOk()
            ->assertInertia(fn (Assert $pagina) => $pagina->component($componente));
    }

    public function test_las_fichas_por_codigo_y_la_edicion_cargan(): void
    {
        $this->actingAs($this->superadmin());
        $persona = Persona::factory()->create();
        $mandamiento = Mandamiento::create(['id_persona' => $persona->id, 'estado' => 'PENDIENTE']);
        $registro = RegistroCriminal::create(['fecha_registro' => '2026-01-01', 'id_persona' => $persona->id, 'id_division' => Division::create(['division' => 'D'])->id]);

        $this->get(route('mandamientos.showByCodigo', md5((string) $mandamiento->id)))->assertInertia(fn (Assert $p) => $p->component('Mandamientos/Ver'));
        $this->get(route('registro-criminal.showByCodigo', md5((string) $registro->id)))->assertInertia(fn (Assert $p) => $p->component('RegistroCriminal/Ver'));
        $this->get(route('registro-criminal.edit', $registro))->assertInertia(fn (Assert $p) => $p->component('RegistroCriminal/Formulario'));
        $this->get(route('personas.show', $persona))->assertInertia(fn (Assert $p) => $p->component('Personas/Ver'));
    }

    public function test_los_reportes_se_descargan_en_csv(): void
    {
        $this->actingAs($this->superadmin());
        Persona::factory()->count(2)->create();

        foreach (['mandamientos', 'registro-criminal', 'personas', 'celulares'] as $tipo) {
            $respuesta = $this->get(route('reportes.exportar', ['tipo' => $tipo, 'formato' => 'csv']));
            $respuesta->assertOk();
            $this->assertStringContainsString('text/csv', $respuesta->headers->get('Content-Type'));
            $this->assertNotEmpty($respuesta->streamedContent());
        }
    }
}
