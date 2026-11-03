<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Telefono extends Model implements AuditableContract
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'telefono';

    protected $fillable = [
        'numero_celular',
        'persona_caso',
        'caso',
        'empresa',
        'respuesta_requerimiento',
        'persona_id',
        'informacion',
        'callapp',
        'truecall',
        'uninet',
        'id_caso',
    ];

    /**
     * Relación con Persona
     */
    public function persona()
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    /**
     * Un Teléfono tiene muchos IMEIs
     */
    public function imeis()
    {
        return $this->belongsToMany(Imei::class, 'imei_telefono', 'telefono_id', 'imei_id');
    }

    /**
     * Caso o CUD (`id_caso`, D-037). Nulo si la fila no tiene caso. No se llama `caso()` porque `telefono` ya tiene esa columna.
     */
    public function casoVinculado()
    {
        return $this->belongsTo(Caso::class, 'id_caso');
    }

    public static function idTelefonoNumero($numero)
    {
        $telefono = static::where('numero_celular', $numero)->first();

        if ($telefono) {
            $telefono = static::create([
                'numero_celular' => $numero,
            ]);
        }

        return $telefono->id;
    }
}
