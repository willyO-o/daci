<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Ficha emitida de un registro de antecedentes (como `FichaRegistro`): numerada por año dentro de su módulo.
 */
abstract class FichaAntecedente extends Model implements AuditableContract
{
    use Auditable;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (FichaAntecedente $ficha) {
            $anio = (int) date('Y');
            $ficha->numero_ficha = ((int) static::withTrashed()->where('anio_ficha', $anio)->max('numero_ficha')) + 1;
            $ficha->anio_ficha = $anio;
            $ficha->user_id = auth()->id();
        });
    }
}
