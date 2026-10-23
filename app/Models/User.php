<?php

namespace App\Models;

use App\Support\Acceso\CatalogoPermisos;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Traits\HasRoles;

/**
 * Usuario del sistema. La tabla `users` es la del sistema anterior y no se modifica: `role_id` queda como
 * dato histórico (apuntaba a la antigua tabla `roles`, hoy `roles_legado`); el rol vigente vive en Spatie
 * (`model_has_roles`), migrado por `2026_10_22_120000_migrar_roles_a_spatie_permission`.
 */
class User extends Authenticatable implements AuditableContract
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Campos que no se guardan en la auditoría de cambios.
     *
     * @var list<string>
     */
    protected $auditExclude = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Nombre del rol vigente (el sistema asigna un único rol por usuario, igual que el anterior).
     */
    public function nombreRol(): ?string
    {
        return $this->getRoleNames()->first();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(CatalogoPermisos::ROL_SUPERADMIN);
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }
}
