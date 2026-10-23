<?php

namespace App\Http\Controllers;

use App\Models\Multimedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class MultimediaController extends Controller
{
    /**
     * Quita una foto o archivo (de una persona, un mandamiento o un vehículo) y su archivo en el disco.
     */
    public function destroy(Multimedia $multimedia): JsonResponse
    {
        Storage::disk('public')->delete($multimedia->ruta);
        $multimedia->delete();

        return response()->json(['success' => 'Archivo eliminado correctamente.']);
    }
}
