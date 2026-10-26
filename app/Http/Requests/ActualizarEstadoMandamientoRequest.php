<?php

namespace App\Http\Requests;

use App\Models\Mandamiento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cambio del estado de ejecución de un mandamiento desde Consultas › Mandamientos. Si el estado contiene la palabra
 * «EJECUTADO» (igual que el formulario de edición) se exigen fecha, quién lo ejecutó y el acta (salvo que ya tenga
 * una); con cualquier otro estado sólo se guarda el estado y los demás datos de ejecución se conservan.
 */
class ActualizarEstadoMandamientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'estado' => mb_strtoupper(trim((string) $this->input('estado'))),
            'ejecutado_por' => $this->filled('ejecutado_por') ? mb_strtoupper(trim((string) $this->input('ejecutado_por'))) : null,
        ]);
    }

    public function esEjecutado(): bool
    {
        return str_contains((string) $this->input('estado'), 'EJECUTADO');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglas = ['estado' => ['required', 'string', 'max:200']];

        if (! $this->esEjecutado()) {
            return $reglas;
        }

        /** @var Mandamiento $mandamiento */
        $mandamiento = $this->route('mandamiento');
        $tieneActa = $mandamiento->multimedia()->where('tipo', 'acta_ejecucion')->exists();

        return [
            ...$reglas,
            'fecha_ejecucion' => ['required', 'date', 'before_or_equal:today'],
            'ejecutado_por' => ['required', 'string', 'max:200'],
            'detalle_ejecucion' => ['nullable', 'string', 'max:65535'],
            'acta_ejecucion' => [$tieneActa ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'Ingrese el estado.',
            'fecha_ejecucion.required' => 'Indique la fecha de ejecución.',
            'fecha_ejecucion.before_or_equal' => 'La fecha de ejecución no puede ser posterior a hoy.',
            'ejecutado_por.required' => 'Indique quién ejecutó el mandamiento.',
            'acta_ejecucion.required' => 'Suba el acta de ejecución.',
            'acta_ejecucion.mimes' => 'El acta debe ser una imagen (JPG, PNG, WEBP) o un PDF.',
            'acta_ejecucion.max' => 'El acta no debe pesar más de 5 MB.',
        ];
    }

    /**
     * Campos que se escriben en el mandamiento: sólo el estado, o el estado con los datos de ejecución.
     *
     * @return array<string, mixed>
     */
    public function camposAGuardar(): array
    {
        if (! $this->esEjecutado()) {
            return ['estado' => $this->input('estado')];
        }

        return $this->only(['estado', 'fecha_ejecucion', 'ejecutado_por', 'detalle_ejecucion']);
    }
}
