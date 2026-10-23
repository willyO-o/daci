<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class InspeccionTecnica extends Model implements AuditableContract
{
    use Auditable;

    //

    protected $table = 'inspeccion_tecnica';

    protected $fillable = [
        'vehiculo_id',
        'persona_id',
        'dep',
        'resultado',
        'fecha_inspeccion',
        'anio',
    ];

    protected $casts = [
        'fecha_inspeccion' => 'datetime',
    ];

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_id');
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }
}
