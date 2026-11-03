<?php

namespace App\Http\Controllers;

use App\Models\AuditarConsultas;
use App\Models\Caso;
use App\Models\Multimedia;
use App\Models\Persona;
use App\Models\Vehiculo;
use App\Models\VehiculoCaso;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VehiculoController extends Controller
{
    public function index(Request $request): Response
    {
        $vehiculos = Vehiculo::query()
            ->with(['personas:persona.id,nombres,apellidos,ci', 'multimedia:id,id_vehiculo,ruta', 'casoVinculado:id,codigo'])
            ->when($request->filled('search'), fn (Builder $q) => $this->buscar($q, $request->input('filtro'), (string) $request->input('search')))
            ->orderByDesc('id')
            ->paginate($this->tamanoPagina($request))
            ->withQueryString();
        $this->agregarCodigosDeCaso($vehiculos->getCollection());

        $this->auditarBusqueda($request, 'listado_vehiculos', ['tipo_filtro' => $request->input('filtro'), 'busqueda' => $request->input('search')], $vehiculos->total());

        return Inertia::render('Vehiculos/Index', [
            'vehiculos' => $vehiculos,
            'filtros' => $request->only(['search', 'filtro']),
        ]);
    }

    /**
     * «Registrar Vehículo» como página propia: sólo el formulario, sin el listado (permiso `vehiculos.crear`).
     */
    public function create(): Response
    {
        return Inertia::render('Vehiculos/Registrar');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        DB::transaction(function () use ($request, $datos) {
            $vehiculo = Vehiculo::create([...$datos, ...Caso::columnas($request->input('id_caso'), 'caso_relacionado')]);
            $this->sincronizarPersonas($vehiculo, $request->input('personas_asociadas', []));
            $this->guardarFotos($vehiculo, $request->file('fotosVehiculos', []));
        });

        return back()->with('success', 'Vehículo creado correctamente');
    }

    /**
     * Ficha completa del vehículo (personas por tipo de registro, carguíos e ITV).
     */
    public function show(Request $request, Vehiculo $vehiculo): JsonResponse
    {
        AuditarConsultas::registrarAcceso($request->input('identificador'), $vehiculo, 'detalle_vehiculo');

        $vehiculo->load([
            'casoVinculado:id,codigo',
            'multimedia:id,id_vehiculo,ruta',
            'personas:persona.id,nombres,apellidos,ci,telefono',
            'cargios.estacionServicio:id,eess',
            'inspecciones' => fn ($q) => $q->with('persona:id,nombres,apellidos,ci,telefono')->orderByDesc('anio')->orderByDesc('id'),
        ]);
        $this->agregarCodigosDeCaso(collect([$vehiculo]));

        return response()->json([
            ...$vehiculo->toArray(),
            'ultimo_cargio_hace' => $vehiculo->cargios->first()?->fecha_venta?->locale('es')->diffForHumans(),
        ]);
    }

    public function update(Request $request, Vehiculo $vehiculo): RedirectResponse
    {
        $datos = $this->validar($request, $vehiculo);

        DB::transaction(function () use ($request, $vehiculo, $datos) {
            $vehiculo->update([...$datos, ...Caso::columnas($request->input('id_caso'), 'caso_relacionado', $vehiculo)]);
            $this->sincronizarPersonas($vehiculo, $request->input('personas_asociadas', []), true);
            $this->guardarFotos($vehiculo, $request->file('fotosVehiculos', []));
        });

        return back()->with('success', 'Vehículo actualizado correctamente');
    }

    public function destroy(Vehiculo $vehiculo): RedirectResponse
    {
        $fotos = $vehiculo->multimedia()->pluck('ruta')->all();

        DB::transaction(function () use ($vehiculo) {
            $vehiculo->casos()->delete();
            $vehiculo->inspecciones()->delete();
            $vehiculo->cargios()->delete();
            $vehiculo->multimedia()->delete();
            $vehiculo->forceDelete();
        });

        Storage::disk('public')->delete($fotos);

        return back()->with('success', 'Vehículo eliminado correctamente');
    }

    public function vincularPersona(Request $request, Vehiculo $vehiculo): RedirectResponse
    {
        $datos = $request->validate([
            'persona_id' => ['required', 'exists:persona,id'],
            'tipo' => ['required', 'string', 'max:255'],
            'id_caso' => Caso::reglaId(),
        ], ['persona_id.required' => 'Seleccione una persona.', 'tipo.required' => 'Indique el tipo de información.', 'id_caso.exists' => 'El caso elegido ya no existe.']);

        $existe = VehiculoCaso::where('vehiculo_id', $vehiculo->id)
            ->where('persona_id', $datos['persona_id'])
            ->where('tipo', campoDB($datos['tipo']))
            ->exists();

        if ($existe) {
            return back()->withErrors(['persona_id' => 'Esta persona ya está vinculada con este tipo']);
        }

        // El caso del vínculo: `id_caso` y su código en `numero_informacion` (el sistema anterior lo perdía).
        VehiculoCaso::create([
            'vehiculo_id' => $vehiculo->id,
            'persona_id' => $datos['persona_id'],
            'tipo' => campoDB($datos['tipo']),
            ...Caso::columnas($datos['id_caso'] ?? null, 'numero_informacion', null, 140),
        ]);

        return back()->with('success', 'Persona vinculada correctamente');
    }

    public function desvincularPersona(Vehiculo $vehiculo, int $persona): RedirectResponse
    {
        VehiculoCaso::where('vehiculo_id', $vehiculo->id)->where('persona_id', $persona)->delete();

        return back()->with('success', 'Persona desvinculada correctamente');
    }

    /**
     * El caso (`id_caso`) se guarda aparte con `Caso::columnas`.
     *
     * @return array{placa: string, descripcion: ?string, responsable: ?string}
     */
    private function validar(Request $request, ?Vehiculo $vehiculo = null): array
    {
        $datos = $request->validate([
            'placa' => ['required', 'string', 'min:3', 'max:20', Rule::unique('vehiculo', 'placa')->ignore($vehiculo)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'responsable' => ['nullable', 'string', 'max:255'],
            'id_caso' => Caso::reglaId(),
            'personas_asociadas' => ['nullable', 'array'],
            'personas_asociadas.*.persona_id' => ['required', 'exists:persona,id'],
            'personas_asociadas.*.tipo' => ['required', 'string', 'max:255'],
            'personas_asociadas.*.id_caso' => Caso::reglaId(),
            'fotosVehiculos' => ['nullable', 'array', 'max:10'],
            'fotosVehiculos.*' => ['file', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ], [
            'placa.unique' => 'Ya existe un vehículo con esta placa.',
            'id_caso.exists' => 'El caso elegido ya no existe.',
            'personas_asociadas.*.id_caso.exists' => 'El caso elegido ya no existe.',
        ]);

        return [
            'placa' => $datos['placa'],
            'descripcion' => $datos['descripcion'] ?? null,
            'responsable' => $datos['responsable'] ?? null,
        ];
    }

    /**
     * Personas asociadas del formulario (`[{persona_id, tipo, id_caso}]`). Al editar, las que ya no vienen se quitan.
     *
     * @param  array<int, array{persona_id: int, tipo: string, id_caso?: ?int}>  $personas
     */
    private function sincronizarPersonas(Vehiculo $vehiculo, array $personas, bool $quitarAusentes = false): void
    {
        $conservados = [];

        foreach ($personas as $persona) {
            $vinculo = VehiculoCaso::firstOrNew(['vehiculo_id' => $vehiculo->id, 'persona_id' => $persona['persona_id'], 'tipo' => campoDB($persona['tipo'])]);
            $vinculo->fill(Caso::columnas($persona['id_caso'] ?? null, 'numero_informacion', $vinculo->exists ? $vinculo : null, 140))->save();
            $conservados[] = $vinculo->id;
        }

        // Sólo se quitan vínculos que el formulario mostraba: los de personas eliminadas (recuperables) o sin persona
        // no aparecen en la lista y no deben perderse al editar el vehículo.
        if ($quitarAusentes) {
            VehiculoCaso::where('vehiculo_id', $vehiculo->id)
                ->whereNotIn('id', $conservados)
                ->whereIn('persona_id', Persona::query()->select('id'))
                ->delete();
        }
    }

    /**
     * Código del caso de cada vínculo vehículo–persona (`pivot.caso_codigo`), para mostrarlo y para el formulario.
     *
     * @param  Collection<int, Vehiculo>  $vehiculos
     */
    private function agregarCodigosDeCaso($vehiculos): void
    {
        $ids = $vehiculos->flatMap(fn (Vehiculo $v) => $v->personas->pluck('pivot.id_caso'))->filter()->unique()->values();
        $codigos = $ids->isEmpty() ? collect() : Caso::query()->whereIn('id', $ids)->pluck('codigo', 'id');

        $vehiculos->each(fn (Vehiculo $v) => $v->personas->each(
            fn ($p) => $p->pivot->setAttribute('caso_codigo', $codigos[$p->pivot->id_caso] ?? null),
        ));
    }

    /**
     * Fotos en la carpeta de siempre (`vehiculos/`); con nombre único para que dos fotos con el mismo nombre no
     * se pisen (el sistema anterior usaba el nombre original).
     *
     * @param  array<int, UploadedFile>|UploadedFile|null  $fotos
     */
    private function guardarFotos(Vehiculo $vehiculo, array|UploadedFile|null $fotos): void
    {
        foreach (array_filter((array) $fotos) as $foto) {
            $nombreArchivo = $foto->hashName();
            $ruta = $foto->storeAs('vehiculos', $nombreArchivo, 'public');

            if ($ruta !== false && Storage::disk('public')->exists($ruta)) {
                Multimedia::create([
                    'id_vehiculo' => $vehiculo->id,
                    'tipo' => 'foto_vehiculo',
                    'ruta' => $ruta,
                    'nombre_archivo' => $foto->getClientOriginalName(),
                ]);
            }
        }
    }

    private function buscar(Builder $consulta, ?string $filtro, string $texto): void
    {
        $como = '%'.str_replace('%', ' ', $texto).'%';

        $consulta->where(function (Builder $q) use ($filtro, $como) {
            match ($filtro) {
                'placa' => $q->where('placa', 'like', $como),
                'descripcion' => $q->where('descripcion', 'like', $como),
                'responsable' => $q->where('responsable', 'like', $como),
                'caso_relacionado' => $q->where('caso_relacionado', 'like', $como),
                'ci_persona' => $q->whereHas('personas', fn (Builder $p) => $p->where('ci', 'like', $como)),
                'nombres' => $q->whereHas('personas', fn (Builder $p) => $p->whereRaw("CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')) LIKE ?", [$como])),
                default => $q->where('placa', 'like', $como)
                    ->orWhere('descripcion', 'like', $como)
                    ->orWhere('responsable', 'like', $como)
                    ->orWhere('caso_relacionado', 'like', $como),
            };
        });
    }
}
