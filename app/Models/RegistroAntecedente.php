<?php

namespace App\Models;

use App\Support\Antecedentes\ModuloAntecedente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Registro de un módulo de antecedentes (Faltas, Contravenciones): mismos campos y reglas que `RegistroCriminal`,
 * cada módulo en su propia tabla. Las subclases sólo dicen a qué módulo pertenecen.
 */
abstract class RegistroAntecedente extends Model implements AuditableContract
{
    use Auditable;
    use SoftDeletes;

    protected $fillable = [
        'nro_registro',
        'fecha_registro',
        'nombre_supuesto',
        'alias',
        'especialidad',
        'edad_aproximada',
        'nombre_conyuge',
        'domicilio',
        'rasgos',
        'modus_operandi',
        'zonas_opera',
        'observaciones',
        'id_persona',
        'id_division',
        'id_usuario',
        'telefono',
        'estatura',
        'peso',
        'cud',
        'caracteristicas_particulares',
        'hijos',
    ];

    protected $casts = [
        'fecha_registro' => 'date',
    ];

    abstract public static function modulo(): ModuloAntecedente;

    /**
     * Autor y número correlativo del módulo (como `RegistroCriminal`). El número cuenta también los eliminados para
     * no repetir uno ya usado.
     */
    protected static function booted(): void
    {
        static::creating(function (RegistroAntecedente $registro) {
            $registro->id_usuario = auth()->id();
            $registro->nro_registro = ((int) static::withTrashed()->max('nro_registro')) + 1;
        });
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'id_persona');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'id_division');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(static::modulo()->modeloFoto(), static::modulo()->claveFoto());
    }

    public function fichas(): HasMany
    {
        return $this->hasMany(static::modulo()->modeloFicha(), static::modulo()->claveFicha());
    }
}
