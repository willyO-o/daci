<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Artículo de una evidencia (tabla `evidencia_articulo`), con su publicación individual: `estado_publicacion`,
 * `publicado_at`, `publicado_por` y `motivo_publicacion` (los cambios quedan en la Auditoría de cambios).
 */
class EvidenciaArticulo extends Model implements AuditableContract
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'evidencia_articulo';

    protected $fillable = [
        'evidencia_id',
        'tipo_articulo_id',
        'descripcion',
        'marca',
        'modelo',
        'color',
        'imei_1',
        'imei_2',
        'numero_serie',
        'cantidad',
        'unidad_medida',
        'estado_fisico',
        'funcionario_recoge',
        'fecha_recepcion',
        'observaciones',
        'estado_publicacion',
        'publicado_at',
        'publicado_por',
        'motivo_publicacion',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'fecha_recepcion' => 'date:Y-m-d',
        'publicado_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (EvidenciaArticulo $articulo) {
            $articulo->codigo_publico ??= (string) Str::ulid();
        });
    }

    public function evidencia()
    {
        return $this->belongsTo(Evidencia::class, 'evidencia_id');
    }

    public function tipo()
    {
        return $this->belongsTo(TipoArticulo::class, 'tipo_articulo_id');
    }

    public function archivos()
    {
        return $this->hasMany(EvidenciaArchivo::class, 'evidencia_articulo_id');
    }

    public function autorizadoPor()
    {
        return $this->belongsTo(User::class, 'publicado_por');
    }
}
