<?php

namespace App\Http\Controllers;

use App\Support\Antecedentes\ModuloAntecedente;

/**
 * Faltas y Contravenciones: el funcionamiento de Registro Criminal sobre sus propias tablas ({@see AntecedenteController}).
 */
class FaltaContravencionController extends AntecedenteController
{
    protected function modulo(): ModuloAntecedente
    {
        return ModuloAntecedente::FaltaContravencion;
    }
}
