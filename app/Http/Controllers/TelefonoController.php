<?php

namespace App\Http\Controllers;

use App\Models\Telefono;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TelefonoController extends Controller
{
    /**
     * @var list<string>
     */
    private const CAMPOS = [
        'numero_celular', 'persona_caso', 'caso', 'empresa', 'respuesta_requerimiento', 'persona_id', 'informacion',
        'callapp', 'truecall', 'uninet',
    ];

    public function index(Request $request): Response
    {
        $telefonos = Telefono::query()
            ->with(['persona:id,nombres,apellidos,ci', 'imeis:imei.id,imei'])
            ->when($request->filled('search'), fn (Builder $q) => $this->buscar($q, $request->input('filtro'), (string) $request->input('search')))
            ->orderByDesc('id')
            ->paginate($this->tamanoPagina($request))
            ->withQueryString();

        return Inertia::render('Telefonos/Index', [
            'telefonos' => $telefonos,
            'filtros' => $request->only(['search', 'filtro']),
        ]);
    }

    /**
     * «Registrar Teléfono» como página propia: sólo el formulario, sin el listado (permiso `telefonos.crear`).
     */
    public function create(): Response
    {
        return Inertia::render('Telefonos/Registrar');
    }

    public function store(Request $request): RedirectResponse
    {
        Telefono::create($this->validar($request));

        return back()->with('success', 'Teléfono registrado correctamente.');
    }

    public function show(Telefono $telefono): JsonResponse
    {
        return response()->json($telefono->load(['persona:id,nombres,apellidos,ci', 'imeis:imei.id,imei']));
    }

    public function update(Request $request, Telefono $telefono): RedirectResponse
    {
        $telefono->update($this->validar($request, $telefono));

        return back()->with('success', 'Teléfono actualizado correctamente.');
    }

    public function destroy(Telefono $telefono): RedirectResponse
    {
        $telefono->delete();

        return back()->with('success', 'Teléfono eliminado correctamente.');
    }

    public function vincularPersona(Request $request, Telefono $telefono): RedirectResponse
    {
        $datos = $request->validate(['persona_id' => ['required', 'exists:persona,id']], ['persona_id.required' => 'Seleccione una persona.']);

        $telefono->update($datos);

        return back()->with('success', 'Persona vinculada correctamente.');
    }

    /**
     * Buscador de teléfonos para vincular IMEIs (máximo 30 resultados).
     */
    public function searchTelefonos(Request $request): JsonResponse
    {
        $texto = str_replace('%', ' ', (string) $request->input('q', ''));

        $telefonos = Telefono::query()
            ->select(['id', 'numero_celular', 'persona_id'])
            ->with('persona:id,nombres,apellidos')
            ->when($texto !== '', fn (Builder $q) => $q->where(function (Builder $q2) use ($texto) {
                $q2->where('numero_celular', 'like', "%{$texto}%")
                    ->orWhereHas('persona', fn (Builder $p) => $p->whereRaw("CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')) LIKE ?", ["%{$texto}%"]));
            }))
            ->orderBy('numero_celular')
            ->limit(30)
            ->get()
            ->map(fn (Telefono $telefono) => [
                'id' => $telefono->id,
                'text' => $telefono->numero_celular.($telefono->persona ? ' - '.$telefono->persona->nombres.' '.$telefono->persona->apellidos : ''),
            ]);

        return response()->json($telefonos);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Telefono $telefono = null): array
    {
        return $request->validate([
            'numero_celular' => ['required', 'string', 'max:25', Rule::unique('telefono', 'numero_celular')->ignore($telefono)],
            'persona_caso' => ['nullable', 'string', 'max:255'],
            'caso' => ['nullable', 'string', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:150'],
            'respuesta_requerimiento' => ['nullable', 'string', 'max:255'],
            'persona_id' => ['nullable', 'exists:persona,id'],
            'informacion' => ['nullable', 'string', 'max:255'],
            'callapp' => ['nullable', 'string', 'max:150'],
            'truecall' => ['nullable', 'string', 'max:150'],
            'uninet' => ['nullable', 'string', 'max:150'],
        ], [
            'numero_celular.unique' => 'Este número ya está registrado.',
        ], [
            'numero_celular' => 'número celular',
        ]) + array_fill_keys(self::CAMPOS, null);
    }

    private function buscar(Builder $consulta, ?string $filtro, string $texto): void
    {
        $como = '%'.str_replace('%', ' ', $texto).'%';

        $consulta->where(function (Builder $q) use ($filtro, $como) {
            match ($filtro) {
                'numero' => $q->where('numero_celular', 'like', $como),
                'imei' => $q->whereHas('imeis', fn (Builder $i) => $i->where('imei', 'like', $como)),
                'ci_persona' => $q->whereHas('persona', fn (Builder $p) => $p->where('ci', 'like', $como)),
                'nombre_persona' => $q->whereHas('persona', fn (Builder $p) => $p->whereRaw("CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')) LIKE ?", [$como])),
                'callap' => $q->where('callapp', 'like', $como),
                'truecall' => $q->where('truecall', 'like', $como),
                'uninet' => $q->where('uninet', 'like', $como),
                'respuesta_requerimiento' => $q->where('respuesta_requerimiento', 'like', $como),
                default => $q->where('numero_celular', 'like', $como)
                    ->orWhere('caso', 'like', $como)
                    ->orWhere('persona_caso', 'like', $como)
                    ->orWhereHas('persona', fn (Builder $p) => $p->where('ci', 'like', $como)
                        ->orWhereRaw("CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellidos, '')) LIKE ?", [$como])),
            };
        });
    }
}
