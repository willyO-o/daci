<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Tamaño de página de un listado: el que pide la pantalla (`size`, 10 por defecto como el sistema anterior),
     * acotado a 100 para que ninguna petición traiga la tabla entera.
     */
    protected function tamanoPagina(Request $request, int $porDefecto = 10): int
    {
        return min(max($request->integer('size', $porDefecto), 1), 100);
    }
}
