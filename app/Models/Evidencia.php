<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Evidencia (tabla `evidencia`): el procedimiento o acta en el que se secuestraron, recuperaron o recolectaron uno o
 * varios artículos. El CUD es su caso (`id_caso` → `caso`); la unidad, `id_division` → `division` (D-038).
 */
class Evidencia extends Model implements AuditableContract
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'evidencia';

    protected $fillable = [
        'codigo',
        'id_caso',
        'id_division',
        'fecha_hora',
        'lugar',
        'detalle_lugar',
        'funcionario_responsable',
        'numero_acta',
        'observaciones',
        'estado',
        'user_id',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
    ];

    /** Quien registra es el usuario de la sesión (como en los demás módulos). */
    protected static function booted(): void
    {
        static::creating(function (Evidencia $evidencia) {
            $evidencia->user_id ??= auth()->id();
        });
    }

    public function caso()
    {
        return $this->belongsTo(Caso::class, 'id_caso');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'id_division');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function articulos()
    {
        return $this->hasMany(EvidenciaArticulo::class, 'evidencia_id');
    }

    /** Archivos de la evidencia y de sus artículos. */
    public function archivos()
    {
        return $this->hasMany(EvidenciaArchivo::class, 'evidencia_id');
    }
}
