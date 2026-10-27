<?php

namespace App\Models;

/**
 * Foto de un registro de Faltas y Contravenciones (tabla `foto_falta_contravencion`).
 */
class FotoFaltaContravencion extends FotoAntecedente
{
    protected $table = 'foto_falta_contravencion';

    protected $fillable = ['tipo', 'ruta_archivo', 'id_falta_contravencion', 'id_usuario'];
}
