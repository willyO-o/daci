<?php

namespace App\Http\Controllers;

use App\Models\Division;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo de los formularios: el listado alimenta los selects y el alta permite agregar un valor sin salir del
 * formulario (mismo contrato JSON que el sistema anterior: `success`, `message`, `data`).
 */
class DivisionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Division::query()->orderBy('division')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'division' => ['required', 'string', 'max:255'],
        ]);

        $registro = Division::create(['division' => campoDB($datos['division'])]);

        return response()->json(['success' => true, 'message' => 'División creada correctamente', 'data' => $registro]);
    }
}
