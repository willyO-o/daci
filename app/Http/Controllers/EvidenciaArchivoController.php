<?php

namespace App\Http\Controllers;

use App\Models\EvidenciaArchivo;
use App\Support\Evidencias\ReglasEvidencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Archivos de Evidencias, siempre desde el disco PRIVADO (no tienen URL pública). Ver pide `evidencias.ver` (ruta) y,
 * si es un documento interno (acta, comprobante, documento), además `evidencias.documentos`.
 */
class EvidenciaArchivoController extends Controller
{
    public function show(Request $request, EvidenciaArchivo $archivo): StreamedResponse
    {
        $this->autorizarDocumento($request, $archivo);
        abort_unless($archivo->evidencia()->exists() && Storage::disk(EvidenciaArchivo::DISCO)->exists($archivo->ruta), 404);

        $extension = $archivo->mime === 'application/pdf' ? 'pdf' : 'webp';
        $nombre = pathinfo($archivo->nombre_archivo, PATHINFO_FILENAME).".{$extension}";

        return Storage::disk(EvidenciaArchivo::DISCO)->response($archivo->ruta, $nombre, [
            'Content-Type' => $archivo->mime,
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /**
     * Quitar un archivo al editar (eliminación lógica: el archivo se conserva en el disco).
     */
    public function destroy(Request $request, EvidenciaArchivo $archivo): JsonResponse
    {
        $this->autorizarDocumento($request, $archivo);
        $archivo->delete();

        return response()->json(['success' => true, 'message' => 'Archivo eliminado.']);
    }

    /**
     * Marcar o desmarcar una fotografía como pública (`evidencias.publicar`). Sólo las fotografías del artículo: la del
     * IMEI lo mostraría completo y los documentos son siempre internos.
     */
    public function publico(Request $request, EvidenciaArchivo $archivo): JsonResponse
    {
        $datos = $request->validate(['es_publico' => ['required', 'boolean']]);

        if (! in_array($archivo->tipo, ReglasEvidencia::TIPOS_PUBLICABLES, true) || $archivo->evidencia_articulo_id === null) {
            return response()->json(['message' => 'Sólo una fotografía del artículo puede ser pública.'], 422);
        }

        $archivo->update(['es_publico' => (bool) $datos['es_publico']]);

        return response()->json(['success' => true, 'es_publico' => $archivo->es_publico]);
    }

    private function autorizarDocumento(Request $request, EvidenciaArchivo $archivo): void
    {
        abort_if($archivo->privado && ! $request->user()->can('evidencias.documentos'), 403, 'No tiene permiso para ver los documentos internos de la evidencia.');
    }
}
