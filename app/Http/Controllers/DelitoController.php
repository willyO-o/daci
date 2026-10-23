<?php

namespace App\Http\Controllers;

use App\Models\Delito;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo de los formularios: el listado alimenta los selects y el alta permite agregar un valor sin salir del
 * formulario (mismo contrato JSON que el sistema anterior: `success`, `message`, `data`).
 */
class DelitoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Delito::query()->orderBy('nombre_delito')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre_delito' => ['required', 'string', 'max:255'],
        ]);

        $registro = Delito::create(['nombre_delito' => campoDB($datos['nombre_delito'])]);

        return response()->json(['success' => true, 'message' => 'Delito creado correctamente', 'data' => $registro]);
    }
}
