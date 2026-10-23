<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Cargio extends Model implements AuditableContract
{
    use Auditable;

    //
    protected $table = 'cargio';

    protected $fillable = [
        'vehiculo_id',
        'estacion_servicio_id',
        'nit_consumidor',
        'razon_social',
        'departamento',
        'producto',
        'factura',
        'nro_autorizacion',
        'codigo_control',
        'cantidad',
        'monto',
        'fecha_venta',
    ];

    protected $casts = [
        'fecha_venta' => 'datetime',
    ];

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_id');
    }

    public function estacionServicio()
    {
        return $this->belongsTo(EstacionServicio::class, 'estacion_servicio_id');
    }
}
