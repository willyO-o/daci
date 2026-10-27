<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Texto base de la ficha de un módulo de antecedentes (como `FichaRegistroPlantilla`). Se edita en «Plantilla de la
 * Ficha»; los cambios quedan en la Auditoría de cambios.
 */
abstract class PlantillaAntecedente extends Model implements AuditableContract
{
    use Auditable;

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
