<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Foto FRONTAL / LATERAL de un registro de antecedentes (como `FotosRegistro`), en la tabla de su módulo.
 */
abstract class FotoAntecedente extends Model implements AuditableContract
{
    use Auditable;

    public const TIPOS = ['FRONTAL', 'LATERAL'];
}
