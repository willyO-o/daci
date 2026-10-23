<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Juzgado extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'juzgado';

    protected $fillable = [
        'nombre_juzgado',
        'estado_juzgado',
    ];

    /**
     * Relación con mandamientos
     */
    public function mandamientos()
    {
        return $this->hasMany(Mandamiento::class, 'id_juzgado');
    }

    public static function idJuzgadoNombre($nombre)
    {
        // fisrtor create
        if (empty($nombre)) {
            return null;
        }
        $juzgado = static::firstOrCreate(['nombre_juzgado' => $nombre]);

        return $juzgado->id;
    }
}
