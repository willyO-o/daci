<?php

namespace App\Http\Requests;

use App\Support\Acceso\CatalogoPermisos;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de usuarios (mismas reglas y mensajes que el sistema anterior). El rol se elige entre los roles de
 * Spatie, nunca el superadmin.
 */
class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $usuario = $this->route('usuario');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario)],
            'password' => [$usuario ? 'nullable' : 'required', 'string', 'min:6', 'confirmed'],
            'role_id' => [
                'required',
                Rule::exists('roles', 'id')->where('guard_name', CatalogoPermisos::GUARD)
                    ->whereNot('name', CatalogoPermisos::ROL_SUPERADMIN),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'role_id.required' => 'Debe seleccionar un rol.',
            'role_id.exists' => 'El rol seleccionado no es válido.',
        ];
    }
}
