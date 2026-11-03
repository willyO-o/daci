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
        'id_caso',

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
            ->withPivot('tipo', 'numero_informacion', 'registro_criminal_id', 'id_caso')
            ->withTimestamps();
    }

    /**
     * Caso o CUD (`id_caso`, D-037). Nulo si la fila no tiene caso. No se llama `caso()` porque `telefono` ya tiene esa columna.
     */
    public function casoVinculado()
    {
        return $this->belongsTo(Caso::class, 'id_caso');
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
