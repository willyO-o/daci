<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Caso o CUD (tabla `caso`). Lo usan, por su columna `id_caso`, `telefono`, `registro_criminal`,
 * `falta_contravencion`, `vehiculo` y `vehiculo_caso` (D-037).
 */
class Caso extends Model implements AuditableContract
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'caso';

    protected $fillable = [
        'codigo',
        'descripcion',
        'estado',
        'user_id',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function telefonos()
    {
        return $this->hasMany(Telefono::class, 'id_caso');
    }

    public function registrosCriminales()
    {
        return $this->hasMany(RegistroCriminal::class, 'id_caso');
    }

    public function faltasContravenciones()
    {
        return $this->hasMany(FaltaContravencion::class, 'id_caso');
    }

    public function vehiculos()
    {
        return $this->hasMany(Vehiculo::class, 'id_caso');
    }

    /**
     * Vínculos vehículo–persona (`vehiculo_caso`) con este caso.
     */
    public function vinculosVehiculo()
    {
        return $this->hasMany(VehiculoCaso::class, 'id_caso');
    }
}
