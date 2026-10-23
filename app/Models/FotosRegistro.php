<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class FotosRegistro extends Model implements AuditableContract
{
    use Auditable;

    //
    protected $table = 'fotos_registro';

    protected $fillable = [
        'tipo',
        'ruta_archivo',
        'id_registro_criminal',
        'id_usuario',
    ];
}
