<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FichaRegistroPlantilla extends Model
{
    //
    protected $table = 'ficha_registro_plantilla';

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
