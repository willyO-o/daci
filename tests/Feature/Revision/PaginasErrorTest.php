<?php

namespace Tests\Feature\Revision;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Las páginas de error usan el diseño de la plantilla (como la 404 del sistema anterior), sin cambiar el código
 * HTTP ni la respuesta JSON de las peticiones AJAX.
 */
class PaginasErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_pagina_inexistente_muestra_la_404_de_la_plantilla(): void
    {
        $this->actingAs($this->usuarioConPermisos())
            ->get('/esta-pagina-no-existe')
            ->assertNotFound()
            ->assertInertia(fn (Assert $pagina) => $pagina->component('Errores/Error')->where('estado', 404));
    }

    public function test_sin_permiso_muestra_la_403_de_la_plantilla(): void
    {
        $this->actingAs($this->usuarioConPermisos())
            ->get(route('personas.index'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $pagina) => $pagina->component('Errores/Error')->where('estado', 403));
    }

    public function test_las_peticiones_json_siguen_recibiendo_json(): void
    {
        $this->actingAs($this->usuarioConPermisos())
            ->getJson(route('personas.show', 999999))
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }
}
