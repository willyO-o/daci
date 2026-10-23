<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Imei extends Model implements AuditableContract
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'imei';

    protected $fillable = [
        'imei',
        'caracteristicas',
    ];

    /**
     * Un IMEI pertenece a un Teléfono
     */
    public function telefonos()
    {
        return $this->belongsToMany(Telefono::class, 'imei_telefono', 'imei_id', 'telefono_id');
    }
}
