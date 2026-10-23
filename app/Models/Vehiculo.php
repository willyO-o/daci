<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Vehiculo extends Model implements AuditableContract
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'vehiculo';

    protected $fillable = [
        'placa',
        'descripcion',
        'responsable',
        'caso_relacionado',

    ];

    /**
     * Relación con VehiculoCaso
     */
    public function casos()
    {
        return $this->hasMany(VehiculoCaso::class, 'vehiculo_id');
    }

    /**
     * Relación con Persona a través de VehiculoCaso
     */
    public function personas()
    {
        return $this->belongsToMany(Persona::class, 'vehiculo_caso', 'vehiculo_id', 'persona_id')
            ->withPivot('tipo', 'numero_informacion', 'registro_criminal_id')
            ->withTimestamps();
    }

    public function inspecciones()
    {
        return $this->hasMany(InspeccionTecnica::class, 'vehiculo_id');
    }

    public function multimedia()
    {
        return $this->hasMany(Multimedia::class, 'id_vehiculo');
    }

    public function cargios()
    {
        return $this->hasMany(Cargio::class, 'vehiculo_id')
            ->orderBy('fecha_venta', 'desc');
    }
}
