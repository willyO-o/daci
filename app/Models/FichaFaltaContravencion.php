<?php

namespace App\Models;

/**
 * Ficha emitida de un registro de Faltas y Contravenciones (tabla `ficha_falta_contravencion`).
 */
class FichaFaltaContravencion extends FichaAntecedente
{
    protected $table = 'ficha_falta_contravencion';

    protected $fillable = [
        'numero_ficha', 'anio_ficha', 'caso_cud', 'introduccion', 'requerimiento', 'persona', 'resultado_busqueda',
        'nota_certificacion', 'nota_general', 'fecha_literal', 'otros_datos', 'falta_contravencion_id', 'user_id',
    ];
}
