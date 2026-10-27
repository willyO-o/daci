<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarEstadoMandamientoRequest;
use App\Models\AuditarConsultas;
use App\Models\Delito;
use App\Models\Juzgado;
use App\Models\Mandamiento;
use App\Models\Multimedia;
use App\Models\TipoMandamiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

/**
 * Mandamientos. El listado y la consulta se cargan por páginas en JSON con scroll infinito (como el sistema
 * anterior); las altas y ediciones responden JSON para actualizar la tarjeta sin recargar la lista.
 */
class MandamientoController extends Controller
{
    /**
     * Filtros de búsqueda admitidos (lista blanca de `Mandamiento::getMandamientos`).
     *
     * @var list<string>
     */
    private const FILTROS = ['search', 'tipo_filtro', 'id_delito', 'estado', 'fecha_inicio', 'fecha_fin'];

    public function index(Request $request): JsonResponse|Response
    {
        if ($request->wantsJson()) {
            $respuesta = $this->pagina(Mandamiento::getMandamientos($request->only(self::FILTROS)), $request);

            $this->auditarBusqueda($request, 'listado_mandamientos', [
                'tipo_filtro' => $request->input('tipo_filtro'),
                'busqueda' => $request->input('search'),
                'delito' => $request->input('id_delito'),
                'estado' => $request->input('estado'),
                'fecha_inicio' => $request->input('fecha_inicio'),
                'fecha_fin' => $request->input('fecha_fin'),
            ], (int) $respuesta->getData(true)['total']);

            return $respuesta;
        }

        return Inertia::render('Mandamientos/Index', [
            'estados' => $this->estados(),
            ...$this->catalogos(),
        ]);
    }

    /**
     * Página «Registrar Mandamiento» (la usa el técnico FELCC, que registra pero no ve el listado).
     */
    public function create(): Response
    {
        return Inertia::render('Mandamientos/Registrar', [
            'estados' => $this->estados(),
            ...$this->catalogos(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(Mandamiento::$rules, $this->mensajes());

        $archivosGuardados = [];

        try {
            $mandamiento = DB::transaction(function () use ($request, &$archivosGuardados) {
                $mandamiento = Mandamiento::create($request->only($this->campos()));

                $this->adjuntar($mandamiento, $request->file('imagen_mandamiento'), 'mandamiento', $archivosGuardados);
                $this->adjuntar($mandamiento, $request->file('acta_ejecucion'), 'acta_ejecucion', $archivosGuardados);

                return $mandamiento;
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($archivosGuardados);
            $this->registrarError('registrar', $e, $request);
            $mensaje = 'Error al guardar el mandamiento: '.$e->getMessage();

            return response()->json(['error' => $mensaje, 'message' => $mensaje], 500);
        }

        // Ya está guardado: si falla la consulta no se invita a reintentar (duplicaría el registro).
        $datos = Mandamiento::getMandamientos([], $mandamiento->id)->first();

        if (! $datos) {
            $mensaje = 'El mandamiento se guardó, pero no se pudo mostrar. Recargue la página para verlo; no lo registre de nuevo.';

            return response()->json(['error' => $mensaje, 'message' => $mensaje], 500);
        }

        return response()->json(['success' => 'Mandamiento guardado correctamente.', 'datos' => $this->tarjeta($datos)]);
    }

    /**
     * Detalle: JSON para el modal (y suma el acceso a la auditoría de la consulta en curso).
     */
    public function show(Request $request, int $mandamiento): JsonResponse
    {
        $datos = Mandamiento::getMandamientos([], $mandamiento)->first();

        abort_if(! $datos, 404, 'Mandamiento no encontrado');

        AuditarConsultas::registrarAcceso($request->input('identificador'), $datos, 'detalle_mandamiento');

        return response()->json($this->detalle($datos));
    }

    /**
     * Página de detalle por código (md5 del id): la abren los enlaces de otras fichas y de la auditoría.
     */
    public function showByCodigo(Request $request, string $codigo): Response
    {
        $datos = Mandamiento::getMandamientos([], $codigo, true)->first();

        abort_if(! $datos, 404, 'Mandamiento no encontrado');

        AuditarConsultas::registrarAcceso($request->input('identificador'), $datos, 'detalle_mandamiento');

        return Inertia::render('Mandamientos/Ver', [
            'mandamiento' => $this->detalle($datos),
            'identificador' => $request->input('identificador'),
        ]);
    }

    public function update(Request $request, Mandamiento $mandamiento): JsonResponse
    {
        $request->validate(Mandamiento::$rules, $this->mensajes());

        $archivosGuardados = [];
        $archivosReemplazados = [];

        try {
            DB::transaction(function () use ($request, $mandamiento, &$archivosGuardados, &$archivosReemplazados) {
                if ($request->hasFile('imagen_mandamiento')) {
                    $anterior = Multimedia::where('id_mandamiento', $mandamiento->id)->where('tipo', 'mandamiento')->first();
                    $this->adjuntar($mandamiento, $request->file('imagen_mandamiento'), 'mandamiento', $archivosGuardados);

                    if ($anterior) {
                        $archivosReemplazados[] = $anterior->ruta;
                        $anterior->delete();
                    }
                }

                // El acta anterior se conserva (el sistema anterior nunca la borraba); se muestra la más reciente.
                $this->adjuntar($mandamiento, $request->file('acta_ejecucion'), 'acta_ejecucion', $archivosGuardados);

                $mandamiento->update($request->only($this->campos()));
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($archivosGuardados);
            $this->registrarError('actualizar', $e, $request);
            $mensaje = 'Error al actualizar el mandamiento: '.$e->getMessage();

            return response()->json(['error' => $mensaje, 'message' => $mensaje], 500);
        }

        // El archivo reemplazado se borra del disco sólo cuando la base ya confirmó el cambio.
        Storage::disk('public')->delete($archivosReemplazados);

        $datos = Mandamiento::getMandamientos([], $mandamiento->id)->first();

        return response()->json(['success' => 'Mandamiento actualizado correctamente.', 'datos' => $datos ? $this->tarjeta($datos) : null]);
    }

    /**
     * Sólo el estado de ejecución (botón de Consultas › Mandamientos). Con «EJECUTADO» guarda también fecha, quién
     * lo ejecutó, el detalle y el acta; con otro estado no toca los demás datos de ejecución.
     */
    public function actualizarEstado(ActualizarEstadoMandamientoRequest $request, Mandamiento $mandamiento): JsonResponse
    {
        $archivosGuardados = [];

        try {
            DB::transaction(function () use ($request, $mandamiento, &$archivosGuardados) {
                if ($request->esEjecutado()) {
                    // El acta anterior se conserva, igual que al editar; se muestra la más reciente.
                    $this->adjuntar($mandamiento, $request->file('acta_ejecucion'), 'acta_ejecucion', $archivosGuardados);
                }

                $mandamiento->update($request->camposAGuardar());
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($archivosGuardados);
            $this->registrarError('actualizar el estado', $e, $request);
            $mensaje = 'Error al actualizar el estado: '.$e->getMessage();

            return response()->json(['error' => $mensaje, 'message' => $mensaje], 500);
        }

        $datos = Mandamiento::getMandamientos([], $mandamiento->id)->first();

        return response()->json(['success' => 'Estado actualizado correctamente.', 'datos' => $datos ? $this->tarjeta($datos) : null]);
    }

    public function destroy(Mandamiento $mandamiento): JsonResponse
    {
        $archivos = $mandamiento->multimedia()->pluck('ruta')->all();

        DB::transaction(function () use ($mandamiento) {
            $mandamiento->multimedia()->delete();
            $mandamiento->forceDelete();
        });

        // El sistema anterior borraba los registros pero dejaba los archivos en el disco.
        Storage::disk('public')->delete($archivos);

        return response()->json(['success' => 'Mandamiento eliminado correctamente.']);
    }

    /**
     * Consulta de mandamientos: exige un filtro y al menos 4 caracteres, y cada búsqueda nueva queda registrada en
     * la auditoría de consultas con su identificador.
     */
    public function consultarMandamientos(Request $request): JsonResponse|Response
    {
        if (! $request->wantsJson()) {
            return Inertia::render('Consultas/Mandamientos', [
                // Sugerencias del modal «Estado de ejecución»: los estados más usados primero.
                'estados' => $request->user()->can('mandamientos.estado')
                    ? Mandamiento::query()->whereNotNull('estado')->where('estado', '!=', '')
                        ->groupBy('estado')->orderByRaw('COUNT(*) DESC')->orderBy('estado')
                        ->limit(30)->pluck('estado')
                    : [],
            ]);
        }

        if (blank($request->input('tipo_filtro')) || mb_strlen((string) $request->input('search')) < 4) {
            return response()->json(['datos' => [], 'total' => 0, 'page' => 1, 'last_page' => 1]);
        }

        $respuesta = $this->pagina(Mandamiento::getMandamientos($request->only(self::FILTROS)), $request);

        if ($request->boolean('nuevo_filtro')) {
            $request->merge(['cantidad_resultados' => $respuesta->getData(true)['total']]);
            AuditarConsultas::registrar($request->user(), 'consulta_mandamientos', $request);
        }

        return $respuesta;
    }

    /**
     * @param  Builder<Mandamiento>  $consulta
     */
    private function pagina($consulta, Request $request): JsonResponse
    {
        $pagina = $consulta->paginate($this->tamanoPagina($request, 6));

        return response()->json([
            'datos' => collect($pagina->items())->map(fn (Mandamiento $m) => $this->tarjeta($m))->values(),
            'total' => $pagina->total(),
            'page' => $pagina->currentPage(),
            'last_page' => $pagina->lastPage(),
        ]);
    }

    /**
     * Datos de la tarjeta del listado (la fila de `getMandamientos` con los JSON ya decodificados).
     *
     * @return array<string, mixed>
     */
    private function tarjeta(Mandamiento $mandamiento): array
    {
        return [
            ...$mandamiento->only([
                'id', 'hoja_ruta', 'estado', 'tipo_documento', 'asignado', 'telefono', 'domicilio', 'vehiculos',
                'actividades_realizadas', 'detalle_ejecucion', 'ejecutado_por', 'id_persona', 'id_juzgado',
                'id_delito', 'id_tipo_mandamiento', 'nombre_completo', 'ci', 'nombre_delito', 'nombre_juzgado',
                'tipo_mandamiento',
            ]),
            'fecha_ejecucion' => $mandamiento->fecha_ejecucion?->toDateString(),
            'created_at' => $mandamiento->created_at?->toIso8601String(),
            'archivo_mandamiento' => $this->json($mandamiento->getAttribute('archivo_mandamiento')),
            'acta_ejecucion' => $this->json($mandamiento->getAttribute('acta_ejecucion')),
            'imagenes_persona' => array_values(array_filter((array) $this->json($mandamiento->getAttribute('imagenes_persona')))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detalle(Mandamiento $mandamiento): array
    {
        $persona = $mandamiento->persona()->with(['multimedia:id,id_persona,ruta', 'pais:id,gentilicio'])->first();

        return [
            ...$this->tarjeta($mandamiento),
            'codigo' => md5((string) $mandamiento->id),
            'persona' => $persona ? [
                ...$persona->toArray(),
                'fecha_nacimiento' => $persona->fecha_nacimiento?->toDateString(),
                'edad' => $persona->fecha_nacimiento?->age,
                'nacionalidad' => $persona->pais?->gentilicio,
            ] : null,
        ];
    }

    private function json(mixed $valor): mixed
    {
        return is_string($valor) ? json_decode($valor, true) : $valor;
    }

    /**
     * Guarda un adjunto en las carpetas de siempre: imagen o PDF del mandamiento y del acta de ejecución.
     *
     * @param  list<string>  $archivosGuardados
     */
    private function adjuntar(Mandamiento $mandamiento, ?UploadedFile $archivo, string $tipo, array &$archivosGuardados): void
    {
        if (! $archivo) {
            return;
        }

        $extension = $archivo->getClientOriginalExtension();
        $esPdf = strtolower($extension) === 'pdf';
        $directorio = $tipo === 'mandamiento'
            ? ($esPdf ? 'mandamientos-pdf' : 'mandamientos')
            : ($esPdf ? 'mandamientos-pdf-actas' : 'mandamientos-img-actas');

        $nombreArchivo = $archivo->hashName();
        $ruta = $archivo->storeAs($directorio, $nombreArchivo, 'public');

        if ($ruta === false || ! Storage::disk('public')->exists($ruta)) {
            throw new RuntimeException($tipo === 'mandamiento' ? 'Error al guardar la imagen del mandamiento.' : 'Error al guardar el acta de ejecución.');
        }

        $archivosGuardados[] = $ruta;

        Multimedia::create([
            'tipo' => $tipo,
            'ruta' => $ruta,
            'nombre_archivo' => $nombreArchivo,
            'id_mandamiento' => $mandamiento->id,
            'tipo_archivo' => $extension,
        ]);
    }

    /**
     * @return list<string>
     */
    private function campos(): array
    {
        return [
            'hoja_ruta', 'estado', 'fecha_ejecucion', 'detalle_ejecucion', 'asignado', 'tipo_documento',
            'actividades_realizadas', 'id_juzgado', 'id_delito', 'id_tipo_mandamiento', 'id_persona', 'telefono',
            'vehiculos', 'domicilio', 'ejecutado_por',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mensajes(): array
    {
        return [
            'ejecutado_por.required_if' => 'Indique quién ejecutó el mandamiento.',
            'fecha_ejecucion.before_or_equal' => 'La fecha de ejecución no puede ser posterior a hoy.',
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function estados()
    {
        return Mandamiento::query()->whereNotNull('estado')->distinct()->orderBy('estado')->pluck('estado');
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'juzgados' => Juzgado::query()->orderBy('nombre_juzgado')->get(['id', 'nombre_juzgado']),
            'delitos' => Delito::query()->orderBy('nombre_delito')->get(['id', 'nombre_delito']),
            'tiposMandamiento' => TipoMandamiento::query()->orderBy('tipo_mandamiento')->get(['id', 'tipo_mandamiento']),
        ];
    }

    /**
     * Deja en el log el detalle de un error al guardar (sin datos personales ni contenido de archivos).
     */
    private function registrarError(string $accion, Throwable $e, Request $request): void
    {
        $archivos = [];

        foreach (['imagen_mandamiento', 'acta_ejecucion'] as $campo) {
            $archivo = $request->file($campo);

            if ($archivo) {
                $archivos[$campo] = [
                    'nombre' => $archivo->getClientOriginalName(),
                    'extension' => $archivo->getClientOriginalExtension(),
                    'bytes' => $archivo->getSize(),
                    'valido' => $archivo->isValid(),
                    'error_subida' => $archivo->isValid() ? null : $archivo->getErrorMessage(),
                ];
            }
        }

        Log::error("Mandamiento: error al {$accion}", [
            'usuario_id' => auth()->id(),
            'url' => $request->fullUrl(),
            'ids' => $request->only(['id_persona', 'id_juzgado', 'id_delito', 'id_tipo_mandamiento', 'estado']),
            'archivos' => $archivos,
            'exception' => $e,
        ]);
    }
}
