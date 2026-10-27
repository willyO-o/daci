<?php

namespace App\Http\Controllers;

use App\Models\AuditarConsultas;
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

    /**
     * Registra en la Auditoría de consultas una búsqueda hecha en un listado. Sólo la primera página (seguir bajando o
     * paginar no es una consulta nueva) y sólo si hay algún criterio (lo decide `AuditarConsultas::registrarBusqueda`).
     *
     * @param  array<string, mixed>  $criterios
     */
    protected function auditarBusqueda(Request $request, string $modulo, array $criterios, int $resultados): void
    {
        if ($request->integer('page', 1) > 1 || ! $request->user()) {
            return;
        }

        AuditarConsultas::registrarBusqueda($request->user(), $modulo, $criterios, $resultados);
    }
}
