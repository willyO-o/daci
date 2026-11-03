<?php

namespace App\Http\Controllers;

use App\Models\AuditarConsultas;
use App\Models\Caso;
use App\Support\Casos\MigradorCasos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Módulo Casos (D-037): listado, detalle con lo vinculado por `id_caso`, alta, edición y eliminación. Cada sección del
 * detalle (teléfonos, registros, faltas, vehículos) sólo se carga si el usuario puede ver ese módulo.
 */
class CasoController extends Controller
{
    private const ESTADOS = ['ACTIVO', 'INACTIVO'];

    /** Filas que se muestran por sección en el detalle (un caso puede tener miles de teléfonos). */
    private const POR_SECCION = 100;

    /**
     * Relación de `Caso` => permiso para verla.
     *
     * @var array<string, string>
     */
    private const SECCIONES = [
        'telefonos' => 'telefonos.ver',
        'registrosCriminales' => 'registro_criminal.ver',
        'faltasContravenciones' => 'faltas_contravenciones.ver',
        'vehiculos' => 'vehiculos.ver',
        'vinculosVehiculo' => 'vehiculos.ver',
    ];

    public function index(Request $request): Response
    {
        $texto = trim((string) $request->input('search', ''));
        $estado = in_array($request->input('estado'), self::ESTADOS, true) ? $request->input('estado') : null;

        $casos = Caso::query()
            ->withCount($this->seccionesVisibles($request))
            ->when($texto !== '', function (Builder $q) use ($texto) {
                $como = '%'.str_replace('%', ' ', $texto).'%';
                $q->where(fn (Builder $q2) => $q2->where('codigo', 'like', $como)->orWhere('descripcion', 'like', $como));
            })
            ->when($estado, fn (Builder $q) => $q->where('estado', $estado))
            ->orderByDesc('id')
            ->paginate($this->tamanoPagina($request))
            ->withQueryString();

        $this->auditarBusqueda($request, 'listado_casos', ['busqueda' => $texto, 'estado' => $estado], $casos->total());

        return Inertia::render('Casos/Index', [
            'casos' => $casos,
            'filtros' => $request->only(['search', 'estado']),
            'estados' => self::ESTADOS,
        ]);
    }

    public function show(Request $request, Caso $caso): Response
    {
        AuditarConsultas::registrarAcceso($request->input('identificador'), $caso, 'detalle_caso');

        $visibles = $this->seccionesVisibles($request);
        $caso->loadCount($visibles)->load('usuario:id,name');

        $cargas = [
            'telefonos' => fn ($q) => $q->select(['id', 'numero_celular', 'empresa', 'persona_caso', 'persona_id', 'id_caso', 'created_at'])
                ->with('persona:id,nombres,apellidos,ci'),
            'registrosCriminales' => fn ($q) => $q->select(['id', 'nro_registro', 'fecha_registro', 'id_persona', 'id_division', 'id_caso'])
                ->with(['persona:id,nombres,apellidos,ci', 'division:id,division']),
            'faltasContravenciones' => fn ($q) => $q->select(['id', 'nro_registro', 'fecha_registro', 'id_persona', 'id_division', 'id_caso'])
                ->with(['persona:id,nombres,apellidos,ci', 'division:id,division']),
            'vehiculos' => fn ($q) => $q->select(['id', 'placa', 'descripcion', 'responsable', 'id_caso']),
            'vinculosVehiculo' => fn ($q) => $q->select(['id', 'vehiculo_id', 'persona_id', 'tipo', 'id_caso'])
                ->with(['vehiculo:id,placa', 'persona:id,nombres,apellidos,ci']),
        ];

        $secciones = [];
        foreach ($visibles as $relacion) {
            $filas = $caso->{$relacion}()->tap($cargas[$relacion])->orderByDesc('id')->limit(self::POR_SECCION)->get();

            // Los registros se abren por su código (md5 del id), como en Consultas.
            if (in_array($relacion, ['registrosCriminales', 'faltasContravenciones'], true)) {
                $filas->each(fn ($r) => $r->setAttribute('codigo', md5((string) $r->id)));
            }

            $secciones[$relacion] = ['filas' => $filas, 'total' => $caso->{str($relacion)->snake().'_count'}];
        }

        return Inertia::render('Casos/Ver', [
            'caso' => $caso->only(['id', 'codigo', 'descripcion', 'estado', 'created_at', 'updated_at']) + ['usuario' => $caso->usuario?->name],
            'secciones' => $secciones,
            'porSeccion' => self::POR_SECCION,
            'estados' => self::ESTADOS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Caso::create($this->validar($request) + ['user_id' => $request->user()->id]);

        return back()->with('success', 'Caso registrado correctamente.');
    }

    public function update(Request $request, Caso $caso): RedirectResponse
    {
        $caso->update($this->validar($request, $caso));

        return back()->with('success', 'Caso actualizado correctamente.');
    }

    /**
     * Buscador del selector de caso de los formularios (máximo 30); primero el que coincide exacto.
     */
    public function buscar(Request $request): JsonResponse
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', (string) $request->input('q', '')));

        $casos = Caso::query()
            ->select(['id', 'codigo', 'descripcion', 'estado'])
            ->when($texto !== '', fn (Builder $q) => $q->where('codigo', 'like', '%'.str_replace('%', ' ', $texto).'%'))
            ->orderByRaw('codigo = ? DESC', [$texto])
            ->orderBy('codigo')
            ->limit(30)
            ->get();

        return response()->json($casos);
    }

    /**
     * Alta desde el selector de un formulario (JSON). Si ya hay un caso con ese código (limpio), devuelve ése en lugar
     * de dar error: quien registra un teléfono sólo quiere que quede con su caso.
     */
    public function storeRapido(Request $request): JsonResponse
    {
        $codigo = MigradorCasos::normalizar($request->input('codigo'));
        $existente = $codigo === null ? null : Caso::withTrashed()->where('codigo', $codigo)->first();

        if ($existente) {
            if ($existente->trashed()) {
                $existente->restore();
            }

            return response()->json([...$existente->only(['id', 'codigo', 'descripcion', 'estado']), 'existente' => true]);
        }

        $request->mergeIfMissing(['estado' => 'ACTIVO']);
        $caso = Caso::create($this->validar($request) + ['user_id' => $request->user()->id]);

        return response()->json([...$caso->only(['id', 'codigo', 'descripcion', 'estado']), 'existente' => false], 201);
    }

    /**
     * Sólo un caso sin ningún registro vinculado (también eliminados): no deja filas apuntando a un caso borrado. Se
     * borra del todo para que su código se pueda volver a usar (el índice único incluye los eliminados); el borrado
     * queda en la Auditoría de cambios.
     */
    public function destroy(Caso $caso): RedirectResponse
    {
        $vinculados = collect(array_keys(self::SECCIONES))
            ->sum(fn (string $relacion) => $caso->{$relacion}()->withoutGlobalScopes()->count());

        if ($vinculados > 0) {
            return back()->with('error', "No se puede eliminar el caso {$caso->codigo}: tiene {$vinculados} registro(s) vinculado(s).");
        }

        $caso->forceDelete();

        return redirect()->route('casos.index')->with('success', 'Caso eliminado correctamente.');
    }

    /**
     * Relaciones del detalle que el usuario puede ver.
     *
     * @return list<string>
     */
    private function seccionesVisibles(Request $request): array
    {
        return array_keys(array_filter(self::SECCIONES, fn (string $permiso) => $request->user()->can($permiso)));
    }

    /**
     * El código se limpia como en la migración (`MigradorCasos::normalizar`): espacios, guiones sueltos, MAYÚSCULA.
     * Cambiar el código corrige el caso en todas sus filas (comparten el mismo `id_caso`).
     *
     * @return array{codigo: string, descripcion: ?string, estado: string}
     */
    private function validar(Request $request, ?Caso $caso = null): array
    {
        $request->merge(['codigo' => MigradorCasos::normalizar($request->input('codigo')) ?? '']);

        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:255', Rule::unique('caso', 'codigo')->ignore($caso)],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'estado' => ['required', Rule::in(self::ESTADOS)],
        ], [
            'codigo.required' => 'Ingrese el caso o CUD (no puede estar vacío ni ser sólo guiones o «S/N»).',
            'codigo.unique' => 'Ya existe un caso con ese código.',
        ], [
            'codigo' => 'caso / CUD',
        ]);

        return $datos + ['descripcion' => null];
    }
}
