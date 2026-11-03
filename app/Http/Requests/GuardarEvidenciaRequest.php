<?php

namespace App\Http\Requests;

use App\Models\Caso;
use App\Models\TipoArticulo;
use App\Support\Evidencias\ReglasEvidencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de una evidencia con sus artículos y archivos (D-038). La autorización la dan las rutas
 * (`evidencias.crear` / `evidencias.editar`).
 */
class GuardarEvidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * IMEI sólo con dígitos (se aceptan espacios o guiones al escribirlo).
     */
    protected function prepareForValidation(): void
    {
        $articulos = $this->input('articulos');

        if (! is_array($articulos)) {
            return;
        }

        foreach ($articulos as $indice => $articulo) {
            foreach (['imei_1', 'imei_2'] as $campo) {
                if (is_array($articulo) && filled($articulo[$campo] ?? null)) {
                    $articulos[$indice][$campo] = preg_replace('/\D+/', '', (string) $articulo[$campo]);
                }
            }
        }

        $this->merge(['articulos' => $articulos]);
    }

    public function rules(): array
    {
        $evidencia = $this->route('evidencia');
        $foto = ['file', 'mimes:jpeg,jpg,png,webp', 'max:'.(ReglasEvidencia::MAX_MB_FOTO * 1024)];
        $documento = ['file', 'mimes:pdf,jpeg,jpg,png,webp', 'max:'.(ReglasEvidencia::MAX_MB_DOCUMENTO * 1024)];

        return [
            'codigo' => ['nullable', 'string', 'max:50', Rule::unique('evidencia', 'codigo')->ignore($evidencia)],
            'id_caso' => Caso::reglaId(),
            'id_division' => ['nullable', 'integer', 'exists:division,id'],
            'fecha_hora' => ['required', 'date', 'before_or_equal:now'],
            'lugar' => ['required', 'string', 'max:255'],
            'detalle_lugar' => ['nullable', 'string', 'max:2000'],
            'funcionario_responsable' => ['required', 'string', 'max:200'],
            'numero_acta' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'estado' => ['required', Rule::in(ReglasEvidencia::ESTADOS)],
            'actas' => ['nullable', 'array'],
            'actas.*' => $documento,
            'documentos' => ['nullable', 'array'],
            'documentos.*' => $documento,

            'articulos' => ['required', 'array', 'min:1', 'max:100'],
            'articulos.*.id' => ['nullable', 'integer'],
            'articulos.*.tipo_articulo_id' => ['required', 'integer', 'exists:tipo_articulo,id'],
            'articulos.*.descripcion' => ['nullable', 'string', 'max:500'],
            'articulos.*.marca' => ['nullable', 'string', 'max:100'],
            'articulos.*.modelo' => ['nullable', 'string', 'max:100'],
            'articulos.*.color' => ['nullable', 'string', 'max:50'],
            'articulos.*.imei_1' => ['nullable', 'regex:/^\d{14,16}$/'],
            'articulos.*.imei_2' => ['nullable', 'regex:/^\d{14,16}$/', 'different:articulos.*.imei_1'],
            'articulos.*.numero_serie' => ['nullable', 'string', 'max:100'],
            'articulos.*.cantidad' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
            'articulos.*.unidad_medida' => ['nullable', 'string', 'max:30'],
            'articulos.*.estado_fisico' => ['nullable', Rule::in(ReglasEvidencia::ESTADOS_FISICOS)],
            'articulos.*.funcionario_recoge' => ['nullable', 'string', 'max:200'],
            'articulos.*.fecha_recepcion' => ['nullable', 'date', 'before_or_equal:today'],
            'articulos.*.observaciones' => ['nullable', 'string', 'max:2000'],
            'articulos.*.solicitar_publicacion' => ['nullable', 'boolean'],
            'articulos.*.fotos' => ['nullable', 'array'],
            'articulos.*.fotos.*' => $foto,
            'articulos.*.fotos_imei' => ['nullable', 'array'],
            'articulos.*.fotos_imei.*' => $foto,
            'articulos.*.comprobantes' => ['nullable', 'array'],
            'articulos.*.comprobantes.*' => $documento,
        ];
    }

    /**
     * Reglas que dependen del grupo del tipo de artículo y del total de archivos.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $articulos = $this->input('articulos');
                if (! is_array($articulos)) {
                    return;
                }

                $grupos = TipoArticulo::query()
                    ->whereIn('id', collect($articulos)->pluck('tipo_articulo_id')->filter()->all())
                    ->pluck('grupo', 'id');

                foreach ($articulos as $indice => $articulo) {
                    $grupo = $grupos[$articulo['tipo_articulo_id'] ?? null] ?? null;
                    if (in_array($grupo, ReglasEvidencia::DESCRIPCION_OBLIGATORIA, true) && blank($articulo['descripcion'] ?? null)) {
                        $validator->errors()->add("articulos.{$indice}.descripcion", 'Describa el artículo: para este tipo no hay marca ni modelo que lo identifiquen.');
                    }
                }

                $archivos = collect($this->allFiles())->flatten()->filter(fn ($a) => $a instanceof UploadedFile)->count();
                if ($archivos > ReglasEvidencia::MAX_ARCHIVOS_POR_ENVIO) {
                    $validator->errors()->add('archivos', 'Se pueden adjuntar hasta '.ReglasEvidencia::MAX_ARCHIVOS_POR_ENVIO." archivos por vez (se enviaron {$archivos}). Guarde y adjunte el resto editando la evidencia.");
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'articulos.required' => 'Agregue al menos un artículo.',
            'articulos.min' => 'Agregue al menos un artículo.',
            'articulos.*.imei_1.regex' => 'El IMEI debe tener entre 14 y 16 dígitos.',
            'articulos.*.imei_2.regex' => 'El IMEI debe tener entre 14 y 16 dígitos.',
            'articulos.*.imei_2.different' => 'El IMEI 2 no puede ser igual al IMEI 1.',
            'fecha_hora.before_or_equal' => 'La fecha y hora no puede ser posterior a este momento.',
            'articulos.*.fecha_recepcion.before_or_equal' => 'La fecha de recepción no puede ser posterior a hoy.',
            'codigo.unique' => 'Ya existe una evidencia con ese código.',
            'id_caso.exists' => 'El caso elegido ya no existe.',
            '*.mimes' => 'Formato no admitido.',
            'articulos.*.fotos.*.mimes' => 'Las fotografías deben ser JPG, PNG o WebP.',
            'articulos.*.fotos_imei.*.mimes' => 'Las fotografías deben ser JPG, PNG o WebP.',
            'articulos.*.fotos.*.max' => 'Cada fotografía puede pesar hasta '.ReglasEvidencia::MAX_MB_FOTO.' MB.',
            'articulos.*.fotos_imei.*.max' => 'Cada fotografía puede pesar hasta '.ReglasEvidencia::MAX_MB_FOTO.' MB.',
            'articulos.*.comprobantes.*.mimes' => 'Los documentos deben ser PDF o una imagen.',
            'articulos.*.comprobantes.*.max' => 'Cada documento puede pesar hasta '.ReglasEvidencia::MAX_MB_DOCUMENTO.' MB.',
            'actas.*.mimes' => 'Los documentos deben ser PDF o una imagen.',
            'actas.*.max' => 'Cada documento puede pesar hasta '.ReglasEvidencia::MAX_MB_DOCUMENTO.' MB.',
            'documentos.*.mimes' => 'Los documentos deben ser PDF o una imagen.',
            'documentos.*.max' => 'Cada documento puede pesar hasta '.ReglasEvidencia::MAX_MB_DOCUMENTO.' MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'id_division' => 'unidad / división',
            'fecha_hora' => 'fecha y hora',
            'lugar' => 'lugar',
            'funcionario_responsable' => 'funcionario responsable',
            'numero_acta' => 'número de acta',
            'articulos.*.tipo_articulo_id' => 'tipo de artículo',
            'articulos.*.descripcion' => 'descripción',
            'articulos.*.cantidad' => 'cantidad',
            'articulos.*.numero_serie' => 'número de serie',
            'articulos.*.funcionario_recoge' => 'funcionario que recogió',
            'articulos.*.fecha_recepcion' => 'fecha de recepción',
        ];
    }
}
