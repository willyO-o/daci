<?php

namespace App\Http\Controllers;

use App\Lib\ReportesPdf;
use App\Models\FichaRegistro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Ficha de registro de un registro criminal: se edita en la vista previa, se guarda (numerada por año) y se
 * descarga en PDF con el formato oficial del sistema anterior (`App\Lib\ReportesPdf`).
 */
class FichaRegistroController extends Controller
{
    /**
     * Campos editables de la ficha.
     *
     * @var list<string>
     */
    private const CAMPOS = [
        'caso_cud', 'introduccion', 'requerimiento', 'persona', 'resultado_busqueda', 'nota_certificacion',
        'nota_general', 'fecha_literal',
    ];

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'registro_criminal_id' => ['required', 'exists:registro_criminal,id'],
            'codigo' => ['nullable', 'string', 'size:32'],
        ]);

        // Sólo letras, números, acentos y la puntuación de un documento (sin emojis ni marcas), igual que antes.
        $datos = collect($request->only(self::CAMPOS))
            ->map(fn ($valor) => is_string($valor)
                ? trim(preg_replace('/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s,;:\/#.\'"\-\_\(\)•\n\r\t]/u', '', $valor))
                : $valor)
            ->put('registro_criminal_id', $request->integer('registro_criminal_id'))
            ->all();

        $ficha = $request->filled('codigo')
            ? FichaRegistro::whereRaw('MD5(id) = ?', [$request->input('codigo')])->first()
            : null;

        if ($ficha) {
            $ficha->update($datos);
        } else {
            $ficha = FichaRegistro::create($datos);
        }

        $codigo = md5((string) $ficha->id);

        return response()->json([
            'data' => ['url' => route('ficha-registro.pdf', ['codigo' => $codigo]), 'codigo' => $codigo],
            'message' => 'Ficha de registro creada exitosamente',
        ]);
    }

    public function pdf(string $codigo): Response
    {
        $ficha = FichaRegistro::whereRaw('MD5(id) = ?', [$codigo])->firstOrFail();

        $contenido = (new ReportesPdf)->fichaRegistro($ficha, 'S');

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="ficha-registro-'.str_pad((string) $ficha->numero_ficha, 3, '0', STR_PAD_LEFT).'-'.$ficha->anio_ficha.'.pdf"',
        ]);
    }
}
