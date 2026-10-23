<?php

namespace App\Http\Controllers;

use App\Models\Juzgado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo de los formularios: el listado alimenta los selects y el alta permite agregar un valor sin salir del
 * formulario (mismo contrato JSON que el sistema anterior: `success`, `message`, `data`).
 */
class JuzgadoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Juzgado::query()->orderBy('nombre_juzgado')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre_juzgado' => ['required', 'string', 'max:255'],
        ]);

        $registro = Juzgado::create(['nombre_juzgado' => campoDB($datos['nombre_juzgado'])]);

        return response()->json(['success' => true, 'message' => 'Juzgado creado correctamente', 'data' => $registro]);
    }
}
