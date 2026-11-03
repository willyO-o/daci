<?php

namespace App\Models;

use App\Support\Casos\MigradorCasos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Caso o CUD (tabla `caso`). Lo usan, por su columna `id_caso`, `telefono`, `registro_criminal`,
 * `falta_contravencion`, `vehiculo` y `vehiculo_caso` (D-037).
 */
class Caso extends Model implements AuditableContract
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'caso';

    protected $fillable = [
        'codigo',
        'descripcion',
        'estado',
        'user_id',
    ];

    /**
     * Regla de validación del campo `id_caso` de los formularios (teléfono, vehículo, registro criminal, faltas).
     *
     * @return list<mixed>
     */
    public static function reglaId(): array
    {
        return ['nullable', 'integer', Rule::exists('caso', 'id')->whereNull('deleted_at')];
    }

    /**
     * Lo que se guarda en una fila al elegir (o quitar) su caso: `id_caso` y la columna de texto heredada (`caso`,
     * `cud`…), que mientras dure la transición se sigue escribiendo con el código del caso (D-037, fase 3) para que
     * búsquedas, reportes y la vuelta atrás sigan funcionando.
     *
     * Sin caso: el texto queda nulo, salvo en una fila que nunca tuvo caso vinculado y sí un texto heredado (p. ej.
     * «S/N» o algo cargado antes de migrar): ese texto se conserva, para no perderlo al editar otro dato.
     *
     * `$largo` es el de la columna de texto (`vehiculo_caso.numero_informacion` admite 140): la copia se recorta, el
     * `id_caso` queda completo.
     *
     * @return array<string, mixed>
     */
    public static function columnas(mixed $idCaso, string $columnaTexto, ?Model $actual = null, int $largo = 255): array
    {
        $id = filled($idCaso) ? (int) $idCaso : null;

        if ($id !== null) {
            $codigo = static::query()->whereKey($id)->value('codigo');

            return ['id_caso' => $id, $columnaTexto => $codigo === null ? null : mb_substr($codigo, 0, $largo)];
        }

        if ($actual !== null && $actual->getAttribute('id_caso') === null) {
            return ['id_caso' => null];
        }

        return ['id_caso' => null, $columnaTexto => null];
    }

    /**
     * Id del caso de un texto (importaciones): se limpia como en la migración; si no existe, se crea. Nulo, vacío o
     * relleno («S/N», «-») → null.
     */
    public static function resolver(?string $texto): ?int
    {
        $codigo = MigradorCasos::normalizar($texto);

        if ($codigo === null) {
            return null;
        }

        // La colación no distingue mayúsculas ni tildes, igual que el índice único.
        $caso = static::withTrashed()->where('codigo', $codigo)->first();

        if ($caso?->trashed()) {
            $caso->restore();
        }

        return ($caso ?? static::create(['codigo' => $codigo, 'estado' => 'ACTIVO', 'user_id' => auth()->id()]))->id;
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function telefonos()
    {
        return $this->hasMany(Telefono::class, 'id_caso');
    }

    public function registrosCriminales()
    {
        return $this->hasMany(RegistroCriminal::class, 'id_caso');
    }

    public function faltasContravenciones()
    {
        return $this->hasMany(FaltaContravencion::class, 'id_caso');
    }

    public function vehiculos()
    {
        return $this->hasMany(Vehiculo::class, 'id_caso');
    }

    /**
     * Vínculos vehículo–persona (`vehiculo_caso`) con este caso.
     */
    public function vinculosVehiculo()
    {
        return $this->hasMany(VehiculoCaso::class, 'id_caso');
    }
}
