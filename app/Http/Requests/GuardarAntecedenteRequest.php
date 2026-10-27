<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de un registro de Faltas o Contravenciones: las mismas reglas que el alta de Registro Criminal.
 */
class GuardarAntecedenteRequest extends FormRequest
{
    /**
     * El permiso lo exige la ruta (`faltas.crear` / `contravenciones.crear`).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Los largos son los de las columnas: el sistema anterior no los validaba y un texto más largo terminaba en
        // un error de la base de datos al guardar.
        return [
            'fecha_registro' => 'required|date',
            'id_persona' => 'nullable|integer',
            'nombres' => 'required|string|max:150',
            'apellidos' => 'nullable|string|max:150',
            'ci' => 'nullable|string|max:250',
            'complemento' => 'nullable|string|max:40',
            'lugar_nacimiento' => 'nullable|string|max:250',
            'fecha_nacimiento' => 'nullable|date',
            'ocupacion' => 'nullable|string|max:150',
            'estado_civil' => 'nullable|in:SOLTERO,CASADO,DIVORCIADO,VIUDO,CONYUGUE',
            'id_pais' => 'nullable|exists:pais,id',
            'genero' => 'required|in:MASCULINO,FEMENINO',
            'nombre_supuesto' => 'nullable|string|max:250',
            'alias' => 'nullable|string|max:20',
            'especialidad' => 'nullable|string|max:250',
            'edad_aproximada' => 'nullable|string|max:20',
            'nombre_conyuge' => 'nullable|string|max:250',
            'domicilio' => 'nullable|string|max:250',
            // Se copia también al teléfono de la persona (columna de 25).
            'telefono' => 'nullable|string|max:25',
            'estatura' => 'nullable|string|max:255',
            'peso' => 'nullable|string|max:255',
            'cud' => 'nullable|string|max:255',
            'id_division' => 'required|exists:division,id',
            'modus_operandi' => 'nullable|string',
            'zonas_opera' => 'nullable|string',
            'foto_frente' => 'nullable|image|max:10240',
            'foto_perfil' => 'nullable|image|max:10240',
        ];
    }
}
