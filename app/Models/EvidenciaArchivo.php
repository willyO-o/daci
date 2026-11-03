<?php

namespace App\Models;

use App\Support\Evidencias\ReglasEvidencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Fotografía o documento de una evidencia o de uno de sus artículos (tabla `evidencia_archivo`). El archivo vive en el
 * disco PRIVADO (`storage/app/private/evidencias/...`): nunca tiene URL pública; se sirve por
 * `evidencias.archivos.show` según el permiso (los documentos piden `evidencias.documentos`).
 */
class EvidenciaArchivo extends Model implements AuditableContract
{
    use Auditable;
    use SoftDeletes;

    public const DISCO = 'local';

    protected $table = 'evidencia_archivo';

    protected $fillable = [
        'evidencia_id',
        'evidencia_articulo_id',
        'tipo',
        'nombre_archivo',
        'ruta',
        'mime',
        'tamano',
        'descripcion',
        'es_publico',
        'user_id',
    ];

    protected $casts = [
        'es_publico' => 'boolean',
    ];

    /** La ruta en disco no sale a las pantallas: se usa la URL protegida. */
    protected $hidden = ['ruta'];

    protected $appends = ['url', 'es_imagen', 'privado'];

    public function evidencia()
    {
        return $this->belongsTo(Evidencia::class, 'evidencia_id');
    }

    public function articulo()
    {
        return $this->belongsTo(EvidenciaArticulo::class, 'evidencia_articulo_id');
    }

    public function getUrlAttribute(): string
    {
        return route('evidencias.archivos.show', $this->id);
    }

    public function getEsImagenAttribute(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function getPrivadoAttribute(): bool
    {
        return ReglasEvidencia::esPrivado($this->tipo);
    }
}
