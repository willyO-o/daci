<?php

namespace App\Http\Controllers;

use App\Models\EvidenciaArticulo;
use App\Support\Evidencias\ReglasEvidencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Revisión de la publicación de artículos para el futuro portal ciudadano (`evidencias.publicar`, D-038). Nada se
 * publica solo: el artículo llega aquí como PENDIENTE_REVISION desde el formulario y quien revisa lo aprueba
 * (PUBLICADO, con fecha y autor), lo rechaza (NO_PUBLICAR, con motivo) o, ya publicado, lo retira (RETIRADO, con
 * motivo). Cada cambio queda en la Auditoría de cambios.
 */
class PublicacionEvidenciaController extends Controller
{
    /** Pestañas de la bandeja. */
    private const PESTANAS = [ReglasEvidencia::PENDIENTE, ReglasEvidencia::PUBLICADO, ReglasEvidencia::RETIRADO];

    public function index(Request $request): Response
    {
        $estado = in_array($request->input('estado'), self::PESTANAS, true) ? $request->input('estado') : ReglasEvidencia::PENDIENTE;
        $texto = trim((string) $request->input('search', ''));
        $como = '%'.str_replace('%', ' ', $texto).'%';

        $articulos = EvidenciaArticulo::query()
            ->with([
                'evidencia:id,codigo,fecha_hora,estado,id_caso',
                'evidencia.caso:id,codigo',
                'tipo:id,nombre,grupo',
                'autorizadoPor:id,name',
                'archivos' => fn ($q) => $q->whereIn('tipo', ReglasEvidencia::TIPOS_FOTO)->orderBy('id'),
            ])
            ->whereHas('evidencia')
            ->where('estado_publicacion', $estado)
            ->when($texto !== '', fn (Builder $q) => $q->where(fn (Builder $q2) => $q2
                ->where('imei_1', 'like', $como)->orWhere('imei_2', 'like', $como)->orWhere('numero_serie', 'like', $como)
                ->orWhere('marca', 'like', $como)->orWhere('modelo', 'like', $como)->orWhere('descripcion', 'like', $como)
                ->orWhereHas('evidencia', fn (Builder $e) => $e->where('codigo', 'like', $como))))
            ->orderByDesc($estado === ReglasEvidencia::PUBLICADO ? 'publicado_at' : 'updated_at')
            ->paginate($this->tamanoPagina($request))
            ->withQueryString();

        $totales = EvidenciaArticulo::query()
            ->whereHas('evidencia')
            ->whereIn('estado_publicacion', self::PESTANAS)
            ->selectRaw('estado_publicacion, COUNT(*) as total')
            ->groupBy('estado_publicacion')
            ->pluck('total', 'estado_publicacion');

        return Inertia::render('Evidencias/Publicacion', [
            'articulos' => $articulos,
            'filtros' => ['estado' => $estado, 'search' => $texto],
            'totales' => collect(self::PESTANAS)->mapWithKeys(fn ($e) => [$e => (int) ($totales[$e] ?? 0)]),
            'reglas' => ReglasEvidencia::paraVista(),
        ]);
    }

    public function aprobar(Request $request, EvidenciaArticulo $articulo): RedirectResponse
    {
        if ($error = $this->noPermitido($articulo, [ReglasEvidencia::PENDIENTE, ReglasEvidencia::RETIRADO])) {
            return $error;
        }
        if ($articulo->evidencia->estado === 'ANULADA') {
            return back()->with('error', 'La evidencia está anulada: sus artículos no se publican.');
        }

        $articulo->update([
            'estado_publicacion' => ReglasEvidencia::PUBLICADO,
            'publicado_at' => now(),
            'publicado_por' => $request->user()->id,
            'motivo_publicacion' => null,
        ]);

        return back()->with('success', 'Artículo publicado.');
    }

    public function rechazar(Request $request, EvidenciaArticulo $articulo): RedirectResponse
    {
        if ($error = $this->noPermitido($articulo, [ReglasEvidencia::PENDIENTE])) {
            return $error;
        }
        $articulo->update(['estado_publicacion' => ReglasEvidencia::NO_PUBLICAR, 'motivo_publicacion' => $this->motivo($request)]);

        return back()->with('success', 'Publicación rechazada.');
    }

    public function retirar(Request $request, EvidenciaArticulo $articulo): RedirectResponse
    {
        if ($error = $this->noPermitido($articulo, [ReglasEvidencia::PUBLICADO])) {
            return $error;
        }
        $articulo->update(['estado_publicacion' => ReglasEvidencia::RETIRADO, 'motivo_publicacion' => $this->motivo($request)]);

        return back()->with('success', 'Artículo retirado del portal.');
    }

    /**
     * La acción sólo vale desde ciertos estados (si otro usuario ya lo cambió, se avisa en lugar de pisarlo).
     *
     * @param  list<string>  $permitidos
     */
    private function noPermitido(EvidenciaArticulo $articulo, array $permitidos): ?RedirectResponse
    {
        if (! $articulo->evidencia()->exists()) {
            return back()->with('error', 'La evidencia de este artículo fue eliminada.');
        }

        return in_array($articulo->estado_publicacion, $permitidos, true)
            ? null
            : back()->with('error', 'El artículo cambió de estado: vuelva a cargar la bandeja.');
    }

    private function motivo(Request $request): string
    {
        return trim($request->validate(
            ['motivo' => ['required', 'string', 'min:5', 'max:500']],
            ['motivo.required' => 'Indique el motivo.', 'motivo.min' => 'Indique el motivo (al menos 5 caracteres).'],
        )['motivo']);
    }
}
