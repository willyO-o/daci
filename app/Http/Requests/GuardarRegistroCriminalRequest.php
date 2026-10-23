<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GuardarRegistroCriminalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha_registro' => 'required|date',
            'nombres' => 'required|string|max:255',
            'genero' => 'required|in:MASCULINO,FEMENINO',
            'especialidad' => 'nullable|string|max:255',
            'id_division' => 'required|exists:division,id',
            'modus_operandi' => 'nullable|string',
            'zonas_opera' => 'nullable|string',
            // 'alias' => 'nullable|string|max:255',
            // 'especialidad' => 'nullable|string|max:255',
            // 'edad_aproximada' => 'nullable|integer|min:0',
            // 'nombre_conyuge' => 'nullable|string|max:255',
            // 'domicilio' => 'nullable|string|max:500',
            // 'rasgos' => 'nullable|string',
            // 'modus_operandi' => 'nullable|string',
            // 'zonas_opera' => 'nullable|string',
            // 'observaciones' => 'nullable|string',
            // 'id_persona' => 'required|exists:persona,id',
            // 'id_division' => 'required|exists:division,id',
            // 'id_usuario' => 'required|exists:users,id', --- IGNORE ---
        ];
    }
}
