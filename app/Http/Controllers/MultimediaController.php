<?php

namespace App\Http\Controllers;

use App\Models\Multimedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MultimediaController extends Controller
{
    /**
     * Quita una foto o archivo (de una persona, un mandamiento o un vehículo) y su archivo en el disco. Sólo quien
     * puede editar el registro dueño del archivo (la ruta admite cualquiera de los tres permisos).
     */
    public function destroy(Request $request, Multimedia $multimedia): JsonResponse
    {
        $permiso = match (true) {
            $multimedia->id_vehiculo !== null => 'vehiculos.editar',
            $multimedia->id_mandamiento !== null => 'mandamientos.editar',
            default => 'personas.editar',
        };

        abort_unless($request->user()->can($permiso), 403, 'No tiene permiso para eliminar este archivo.');

        $multimedia->delete();
        Storage::disk('public')->delete($multimedia->ruta);

        return response()->json(['success' => 'Archivo eliminado correctamente.']);
    }
}
