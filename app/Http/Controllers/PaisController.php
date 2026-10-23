<?php

namespace App\Http\Controllers;

use App\Models\Pais;
use Illuminate\Http\JsonResponse;

/**
 * Países y gentilicios para los selects de los formularios de persona (sólo lectura, como el sistema anterior).
 */
class PaisController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Pais::query()->orderBy('pais')->get());
    }
}
