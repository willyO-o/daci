<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Texto base de la ficha de un módulo de antecedentes (como `FichaRegistroPlantilla`).
 */
abstract class PlantillaAntecedente extends Model
{
    protected $fillable = [
        'introduccion',
        'requerimiento',
        'persona',
        'resultado_busqueda',
        'nota_certificacion',
        'nota_general',
        'estado',
    ];
}
