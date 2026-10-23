<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class ImeiTelefono extends Model implements AuditableContract
{
    use Auditable;

    //
    protected $table = 'imei_telefono';

    protected $fillable = [
        'telefono_id',
        'imei_id',
    ];

    public function telefono()
    {
        return $this->belongsTo(Telefono::class, 'telefono_id');
    }

    public function imei()
    {
        return $this->belongsTo(Imei::class, 'imei_id');
    }
}
