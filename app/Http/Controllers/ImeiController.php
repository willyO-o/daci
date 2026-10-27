<?php

namespace App\Http\Controllers;

use App\Models\AuditarConsultas;
use App\Models\Imei;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ImeiController extends Controller
{
    public function index(Request $request): Response
    {
        $imeis = Imei::query()
            ->with(['telefonos:telefono.id,numero_celular,persona_id', 'telefonos.persona:id,nombres,apellidos,ci'])
            ->when($request->filled('search'), fn (Builder $q) => $this->buscar($q, $request->input('filtro'), (string) $request->input('search')))
            ->orderByDesc('id')
            ->paginate($this->tamanoPagina($request))
            ->withQueryString();

        $this->auditarBusqueda($request, 'listado_imeis', ['tipo_filtro' => $request->input('filtro'), 'busqueda' => $request->input('search')], $imeis->total());

        return Inertia::render('Imeis/Index', [
            'imeis' => $imeis,
            'filtros' => $request->only(['search', 'filtro']),
        ]);
    }

    /**
     * «Registrar IMEI» como página propia: sólo el formulario, sin el listado (permiso `imeis.crear`).
     */
    public function create(): Response
    {
        return Inertia::render('Imeis/Registrar');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        DB::transaction(function () use ($datos) {
            $imei = Imei::create(['imei' => $datos['imei'], 'caracteristicas' => $datos['caracteristicas'] ?? null]);
            $imei->telefonos()->sync($datos['telefono_id'] ?? []);
        });

        return back()->with('success', 'IMEI registrado correctamente.');
    }

    public function show(Request $request, Imei $imei): JsonResponse
    {
        AuditarConsultas::registrarAcceso($request->input('identificador'), $imei, 'detalle_imei');

        return response()->json($imei->load(['telefonos:telefono.id,numero_celular,persona_id', 'telefonos.persona:id,nombres,apellidos,ci']));
    }

    public function update(Request $request, Imei $imei): RedirectResponse
    {
        $datos = $this->validar($request, $imei);

        DB::transaction(function () use ($imei, $datos) {
            $imei->update(['imei' => $datos['imei'], 'caracteristicas' => $datos['caracteristicas'] ?? null]);

            // Los teléfonos eliminados no aparecen en el formulario: se conservan vinculados en lugar de perderse.
            $eliminados = $imei->telefonos()->onlyTrashed()->pluck('telefono.id')->all();
            $imei->telefonos()->sync([...($datos['telefono_id'] ?? []), ...$eliminados]);
        });

        return back()->with('success', 'IMEI actualizado correctamente.');
    }

    public function destroy(Imei $imei): RedirectResponse
    {
        $imei->delete();

        return back()->with('success', 'IMEI eliminado correctamente.');
    }

    public function vincularTelefono(Request $request, Imei $imei): RedirectResponse
    {
        $datos = $request->validate(['telefono_id' => ['required', 'exists:telefono,id']], ['telefono_id.required' => 'Seleccione un teléfono.']);

        $imei->telefonos()->syncWithoutDetaching([$datos['telefono_id']]);

        return back()->with('success', 'Teléfono vinculado al IMEI correctamente.');
    }

    /**
     * @return array{imei: string, caracteristicas?: ?string, telefono_id?: list<int>}
     */
    private function validar(Request $request, ?Imei $imei = null): array
    {
        return $request->validate([
            'imei' => ['required', 'string', 'max:50', Rule::unique('imei', 'imei')->ignore($imei)],
            'caracteristicas' => ['nullable', 'string', 'max:1000'],
            'telefono_id' => ['nullable', 'array'],
            'telefono_id.*' => ['integer', 'exists:telefono,id'],
        ], ['imei.unique' => 'Este IMEI ya está registrado.']);
    }

    private function buscar(Builder $consulta, ?string $filtro, string $texto): void
    {
        $como = '%'.str_replace('%', ' ', $texto).'%';

        $consulta->where(function (Builder $q) use ($filtro, $como) {
            match ($filtro) {
                'imei' => $q->where('imei', 'like', $como),
                'caracteristicas' => $q->where('caracteristicas', 'like', $como),
                'numero' => $q->whereHas('telefonos', fn (Builder $t) => $t->where('numero_celular', 'like', $como)),
                default => $q->where('imei', 'like', $como)
                    ->orWhere('caracteristicas', 'like', $como)
                    ->orWhereHas('telefonos', fn (Builder $t) => $t->where('numero_celular', 'like', $como)),
            };
        });
    }
}
