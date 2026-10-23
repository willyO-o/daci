<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos de una persona: las reglas del sistema anterior y, para los campos que aquel guardaba sin validar
 * (`$request->all()`), el largo máximo de su columna.
 */
class PersonaRequest extends FormRequest
{
    /**
     * Campos que el formulario puede escribir (los `fillable` de Persona menos `user_id`, que pone el modelo).
     *
     * @var list<string>
     */
    public const CAMPOS = [
        'nombres', 'apellidos', 'ci', 'complemento', 'telefono', 'alias', 'fecha_nacimiento', 'lugar_nacimiento',
        'id_pais', 'genero', 'estado_civil', 'ocupacion', 'grupo_sanguineo', 'nombre_conyuge', 'padre', 'madre',
        'domicilio', 'datos_segip', 'responsable', 'url_documento', 'estado_investigacion', 'nit_persona',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombres' => ['required', 'string', 'max:150'],
            'apellidos' => ['nullable', 'string', 'max:150'],
            'ci' => ['nullable', 'string', 'max:250'],
            'complemento' => ['nullable', 'string', 'max:40'],
            'telefono' => ['nullable', 'string', 'max:25'],
            'alias' => ['nullable', 'string', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'lugar_nacimiento' => ['nullable', 'string', 'max:250'],
            'id_pais' => ['nullable', 'exists:pais,id'],
            'genero' => ['nullable', 'in:MASCULINO,FEMENINO'],
            'estado_civil' => ['nullable', 'in:SOLTERO,CASADO,DIVORCIADO,VIUDO,CONYUGUE'],
            'ocupacion' => ['nullable', 'string', 'max:150'],
            'grupo_sanguineo' => ['nullable', 'string', 'max:150'],
            'nombre_conyuge' => ['nullable', 'string', 'max:250'],
            'padre' => ['nullable', 'string', 'max:250'],
            'madre' => ['nullable', 'string', 'max:250'],
            'domicilio' => ['nullable', 'string', 'max:500'],
            'datos_segip' => ['nullable', 'string', 'max:65000'],
            'responsable' => ['nullable', 'string', 'max:200'],
            'url_documento' => ['nullable', 'url', 'max:500'],
            'estado_investigacion' => ['nullable', 'string', 'max:200'],
            'nit_persona' => ['nullable', 'string', 'max:140'],
            'fotos' => ['nullable', 'array', 'max:4'],
            'fotos.*' => ['file', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ci' => 'cédula de identidad',
            'id_pais' => 'nacionalidad',
            'url_documento' => 'URL del documento',
            'fotos.*' => 'foto',
        ];
    }
}
