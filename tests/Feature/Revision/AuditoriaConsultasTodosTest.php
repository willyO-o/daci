<?php

namespace Tests\Feature\Revision;

use App\Models\AuditarConsultas;
use App\Models\Delito;
use App\Models\Juzgado;
use App\Models\Mandamiento;
use App\Models\Persona;
use App\Models\Telefono;
use App\Models\TipoMandamiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Auditoría de consultas en todos los módulos: búsquedas en los listados, detalles abiertos y exportaciones, sin
 * filas repetidas y sin interrumpir nunca la consulta del usuario.
 */
class AuditoriaConsultasTodosTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_busqueda_en_personas_queda_registrada_una_sola_vez(): void
    {
        $usuario = $this->usuarioConPermisos(['personas.listar']);
        $this->actingAs($usuario);
        Persona::factory()->count(3)->create(['nombres' => 'JUAN']);

        // Abrir el listado sin filtros, o elegir sólo el campo, no es una consulta.
        $this->get(route('personas.index'))->assertOk();
        $this->get(route('personas.index', ['filtro' => 'nombre']))->assertOk();
        $this->assertSame(0, AuditarConsultas::count());

        $this->get(route('personas.index', ['filtro' => 'nombre', 'search' => 'JUAN']))->assertOk();
        // Paginar o repetir la misma búsqueda no agrega filas.
        $this->get(route('personas.index', ['filtro' => 'nombre', 'search' => 'JUAN', 'page' => 2]))->assertOk();
        $this->get(route('personas.index', ['filtro' => 'nombre', 'search' => 'JUAN']))->assertOk();

        $auditoria = AuditarConsultas::sole();
        $this->assertSame('listado_personas', $auditoria->modulo);
        $this->assertSame($usuario->id, $auditoria->user_id);
        $this->assertSame(['tipo_filtro' => 'nombre', 'busqueda' => 'JUAN'], $auditoria->criterios_consulta);
        $this->assertSame(3, $auditoria->cantidad_resultados);

        // Otra búsqueda sí es una consulta nueva.
        $this->get(route('personas.index', ['filtro' => 'nombre', 'search' => 'PEDRO']))->assertOk();
        $this->assertSame(2, AuditarConsultas::count());
    }

    public function test_buscar_en_telefonos_y_abrir_un_detalle_quedan_registrados(): void
    {
        $this->actingAs($this->usuarioConPermisos(['telefonos.listar', 'telefonos.ver']));
        $telefono = Telefono::create(['numero_celular' => '71234567']);

        $this->get(route('telefonos.index', ['filtro' => 'numero', 'search' => '7123']))->assertOk();
        $this->getJson(route('telefonos.show', $telefono))->assertOk();
        $this->getJson(route('telefonos.show', $telefono))->assertOk();

        $this->assertSame(1, AuditarConsultas::where('modulo', 'listado_telefonos')->count());
        $detalle = AuditarConsultas::where('modulo', 'detalle_telefono')->sole();
        $this->assertSame(['registro' => 'Telefono', 'id' => $telefono->id], $detalle->criterios_consulta);
        $this->assertSame($telefono->id, $detalle->ids_accedidos[0]['id']);
        $this->assertSame(Telefono::class, $detalle->ids_accedidos[0]['modulo']);
    }

    public function test_el_detalle_abierto_desde_una_consulta_se_suma_a_ella_como_antes(): void
    {
        $this->actingAs($this->usuarioConPermisos(['consultas.mandamientos', 'mandamientos.ver']));
        $mandamiento = $this->mandamiento();

        $this->getJson(route('consultas.mandamientos', ['tipo_filtro' => 'hoja_ruta', 'search' => 'HR-300', 'identificador' => 'c-9', 'nuevo_filtro' => 1]))
            ->assertJsonPath('total', 1);
        $this->getJson(route('mandamientos.show', ['mandamiento' => $mandamiento->id, 'identificador' => 'c-9']))->assertOk();

        $this->assertSame(1, AuditarConsultas::count(), 'No debía crear una fila de detalle aparte.');
        $this->assertSame($mandamiento->id, AuditarConsultas::sole()->ids_accedidos[0]['id']);
    }

    public function test_buscar_en_el_listado_de_mandamientos_registra_solo_la_primera_pagina(): void
    {
        $this->actingAs($this->usuarioConPermisos(['mandamientos.listar', 'mandamientos.ver']));
        $mandamiento = $this->mandamiento();

        $this->getJson(route('mandamientos.index', ['tipo_filtro' => 'hoja_ruta', 'search' => 'HR-300', 'page' => 1]))->assertJsonPath('total', 1);
        $this->getJson(route('mandamientos.index', ['tipo_filtro' => 'hoja_ruta', 'search' => 'HR-300', 'page' => 2]))->assertOk();
        // Sin identificador (desde el listado), el detalle queda como acceso propio.
        $this->getJson(route('mandamientos.show', $mandamiento->id))->assertOk();

        $this->assertSame(1, AuditarConsultas::where('modulo', 'listado_mandamientos')->count());
        $this->assertSame(1, AuditarConsultas::where('modulo', 'detalle_mandamiento')->count());
    }

    public function test_exportar_un_reporte_queda_registrado(): void
    {
        $this->actingAs($this->usuarioConPermisos(['reportes.exportar']));
        Persona::factory()->count(2)->create();

        $this->get(route('reportes.exportar', ['tipo' => 'personas']))->assertOk();

        $auditoria = AuditarConsultas::sole();
        $this->assertSame('exportar_reporte', $auditoria->modulo);
        $this->assertSame(['reporte' => 'personas'], $auditoria->criterios_consulta);
        $this->assertSame(2, $auditoria->cantidad_resultados);
    }

    public function test_si_la_auditoria_falla_la_consulta_del_usuario_igual_responde(): void
    {
        $this->actingAs($this->usuarioConPermisos(['personas.listar', 'telefonos.ver']));
        $telefono = Telefono::create(['numero_celular' => '70000001']);
        AuditarConsultas::creating(fn () => throw new \RuntimeException('Base de auditoría no disponible'));

        $this->get(route('personas.index', ['filtro' => 'nombre', 'search' => 'JUAN']))->assertOk();
        $this->getJson(route('telefonos.show', $telefono))->assertOk()->assertJsonPath('numero_celular', '70000001');
        $this->assertSame(0, AuditarConsultas::count());
    }

    public function test_la_pantalla_de_auditoria_muestra_el_nombre_legible_del_modulo(): void
    {
        $this->actingAs($this->usuarioConPermisos(['personas.listar', 'auditoria.consultas']));
        $this->get(route('personas.index', ['filtro' => 'nombre', 'search' => 'JUAN']));

        $this->get(route('auditar-consultas.index'))
            ->assertInertia(fn (Assert $p) => $p->where('etiquetas.listado_personas', 'Búsqueda en Personas')
                ->where('registros.data.0.modulo', 'listado_personas'));
    }

    private function mandamiento(): Mandamiento
    {
        return Mandamiento::create([
            'hoja_ruta' => 'HR-300',
            'estado' => 'POR EJECUTAR',
            'id_persona' => Persona::factory()->create()->id,
            'id_juzgado' => Juzgado::create(['nombre_juzgado' => 'J1', 'estado_juzgado' => 'ACTIVO'])->id,
            'id_delito' => Delito::create(['nombre_delito' => 'ROBO', 'estado_delito' => 'ACTIVO'])->id,
            'id_tipo_mandamiento' => TipoMandamiento::create(['tipo_mandamiento' => 'APREHENSION', 'estado_tipo_mandamiento' => 'ACTIVO'])->id,
        ]);
    }
}
