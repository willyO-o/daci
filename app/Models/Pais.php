<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Pais extends Model implements AuditableContract
{
    use Auditable;

    //
    protected $table = 'pais';

    protected $fillable = ['pais', 'gentilicio'];
}
