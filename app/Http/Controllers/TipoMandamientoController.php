<?php

namespace App\Http\Controllers;

use App\Models\TipoMandamiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo de los formularios: el listado alimenta los selects y el alta permite agregar un valor sin salir del
 * formulario (mismo contrato JSON que el sistema anterior: `success`, `message`, `data`).
 */
class TipoMandamientoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(TipoMandamiento::query()->orderBy('tipo_mandamiento')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo_mandamiento' => ['required', 'string', 'max:255'],
        ]);

        $registro = TipoMandamiento::create(['tipo_mandamiento' => campoDB($datos['tipo_mandamiento'])]);

        return response()->json(['success' => true, 'message' => 'Tipo de mandamiento creado correctamente', 'data' => $registro]);
    }
}
