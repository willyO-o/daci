<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarEvidenciaRequest;
use App\Models\AuditarConsultas;
use App\Models\Division;
use App\Models\Evidencia;
use App\Models\EvidenciaArchivo;
use App\Models\EvidenciaArticulo;
use App\Models\Imei;
use App\Models\TipoArticulo;
use App\Support\Evidencias\GuardadoEvidencia;
use App\Support\Evidencias\ReglasEvidencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Módulo Evidencias (D-038): listado con filtros en el servidor, alta y edición (evidencia + artículos + archivos en
 * una transacción, `GuardadoEvidencia`), detalle con sus coincidencias de IMEI y eliminación lógica.
 */
class EvidenciaController extends Controller
{
    public function __construct(private readonly GuardadoEvidencia $guardado) {}

    public function index(Request $request): Response
    {
        $filtros = $request->only(['codigo', 'cud', 'desde', 'hasta', 'tipo', 'estado', 'funcionario', 'division', 'imei']);
        $como = fn (string $campo) => '%'.str_replace('%', ' ', trim((string) $filtros[$campo])).'%';

        $evidencias = Evidencia::query()
            ->with(['caso:id,codigo', 'division:id,division'])
            ->withCount([
                'articulos',
                'articulos as publicados_count' => fn (Builder $q) => $q->where('estado_publicacion', ReglasEvidencia::PUBLICADO),
                'articulos as pendientes_count' => fn (Builder $q) => $q->where('estado_publicacion', ReglasEvidencia::PENDIENTE),
            ])
            ->when(filled($filtros['codigo'] ?? null), fn (Builder $q) => $q->where(fn (Builder $q2) => $q2->where('codigo', 'like', $como('codigo'))->orWhere('numero_acta', 'like', $como('codigo'))))
            ->when(filled($filtros['cud'] ?? null), fn (Builder $q) => $q->whereHas('caso', fn (Builder $c) => $c->where('codigo', 'like', $como('cud'))))
            ->when(filled($filtros['desde'] ?? null), fn (Builder $q) => $q->whereDate('fecha_hora', '>=', $filtros['desde']))
            ->when(filled($filtros['hasta'] ?? null), fn (Builder $q) => $q->whereDate('fecha_hora', '<=', $filtros['hasta']))
            ->when(filled($filtros['tipo'] ?? null), fn (Builder $q) => $q->whereHas('articulos', fn (Builder $a) => $a->where('tipo_articulo_id', (int) $filtros['tipo'])))
            ->when(in_array($filtros['estado'] ?? null, ReglasEvidencia::ESTADOS, true), fn (Builder $q) => $q->where('estado', $filtros['estado']))
            ->when(filled($filtros['funcionario'] ?? null), fn (Builder $q) => $q->where(fn (Builder $q2) => $q2->where('funcionario_responsable', 'like', $como('funcionario'))
                ->orWhereHas('articulos', fn (Builder $a) => $a->where('funcionario_recoge', 'like', $como('funcionario')))))
            ->when(filled($filtros['division'] ?? null), fn (Builder $q) => $q->where('id_division', (int) $filtros['division']))
            ->when(filled($filtros['imei'] ?? null), fn (Builder $q) => $q->whereHas('articulos', fn (Builder $a) => $a->where(fn (Builder $a2) => $a2
                ->where('imei_1', 'like', $como('imei'))->orWhere('imei_2', 'like', $como('imei'))->orWhere('numero_serie', 'like', $como('imei')))))
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->paginate($this->tamanoPagina($request))
            ->withQueryString();

        $this->auditarBusqueda($request, 'listado_evidencias', array_filter($filtros, 'filled'), $evidencias->total());

        return Inertia::render('Evidencias/Index', [
            'evidencias' => $evidencias,
            'filtros' => $filtros,
            ...$this->catalogos(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Evidencias/Formulario', ['evidencia' => null, ...$this->catalogos(true)]);
    }

    public function store(GuardarEvidenciaRequest $request): RedirectResponse
    {
        $evidencia = $this->guardado->guardar($request->validated(), $request->allFiles());

        return $this->despuesDeGuardar($request, $evidencia, 'Evidencia registrada correctamente.');
    }

    public function show(Request $request, Evidencia $evidencia): Response
    {
        AuditarConsultas::registrarAcceso($request->input('identificador'), $evidencia, 'detalle_evidencia');

        $evidencia->load([
            'caso:id,codigo',
            'division:id,division',
            'usuario:id,name',
            'articulos' => fn ($q) => $q->with(['tipo:id,nombre,grupo', 'autorizadoPor:id,name'])->orderBy('id'),
        ]);

        return Inertia::render('Evidencias/Ver', [
            'evidencia' => $evidencia,
            'archivos' => $this->archivosVisibles($request, $evidencia),
            'coincidencias' => $this->coincidencias($evidencia->articulos, $evidencia->id),
            'reglas' => ReglasEvidencia::paraVista(),
        ]);
    }

    public function edit(Request $request, Evidencia $evidencia): Response
    {
        $evidencia->load(['caso:id,codigo', 'articulos' => fn ($q) => $q->orderBy('id')]);

        return Inertia::render('Evidencias/Formulario', [
            'evidencia' => [
                ...$evidencia->toArray(),
                'fecha_hora' => $evidencia->fecha_hora?->format('Y-m-d\TH:i'),
                'caso_codigo' => $evidencia->caso?->codigo,
            ],
            'archivos' => $this->archivosVisibles($request, $evidencia),
            ...$this->catalogos(true, $evidencia->articulos->pluck('tipo_articulo_id')->all()),
        ]);
    }

    public function update(GuardarEvidenciaRequest $request, Evidencia $evidencia): RedirectResponse
    {
        $this->guardado->guardar($request->validated(), $request->allFiles(), $evidencia);

        return $this->despuesDeGuardar($request, $evidencia, 'Evidencia actualizada correctamente.');
    }

    /**
     * Eliminación lógica de la evidencia, sus artículos y sus archivos (los archivos no se borran del disco). Antes se
     * retiran sus publicaciones.
     */
    public function destroy(Evidencia $evidencia): RedirectResponse
    {
        DB::transaction(function () use ($evidencia) {
            GuardadoEvidencia::retirarPublicaciones($evidencia, 'Evidencia eliminada.');
            $evidencia->archivos()->get()->each->delete();
            $evidencia->articulos()->get()->each->delete();
            $evidencia->delete();
        });

        return redirect()->route('evidencias.index')->with('success', 'Evidencia eliminada correctamente.');
    }

    private function despuesDeGuardar(Request $request, Evidencia $evidencia, string $mensaje): RedirectResponse
    {
        if ($request->input('accion') === 'guardar_nuevo') {
            return redirect()->route('evidencias.create')->with('success', $mensaje);
        }

        return $request->user()->can('evidencias.ver')
            ? redirect()->route('evidencias.show', $evidencia)->with('success', $mensaje)
            : redirect()->route('evidencias.create')->with('success', $mensaje);
    }

    /**
     * Archivos de la evidencia (y de sus artículos) que el usuario puede ver: los documentos internos sólo con
     * `evidencias.documentos`; de los demás se informa cuántos hay.
     *
     * @return array{visibles: Collection<int, EvidenciaArchivo>, ocultos: int}
     */
    private function archivosVisibles(Request $request, Evidencia $evidencia): array
    {
        $archivos = $evidencia->archivos()->orderBy('id')->get();
        $verDocumentos = $request->user()->can('evidencias.documentos');
        $visibles = $archivos->filter(fn (EvidenciaArchivo $a) => $verDocumentos || ! $a->privado)->values();

        return ['visibles' => $visibles, 'ocultos' => $archivos->count() - $visibles->count()];
    }

    /**
     * IMEI o número de serie de estos artículos que también figuran en otra evidencia o en la tabla de IMEIs de las
     * investigaciones (sólo lectura).
     *
     * @param  Collection<int, EvidenciaArticulo>  $articulos
     * @return array<int, array{imeis: list<string>, otras: list<array{id: int, codigo: ?string}>}>
     */
    private function coincidencias(Collection $articulos, int $evidenciaId): array
    {
        $imeis = $articulos->flatMap(fn ($a) => [$a->imei_1, $a->imei_2])->filter()->unique()->values();
        $series = $articulos->pluck('numero_serie')->filter()->unique()->values();

        if ($imeis->isEmpty() && $series->isEmpty()) {
            return [];
        }

        $enInvestigacion = $imeis->isEmpty() ? collect() : Imei::query()->whereIn('imei', $imeis)->pluck('imei')->flip();
        $otros = EvidenciaArticulo::query()
            ->with('evidencia:id,codigo')
            ->where('evidencia_id', '<>', $evidenciaId)
            ->where(fn (Builder $q) => $q->whereIn('imei_1', $imeis)->orWhereIn('imei_2', $imeis)->orWhereIn('numero_serie', $series))
            ->whereHas('evidencia')
            ->limit(200)
            ->get(['id', 'evidencia_id', 'imei_1', 'imei_2', 'numero_serie']);

        $resultado = [];
        foreach ($articulos as $articulo) {
            $propios = array_filter([$articulo->imei_1, $articulo->imei_2]);
            $otras = $otros->filter(fn ($o) => array_intersect($propios, array_filter([$o->imei_1, $o->imei_2]))
                || ($articulo->numero_serie && $o->numero_serie === $articulo->numero_serie))
                ->map(fn ($o) => ['id' => $o->evidencia_id, 'codigo' => $o->evidencia?->codigo])
                ->unique('id')->values()->all();
            $investigacion = array_values(array_filter($propios, fn ($i) => $enInvestigacion->has($i)));

            if ($otras || $investigacion) {
                $resultado[$articulo->id] = ['imeis' => $investigacion, 'otras' => $otras];
            }
        }

        return $resultado;
    }

    /**
     * Para los formularios, sólo los tipos activos y los que ya usan los artículos que se editan (aunque se hayan
     * inactivado después).
     *
     * @param  list<int>  $tiposEnUso
     * @return array<string, mixed>
     */
    private function catalogos(bool $soloActivos = false, array $tiposEnUso = []): array
    {
        return [
            'tipos' => TipoArticulo::query()
                ->when($soloActivos, fn (Builder $q) => $q->where(fn (Builder $q2) => $q2->where('estado', 'ACTIVO')->orWhereIn('id', $tiposEnUso)))
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'grupo', 'estado']),
            'divisiones' => Division::query()->orderBy('division')->get(['id', 'division']),
            'reglas' => ReglasEvidencia::paraVista(),
        ];
    }
}
