<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Tipo de artículo de Evidencias (tabla `tipo_articulo`). Su `grupo` decide qué campos pide el formulario
 * (`ReglasEvidencia::GRUPOS`). No se borra: se inactiva.
 */
class TipoArticulo extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'tipo_articulo';

    protected $fillable = [
        'nombre',
        'grupo',
        'estado',
        'user_id',
    ];

    public function articulos()
    {
        return $this->hasMany(EvidenciaArticulo::class, 'tipo_articulo_id');
    }
}
