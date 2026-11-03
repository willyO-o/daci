<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class VehiculoCaso extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'vehiculo_caso';

    protected $fillable = [
        'vehiculo_id',
        'persona_id',
        'registro_criminal_id',
        'tipo',
        'numero_informacion',
        'id_caso',
    ];

    /**
     * Relación con Vehiculo
     */
    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_id');
    }

    /**
     * Relación con Persona
     */
    public function persona()
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    /**
     * Relación con RegistroCriminal
     */
    public function registroCriminal()
    {
        return $this->belongsTo(RegistroCriminal::class, 'registro_criminal_id');
    }

    /**
     * Caso o CUD (`id_caso`, D-037). Nulo si la fila no tiene caso. No se llama `caso()` porque `telefono` ya tiene esa columna.
     */
    public function casoVinculado()
    {
        return $this->belongsTo(Caso::class, 'id_caso');
    }
}
