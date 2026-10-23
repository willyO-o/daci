<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class EstacionServicio extends Model implements AuditableContract
{
    use Auditable;

    //
    protected $table = 'estacion_servicio';

    protected $fillable = [
        'eess',
        'nit',
        'telefono',
    ];

    public function cargios()
    {
        return $this->hasMany(Cargio::class, 'estacion_servicio_id');
    }

    public static function idEstacionDatos($nit, $eess)
    {
        $estacion = static::where('nit', $nit)->first();

        if (! $estacion) {
            $estacion = static::where('eess', $eess)->first();
        }

        if (! $estacion) {
            $estacion = static::create([
                'eess' => $eess,
                'nit' => $nit,
            ]);
        }

        return $estacion->id;
    }
}
