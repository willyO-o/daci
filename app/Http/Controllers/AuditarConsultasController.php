<?php

namespace App\Http\Controllers;

use App\Models\AuditarConsultas;
use App\Support\AccesosConsultados;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Auditoría de consultas: cada búsqueda hecha en Consultar Mandamientos / Registro Criminal, con sus criterios,
 * cantidad de resultados, los registros que se abrieron y el dispositivo.
 */
class AuditarConsultasController extends Controller
{
    public function index(Request $request): Response
    {
        $registros = AuditarConsultas::query()
            ->with('usuario:id,name,email')
            ->when($request->filled('rol_usuario'), fn (Builder $q) => $q->where('rol_usuario', $request->input('rol_usuario')))
            ->when($request->filled('modulo'), fn (Builder $q) => $q->where('modulo', $request->input('modulo')))
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $texto = $request->input('search');
                $q->where(fn (Builder $q2) => $q2->whereHas('usuario', fn (Builder $u) => $u->where('name', 'like', "%{$texto}%")->orWhere('email', 'like', "%{$texto}%"))
                    ->orWhere('identificador', 'like', "%{$texto}%"));
            })
            ->when($request->filled('fecha_inicio'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->input('fecha_inicio')))
            ->when($request->filled('fecha_fin'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->input('fecha_fin')))
            ->orderByDesc('created_at')
            ->paginate($this->tamanoPagina($request))
            ->withQueryString()
            ->through(fn (AuditarConsultas $registro) => [
                'id' => $registro->id,
                'usuario' => $registro->usuario?->only(['name', 'email']),
                'rol_usuario' => $registro->rol_usuario,
                'modulo' => $registro->modulo,
                'cantidad_resultados' => $registro->cantidad_resultados,
                'accesos' => count($registro->ids_accedidos ?? []),
                'user_agent' => $registro->user_agent,
                'created_at' => $registro->created_at?->toIso8601String(),
            ]);

        return Inertia::render('AuditarConsultas/Index', [
            'registros' => $registros,
            'modulos' => fn () => AuditarConsultas::query()->distinct()->orderBy('modulo')->pluck('modulo'),
            'roles' => fn () => AuditarConsultas::query()->distinct()->orderBy('rol_usuario')->pluck('rol_usuario'),
            'etiquetas' => AuditarConsultas::ETIQUETAS,
            'filtros' => $request->only(['search', 'modulo', 'rol_usuario', 'fecha_inicio', 'fecha_fin']),
        ]);
    }

    public function show(AuditarConsultas $auditarConsulta): JsonResponse
    {
        $auditarConsulta->load('usuario:id,name,email');

        return response()->json([
            ...$auditarConsulta->toArray(),
            'accesos' => AccesosConsultados::resolver($auditarConsulta->ids_accedidos),
            'ubicacion' => $auditarConsulta->getLocationInfo(),
        ]);
    }
}
