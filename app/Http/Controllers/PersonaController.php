<?php

namespace App\Http\Controllers;

use App\Http\Requests\PersonaRequest;
use App\Models\Multimedia;
use App\Models\Pais;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PersonaController extends Controller
{
    /**
     * Listado con búsqueda general o por campo, filtros de género, estado civil y (para quien puede restaurar)
     * registros eliminados. Mismos filtros y orden que el sistema anterior.
     */
    public function index(Request $request): Response
    {
        $puedeVerEliminados = $request->user()->can('personas.restaurar');
        // El sistema anterior mostraba a los administradores «Todos los registros» por defecto.
        $visible = $puedeVerEliminados ? $request->input('visible', 'todos') : 'activos';

        $personas = Persona::query()
            ->with('multimedia:id,id_persona,ruta')
            ->when($request->filled('genero'), fn (Builder $q) => $q->where('genero', $request->input('genero')))
            ->when($request->filled('estado_civil'), fn (Builder $q) => $q->where('estado_civil', $request->input('estado_civil')))
            ->when($request->filled('search'), fn (Builder $q) => $this->buscar($q, $request->input('filtro'), (string) $request->input('search')))
            ->when($visible === 'todos', fn (Builder $q) => $q->withTrashed())
            ->when($visible === 'eliminados', fn (Builder $q) => $q->onlyTrashed())
            ->orderByDesc('id')
            ->paginate($this->tamanoPagina($request))
            ->withQueryString();

        return Inertia::render('Personas/Index', [
            'personas' => $personas,
            'paises' => Pais::query()->orderBy('gentilicio')->get(['id', 'gentilicio']),
            'filtros' => [
                ...$request->only(['search', 'filtro', 'genero', 'estado_civil']),
                'visible' => $visible,
            ],
        ]);
    }

    /**
     * Alta desde el listado (Inertia) o al vuelo desde otro formulario, p. ej. mandamientos (JSON con `datos`).
     */
    public function store(PersonaRequest $request): JsonResponse|RedirectResponse
    {
        $persona = DB::transaction(function () use ($request) {
            $persona = Persona::create($request->only(PersonaRequest::CAMPOS));
            $this->guardarFotos($persona, $request->file('fotos', []));

            return $persona;
        });

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['success' => 'Persona creada correctamente.', 'datos' => $persona->load('multimedia')], 201);
        }

        return back()->with('success', 'Persona creada correctamente.');
    }

    /**
     * Detalle: JSON para el modal del listado; página propia si se abre directamente (p. ej. desde un teléfono).
     */
    public function show(Request $request, int $persona): JsonResponse|Response
    {
        $datos = Persona::withTrashed()
            ->with([
                'multimedia:id,id_persona,ruta',
                'usuario:id,name',
                'pais:id,gentilicio',
                'registroCriminal:id,id_persona,nro_registro,fecha_registro,edad_aproximada,especialidad',
                'mandamientos' => fn ($q) => $q->select('id', 'id_persona', 'hoja_ruta', 'estado', 'id_delito', 'id_tipo_mandamiento')
                    ->with(['delito:id,nombre_delito', 'tipoMandamiento:id,tipo_mandamiento']),
                'vehiculos:vehiculo.id,placa,responsable,caso_relacionado,descripcion',
                'telefonos:id,persona_id,numero_celular,persona_caso,caso,empresa',
            ])
            ->findOrFail($persona);

        $detalle = $this->detalle($datos);

        if ($request->wantsJson()) {
            return response()->json($detalle);
        }

        return Inertia::render('Personas/Ver', ['persona' => $detalle]);
    }

    public function update(PersonaRequest $request, Persona $persona): RedirectResponse
    {
        DB::transaction(function () use ($request, $persona) {
            $persona->update($request->only(PersonaRequest::CAMPOS));
            $this->guardarFotos($persona, $request->file('fotos', []));
        });

        return back()->with('success', 'Persona actualizada correctamente.');
    }

    /**
     * Vincular sólo el enlace al documento (botón de la columna «Documento» del listado).
     */
    public function vincularDocumento(Request $request, Persona $persona): RedirectResponse
    {
        $datos = $request->validate(
            ['url_documento' => ['required', 'url', 'max:500']],
            ['url_documento.required' => 'Ingresa una URL válida', 'url_documento.url' => 'Ingresa una URL válida (ej: https://drive.google.com/...)'],
        );

        $persona->update($datos);

        return back()->with('success', 'Documento vinculado correctamente.');
    }

    /**
     * Resumen de lo que tiene la persona antes de eliminarla (modal «Eliminar Persona»).
     */
    public function showDeleteModal(Persona $persona): JsonResponse
    {
        return response()->json([
            'persona' => $persona->only(['id', 'nombres', 'apellidos', 'ci']),
            'resumen' => $persona->getResumenDatosRelacionados(),
        ]);
    }

    /**
     * Eliminar: opcionalmente migra antes todos sus datos a otra persona; con `eliminar_completo` borra la persona,
     * sus archivos y sus datos relacionados de forma definitiva; si no, la deja eliminada pero recuperable.
     */
    public function destroy(Request $request, int $persona): RedirectResponse
    {
        $registro = Persona::withTrashed()->findOrFail($persona);

        $request->validate([
            'persona_migrar_id' => [
                'nullable', 'integer', Rule::notIn([$registro->id]),
                Rule::exists('persona', 'id')->whereNull('deleted_at'),
            ],
        ], [
            'persona_migrar_id.exists' => 'La persona elegida para migrar los datos no existe.',
            'persona_migrar_id.not_in' => 'Elija otra persona: no se pueden migrar los datos a la misma persona.',
        ]);

        try {
            $archivos = DB::transaction(function () use ($request, $registro) {
                // Si la migración falla no se elimina nada (el sistema anterior seguía y podía borrar los datos sin migrar).
                if ($request->filled('persona_migrar_id')
                    && $registro->migrateRelationsTo(Persona::findOrFail($request->integer('persona_migrar_id'))) === false) {
                    throw new RuntimeException('No se pudieron migrar los datos relacionados; no se eliminó la persona.');
                }

                if (! $request->boolean('eliminar_completo')) {
                    $registro->delete();

                    return [];
                }

                $archivos = $registro->deleteRelationsData();
                $registro->forceDelete();

                return $archivos;
            });
        } catch (QueryException $e) {
            report($e);

            return back()->with('error', 'No se pudo eliminar la persona: tiene datos vinculados que la base de datos no permite borrar. No se eliminó nada.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Los archivos se borran sólo cuando la base ya confirmó el borrado.
        Storage::disk('public')->delete($archivos);

        return back()->with('success', 'Persona eliminada correctamente.');
    }

    public function restore(int $persona): RedirectResponse
    {
        Persona::onlyTrashed()->findOrFail($persona)->restore();

        return back()->with('success', 'Persona restaurada correctamente.');
    }

    /**
     * Buscador de los selects de persona (mandamientos, teléfonos, vehículos, migrar datos). Excluye `id`.
     */
    public function search(Request $request): JsonResponse
    {
        $texto = str_replace('%', ' ', (string) $request->input('q', $request->input('query', '')));

        $personas = Persona::query()
            ->select(['id', 'nombres', 'apellidos', 'ci', 'complemento', 'telefono', 'domicilio', 'alias', 'genero', 'estado_civil', 'fecha_nacimiento', 'lugar_nacimiento', 'nombre_conyuge', 'ocupacion', 'id_pais'])
            ->where(function (Builder $q) use ($texto) {
                $q->where('nombres', 'like', "%{$texto}%")
                    ->orWhere('apellidos', 'like', "%{$texto}%")
                    ->orWhere('ci', 'like', "%{$texto}%")
                    ->orWhereRaw("CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, ''),' - ', COALESCE(ci, '')) LIKE ?", ["%{$texto}%"])
                    ->orWhereRaw("CONCAT(COALESCE(apellidos, ''), ' ', COALESCE(nombres, ''),' - ', COALESCE(ci, '')) LIKE ?", ["%{$texto}%"])
                    ->orWhereRaw("CONCAT(COALESCE(ci, ''), ' - ', COALESCE(apellidos, ''), ' ', COALESCE(nombres, '')) LIKE ?", ["%{$texto}%"]);
            })
            ->when($request->filled('id'), fn (Builder $q) => $q->whereKeyNot($request->integer('id')))
            ->limit(20)
            ->get();

        return response()->json($personas);
    }

    /**
     * ¿Ya existe alguien con este CI y complemento? (aviso en vivo del formulario).
     */
    public function checkCI(Request $request): JsonResponse
    {
        $persona = Persona::query()
            ->where('ci', $request->input('ci'))
            ->when(
                $request->filled('complemento'),
                fn (Builder $q) => $q->where('complemento', $request->input('complemento')),
                fn (Builder $q) => $q->whereNull('complemento'),
            )
            ->when($request->filled('excluir'), fn (Builder $q) => $q->whereKeyNot($request->integer('excluir')))
            ->first(['id', 'nombres', 'apellidos', 'ci', 'complemento']);

        return response()->json(['data' => $persona]);
    }

    /**
     * Búsqueda general (sin campo elegido) o por un campo de la lista; el texto ya no lleva comodines del usuario.
     */
    private function buscar(Builder $consulta, ?string $filtro, string $texto): void
    {
        $texto = str_replace('%', ' ', $texto);
        $como = "%{$texto}%";

        $consulta->where(function (Builder $q) use ($filtro, $como) {
            match ($filtro) {
                'nombre' => $q->where('nombres', 'like', $como),
                'apellidos' => $q->where('apellidos', 'like', $como),
                'ci' => $q->where('ci', 'like', $como)->orWhereRaw('CONCAT(ci, "-", complemento) LIKE ?', [$como]),
                'nombre_padre' => $q->where('padre', 'like', $como),
                'nombre_madre' => $q->where('madre', 'like', $como),
                default => $q->where('nombres', 'like', $como)
                    ->orWhere('apellidos', 'like', $como)
                    ->orWhere('ci', 'like', $como)
                    ->orWhereRaw('CONCAT(ci, "-", complemento) LIKE ?', [$como])
                    ->orWhereRaw("CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')) LIKE ?", [$como]),
            };
        });
    }

    /**
     * @param  array<int, UploadedFile>|UploadedFile|null  $fotos
     */
    private function guardarFotos(Persona $persona, array|UploadedFile|null $fotos): void
    {
        foreach (array_filter((array) $fotos) as $foto) {
            $nombreArchivo = $foto->hashName();
            $ruta = $foto->storeAs('personas', $nombreArchivo, 'public');

            if ($ruta !== false && Storage::disk('public')->exists($ruta)) {
                Multimedia::create([
                    'tipo' => 'persona',
                    'ruta' => $ruta,
                    'nombre_archivo' => $nombreArchivo,
                    'id_persona' => $persona->id,
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function detalle(Persona $persona): array
    {
        return [
            ...$persona->toArray(),
            'fecha_nacimiento' => $persona->fecha_nacimiento?->toDateString(),
            'edad' => $persona->fecha_nacimiento?->age,
            'registrado_por' => $persona->usuario?->name,
            'nacionalidad' => $persona->pais?->gentilicio,
            'registro_criminal' => $persona->registroCriminal->map(fn ($registro) => [
                'id' => $registro->id,
                'codigo' => md5((string) $registro->id),
                'nro_registro' => $registro->nro_registro,
                'fecha_registro' => $registro->fecha_registro?->toDateString(),
                'edad_aproximada' => $registro->edad_aproximada,
                'especialidad' => $registro->especialidad,
            ]),
            'mandamientos' => $persona->mandamientos->map(fn ($mandamiento) => [
                'id' => $mandamiento->id,
                'codigo' => md5((string) $mandamiento->id),
                'hoja_ruta' => $mandamiento->hoja_ruta,
                'delito' => $mandamiento->delito?->nombre_delito,
                'tipo' => $mandamiento->tipoMandamiento?->tipo_mandamiento,
                'estado' => $mandamiento->estado,
            ]),
        ];
    }
}
