<?php

namespace App\Http\Controllers;

use App\Models\TipoArticulo;
use App\Support\Evidencias\ReglasEvidencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de tipos de artículo de Evidencias (`evidencias.tipos`). No se borran: se inactivan (dejan de ofrecerse en
 * el formulario y los artículos que ya lo usan lo conservan). El grupo de un tipo en uso no se cambia: los artículos
 * guardados perderían los campos de su grupo al editarlos.
 */
class TipoArticuloController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Evidencias/TiposArticulo', [
            'tipos' => TipoArticulo::query()->withCount('articulos')->orderBy('nombre')->get(),
            'grupos' => ReglasEvidencia::GRUPOS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        TipoArticulo::create($this->validar($request) + ['user_id' => $request->user()->id]);

        return back()->with('success', 'Tipo de artículo registrado.');
    }

    public function update(Request $request, TipoArticulo $tipo): RedirectResponse
    {
        $datos = $this->validar($request, $tipo);

        if ($datos['grupo'] !== $tipo->grupo && $tipo->articulos()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['grupo' => 'Este tipo ya tiene artículos registrados: su grupo no se puede cambiar. Cree un tipo nuevo.']);
        }

        $tipo->update($datos);

        return back()->with('success', 'Tipo de artículo actualizado.');
    }

    /**
     * @return array{nombre: string, grupo: string, estado: string}
     */
    private function validar(Request $request, ?TipoArticulo $tipo = null): array
    {
        $request->merge(['nombre' => campoDB($request->input('nombre'))]);

        return $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('tipo_articulo', 'nombre')->ignore($tipo)],
            'grupo' => ['required', Rule::in(array_keys(ReglasEvidencia::GRUPOS))],
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ], [
            'nombre.unique' => 'Ya existe un tipo con ese nombre.',
        ]);
    }
}
