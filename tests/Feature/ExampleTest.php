<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_lleva_al_inicio_de_sesion(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
