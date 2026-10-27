<?php

namespace App\Models;

use App\Support\Antecedentes\ModuloAntecedente;

/**
 * Registro de Faltas y Contravenciones (tabla `falta_contravencion`, mismos campos que `registro_criminal`).
 */
class FaltaContravencion extends RegistroAntecedente
{
    protected $table = 'falta_contravencion';

    public static function modulo(): ModuloAntecedente
    {
        return ModuloAntecedente::FaltaContravencion;
    }
}
