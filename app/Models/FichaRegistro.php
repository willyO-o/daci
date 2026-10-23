<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class FichaRegistro extends Model implements AuditableContract
{
    use Auditable;
    //
    use SoftDeletes;

    protected $table = 'ficha_registro';

    protected $fillable = [
        'numero_ficha',
        'anio_ficha',
        'caso_cud',
        'introduccion',
        'requerimiento',
        'persona',
        'resultado_busqueda',
        'nota_certificacion',
        'nota_general',
        'fecha_literal',
        'otros_datos',
        'registro_criminal_id',
        'user_id',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($fichaRegistro) {
            $anioActual = date('Y');
            $ultimoRegistro = self::whereYear('created_at', $anioActual)->orderBy('numero_ficha', 'desc')->first();

            if ($ultimoRegistro) {
                $fichaRegistro->numero_ficha = $ultimoRegistro->numero_ficha + 1;
            } else {
                $fichaRegistro->numero_ficha = 1;
            }

            $fichaRegistro->anio_ficha = $anioActual;
            $fichaRegistro->user_id = auth()->id();
        });
    }
}
