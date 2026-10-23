<?php

namespace App\Http\Controllers;

use App\Models\Multimedia;
use App\Models\Persona;
use App\Models\Vehiculo;
use App\Models\VehiculoCaso;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
            ->with(['personas:persona.id,nombres,apellidos,ci', 'multimedia:id,id_vehiculo,ruta'])
            ->when($request->filled('search'), fn (Builder $q) => $this->buscar($q, $request->input('filtro'), (string) $request->input('search')))
            ->orderByDesc('id')
            ->paginate($this->tamanoPagina($request))
            ->withQueryString();

        return Inertia::render('Vehiculos/Index', [
            'vehiculos' => $vehiculos,
            'filtros' => $request->only(['search', 'filtro']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        DB::transaction(function () use ($request, $datos) {
            $vehiculo = Vehiculo::create($datos);
            $this->sincronizarPersonas($vehiculo, $request->input('personas_asociadas', []));
            $this->guardarFotos($vehiculo, $request->file('fotosVehiculos', []));
        });

        return back()->with('success', 'Vehículo creado correctamente');
    }

    /**
     * Ficha completa del vehículo (personas por tipo de registro, carguíos e ITV).
     */
    public function show(Vehiculo $vehiculo): JsonResponse
    {
        $vehiculo->load([
            'multimedia:id,id_vehiculo,ruta',
            'personas:persona.id,nombres,apellidos,ci,telefono',
            'cargios.estacionServicio:id,eess',
            'inspecciones' => fn ($q) => $q->with('persona:id,nombres,apellidos,ci,telefono')->orderByDesc('anio')->orderByDesc('id'),
        ]);

        return response()->json([
            ...$vehiculo->toArray(),
            'ultimo_cargio_hace' => $vehiculo->cargios->first()?->fecha_venta?->locale('es')->diffForHumans(),
        ]);
    }

    public function update(Request $request, Vehiculo $vehiculo): RedirectResponse
    {
        $datos = $this->validar($request, $vehiculo);

        DB::transaction(function () use ($request, $vehiculo, $datos) {
            $vehiculo->update($datos);
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
            'caso' => ['nullable', 'string', 'max:140'],
        ], ['persona_id.required' => 'Seleccione una persona.', 'tipo.required' => 'Indique el tipo de información.']);

        $existe = VehiculoCaso::where('vehiculo_id', $vehiculo->id)
            ->where('persona_id', $datos['persona_id'])
            ->where('tipo', campoDB($datos['tipo']))
            ->exists();

        if ($existe) {
            return back()->withErrors(['persona_id' => 'Esta persona ya está vinculada con este tipo']);
        }

        // El sistema anterior guardaba el caso en una columna inexistente y se perdía: va en `numero_informacion`.
        VehiculoCaso::create([
            'vehiculo_id' => $vehiculo->id,
            'persona_id' => $datos['persona_id'],
            'tipo' => campoDB($datos['tipo']),
            'numero_informacion' => campoDB($datos['caso'] ?? null),
        ]);

        return back()->with('success', 'Persona vinculada correctamente');
    }

    public function desvincularPersona(Vehiculo $vehiculo, int $persona): RedirectResponse
    {
        VehiculoCaso::where('vehiculo_id', $vehiculo->id)->where('persona_id', $persona)->delete();

        return back()->with('success', 'Persona desvinculada correctamente');
    }

    /**
     * @return array{placa: string, descripcion: ?string, responsable: ?string, caso_relacionado: ?string}
     */
    private function validar(Request $request, ?Vehiculo $vehiculo = null): array
    {
        $datos = $request->validate([
            'placa' => ['required', 'string', 'min:3', 'max:20', Rule::unique('vehiculo', 'placa')->ignore($vehiculo)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'responsable' => ['nullable', 'string', 'max:255'],
            'caso_relacionado' => ['nullable', 'string', 'max:255'],
            'personas_asociadas' => ['nullable', 'array'],
            'personas_asociadas.*.persona_id' => ['required', 'exists:persona,id'],
            'personas_asociadas.*.tipo' => ['required', 'string', 'max:255'],
            'personas_asociadas.*.caso' => ['nullable', 'string', 'max:140'],
            'fotosVehiculos' => ['nullable', 'array', 'max:10'],
            'fotosVehiculos.*' => ['file', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ], [
            'placa.unique' => 'Ya existe un vehículo con esta placa.',
        ]);

        return [
            'placa' => $datos['placa'],
            'descripcion' => $datos['descripcion'] ?? null,
            'responsable' => $datos['responsable'] ?? null,
            'caso_relacionado' => $datos['caso_relacionado'] ?? null,
        ];
    }

    /**
     * Personas asociadas del formulario (`[{persona_id, tipo, caso}]`). Al editar, las que ya no vienen se quitan.
     *
     * @param  array<int, array{persona_id: int, tipo: string, caso?: ?string}>  $personas
     */
    private function sincronizarPersonas(Vehiculo $vehiculo, array $personas, bool $quitarAusentes = false): void
    {
        $conservados = [];

        foreach ($personas as $persona) {
            $caso = VehiculoCaso::updateOrCreate(
                ['vehiculo_id' => $vehiculo->id, 'persona_id' => $persona['persona_id'], 'tipo' => campoDB($persona['tipo'])],
                ['numero_informacion' => campoDB($persona['caso'] ?? null)],
            );
            $conservados[] = $caso->id;
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
