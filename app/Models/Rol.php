<?php

namespace App\Models;

use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Models\Role;

/**
 * Rol de Spatie con la `descripcion` heredada de la tabla de roles del sistema anterior.
 */
class Rol extends Role implements AuditableContract
{
    use Auditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'descripcion',
    ];
}
