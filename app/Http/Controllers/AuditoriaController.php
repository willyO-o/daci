<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use OwenIt\Auditing\Models\Audit;

/**
 * Auditoría de cambios (owen-it/laravel-auditing): quién creó, modificó, eliminó o restauró cada registro.
 */
class AuditoriaController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const MODELOS = [
        'App\Models\Cargio' => 'Cargío',
        'App\Models\Delito' => 'Delito',
        'App\Models\Division' => 'División',
        'App\Models\EstacionServicio' => 'Estación de Servicio',
        'App\Models\FichaRegistro' => 'Ficha de Registro',
        'App\Models\FotosRegistro' => 'Fotos de Registro',
        'App\Models\Imei' => 'IMEI',
        'App\Models\ImeiTelefono' => 'IMEI-Teléfono',
        'App\Models\InspeccionTecnica' => 'Inspección Técnica',
        'App\Models\Juzgado' => 'Juzgado',
        'App\Models\Mandamiento' => 'Mandamiento',
        'App\Models\Multimedia' => 'Multimedia',
        'App\Models\Pais' => 'País',
        'App\Models\Persona' => 'Persona',
        'App\Models\RegistroCriminal' => 'Registro Criminal',
        'App\Models\Role' => 'Rol (sistema anterior)',
        'App\Models\Rol' => 'Rol',
        'App\Models\Telefono' => 'Teléfono',
        'App\Models\TipoMandamiento' => 'Tipo de Mandamiento',
        'App\Models\User' => 'Usuario',
        'App\Models\Vehiculo' => 'Vehículo',
        'App\Models\VehiculoCaso' => 'Vehículo-Caso',
    ];

    /**
     * @var array<string, string>
     */
    private const EVENTOS = [
        'created' => 'Creado',
        'updated' => 'Actualizado',
        'deleted' => 'Eliminado',
        'restored' => 'Restaurado',
    ];

    public function index(Request $request): Response
    {
        $auditorias = Audit::query()
            ->with('user:id,name,email')
            ->when($request->filled('modelo'), fn (Builder $q) => $q->where('auditable_type', $request->input('modelo')))
            ->when($request->filled('evento'), fn (Builder $q) => $q->where('event', $request->input('evento')))
            ->when($request->filled('usuario_id'), fn (Builder $q) => $q->where('user_id', $request->integer('usuario_id')))
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $texto = $request->input('search');
                $q->where(fn (Builder $q2) => $q2->where('auditable_id', 'like', "%{$texto}%")
                    ->orWhere('ip_address', 'like', "%{$texto}%")
                    ->orWhereHasMorph('user', [User::class], fn (Builder $u) => $u->where('name', 'like', "%{$texto}%")->orWhere('email', 'like', "%{$texto}%")));
            })
            ->when($request->filled('fecha_inicio'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->input('fecha_inicio')))
            ->when($request->filled('fecha_fin'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->input('fecha_fin')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->tamanoPagina($request))
            ->withQueryString()
            ->through(fn (Audit $audit) => [
                'id' => $audit->id,
                'evento' => $audit->event,
                'evento_label' => self::EVENTOS[$audit->event] ?? ucfirst($audit->event),
                'modelo_label' => self::MODELOS[$audit->auditable_type] ?? class_basename($audit->auditable_type),
                'registro_id' => $audit->auditable_id,
                'usuario' => $audit->user?->name ?? 'Sistema',
                'usuario_email' => $audit->user?->email ?? '—',
                'ip' => $audit->ip_address ?? '—',
                'created_at' => $audit->created_at?->format('d/m/Y H:i:s'),
                'campos_nuevos' => count($audit->new_values ?? []),
                'campos_antiguos' => count($audit->old_values ?? []),
            ]);

        return Inertia::render('Auditorias/Index', [
            'auditorias' => $auditorias,
            'modelos' => fn () => Audit::query()->distinct()->orderBy('auditable_type')->pluck('auditable_type')
                ->map(fn (string $tipo) => ['value' => $tipo, 'label' => self::MODELOS[$tipo] ?? class_basename($tipo)]),
            'eventos' => fn () => Audit::query()->distinct()->orderBy('event')->pluck('event')
                ->map(fn (string $evento) => ['value' => $evento, 'label' => self::EVENTOS[$evento] ?? ucfirst($evento)]),
            'filtros' => $request->only(['search', 'modelo', 'evento', 'fecha_inicio', 'fecha_fin']),
        ]);
    }

    public function show(Audit $auditoria): JsonResponse
    {
        $auditoria->load('user:id,name,email');

        return response()->json([
            'id' => $auditoria->id,
            'evento' => $auditoria->event,
            'evento_label' => self::EVENTOS[$auditoria->event] ?? ucfirst($auditoria->event),
            'modelo_label' => self::MODELOS[$auditoria->auditable_type] ?? class_basename($auditoria->auditable_type),
            'registro_id' => $auditoria->auditable_id,
            'usuario' => $auditoria->user?->name ?? 'Sistema',
            'usuario_email' => $auditoria->user?->email ?? '—',
            'ip' => $auditoria->ip_address ?? '—',
            'url' => $auditoria->url ?? '—',
            'user_agent' => $auditoria->user_agent ?? '—',
            'old_values' => $auditoria->old_values ?? [],
            'new_values' => $auditoria->new_values ?? [],
            'created_at' => $auditoria->created_at?->format('d/m/Y H:i:s'),
        ]);
    }
}
