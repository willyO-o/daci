<?php

namespace App\Models;

use App\Support\Antecedentes\ModuloAntecedente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Persona extends Model implements AuditableContract
{
    use Auditable;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'persona';

    protected $fillable = [
        'nombres',
        'apellidos',
        'ci',
        'domicilio',
        'telefono',
        'fecha_nacimiento',
        'lugar_nacimiento',
        'complemento',
        'genero',
        'estado_civil',
        'nombre_conyuge',
        'ocupacion',
        'id_pais',
        'responsable',
        'datos_segip',
        'estado_investigacion',
        'url_documento',
        'user_id',
        'padre',
        'madre',
        'grupo_sanguineo',
        'alias',
        'nit_persona',

    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
    ];

    /**
     * Obtener el nombre completo de la persona
     */
    public function getNombreCompletoAttribute()
    {
        return trim($this->nombres.' '.$this->apellidos);
    }

    /**
     * Relación con mandamientos
     */
    public function mandamientos()
    {
        return $this->hasMany(Mandamiento::class, 'id_persona');
    }

    public function multimedia()
    {
        return $this->hasMany(Multimedia::class, 'id_persona');
    }

    public function registroCriminal()
    {
        return $this->hasMany(RegistroCriminal::class, 'id_persona');
    }

    /**
     * Personas con este C.I. y complemento. Compara sin espacios a los lados y trata como iguales NULL y '' (con
     * COALESCE): `complemento = NULL` nunca es verdadero en SQL y una persona sin complemento quedaría sin encontrar.
     * El complemento vacío busca a quien no tiene complemento.
     */
    public function scopeConCi(Builder $consulta, string $ci, ?string $complemento = null): Builder
    {
        return $consulta
            ->whereRaw("TRIM(COALESCE(ci, '')) = ?", [trim($ci)])
            ->whereRaw("TRIM(COALESCE(complemento, '')) = ?", [trim((string) $complemento)]);
    }

    public function faltasContravenciones()
    {
        return $this->hasMany(FaltaContravencion::class, 'id_persona');
    }

    public function telefonos()
    {
        return $this->hasMany(Telefono::class, 'persona_id');
    }

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'id_pais');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vehiculos()
    {
        return $this->belongsToMany(Vehiculo::class, 'vehiculo_caso', 'persona_id', 'vehiculo_id')
            ->withPivot('registro_criminal_id', 'tipo', 'numero_informacion')
            ->withTimestamps();
    }

    public function inspeccionTecnica()
    {
        return $this->hasMany(InspeccionTecnica::class, 'persona_id');
    }

    public static $rules = [
        'nombres' => 'required|string|max:255',
        'apellidos' => 'required|string|max:255',
        'ci' => 'nullable|string|max:20|unique:persona,ci',
        'fecha_nacimiento' => 'nullable|date|before:today',
        'fotos' => 'nullable|array',
        'fotos.*' => 'file|mimes:jpeg,png,jpg,webp|max:2048',
    ];

    public static function idPersonaDatos($datos)
    {
        $persona = null;
        if (! empty($datos['ci'])) {
            $persona = static::where('ci', $datos['ci'])->first();
        }

        if (! $persona && ! empty($datos['nombres'])) {
            $persona = static::whereRaw("CONCAT(nombres, ' ', apellidos) = ?", [$datos['nombres'].' '.$datos['apellidos']])->first();
        }

        if (! $persona && (! empty($datos['nombres'] || ! empty($datos['ci'])))) {
            $persona = static::create($datos);
        }

        if (! $persona) {
            return null;
        }

        return $persona->id;
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($persona) {
            $persona->user_id = auth()->id();
        });
    }

    /**
     * Obtener resumen de datos relacionados para mostrar antes de eliminar
     */
    public function getResumenDatosRelacionados()
    {
        $resumen = [
            'mandamientos' => $this->mandamientos()->count(),
            'registros_criminales' => $this->registroCriminal()->count(),
            'faltas_contravenciones' => $this->faltasContravenciones()->withTrashed()->count(),
            'telefonos' => $this->telefonos()->count(),
            'multimedia' => $this->multimedia()->count(),
            'vehiculos' => $this->vehiculos()->count(),
            'inspeccion_tecnica' => $this->inspeccionTecnica()->count(),
        ];

        return $resumen;
    }

    /**
     * Migrar todas las relaciones a otra persona
     *
     * @param  Persona  $personaDestino  - La persona hacia la que se migrarán los datos
     */
    public function migrateRelationsTo(Persona $personaDestino)
    {

        try {
            DB::beginTransaction();

            $relaciones = [
                // 'documento' => 'id_persona',
                'mandamiento' => 'id_persona',
                'registro_criminal' => 'id_persona',
                'falta_contravencion' => 'id_persona',
                'telefono' => 'persona_id',
                'multimedia' => 'id_persona',
                'vehiculo_caso' => 'persona_id',
                'inspeccion_tecnica' => 'persona_id',
            ];

            foreach ($relaciones as $tabla => $campo) {
                DB::table($tabla)
                    ->where($campo, $this->id)
                    ->update([$campo => $personaDestino->id]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return false;
        }
    }

    /**
     * Borra de forma definitiva todo lo relacionado con la persona y devuelve las rutas de los archivos que quedaron
     * sin registro, para borrarlos del disco DESPUÉS de confirmar la transacción.
     *
     * El orden respeta las claves foráneas RESTRICT de la base: el sistema anterior borraba primero
     * `registro_criminal` y fallaba siempre que el registro tuviera fotos, fichas o vehículos vinculados.
     * La tabla `documento` no se toca (rama de ciudadano): si tuviera filas, la base impide borrar la persona.
     *
     * @return list<string>
     */
    public function deleteRelationsData(): array
    {
        $registros = DB::table('registro_criminal')->where('id_persona', $this->id)->pluck('id');
        $mandamientos = DB::table('mandamiento')->where('id_persona', $this->id)->pluck('id');

        $archivos = DB::table('fotos_registro')->whereIn('id_registro_criminal', $registros)->pluck('ruta_archivo')
            ->merge(DB::table('multimedia')->where('id_persona', $this->id)->orWhereIn('id_mandamiento', $mandamientos)->pluck('ruta'))
            ->filter()
            ->values()
            ->all();

        // Faltas y Contravenciones (tablas propias del módulo): sus fotos, fichas y registros.
        foreach (ModuloAntecedente::cases() as $modulo) {
            $tabla = $modulo->tabla();
            $ids = DB::table($tabla)->where('id_persona', $this->id)->pluck('id');
            $archivos = [...$archivos, ...DB::table("foto_{$tabla}")->whereIn($modulo->claveFoto(), $ids)->pluck('ruta_archivo')->filter()->all()];
            DB::table("foto_{$tabla}")->whereIn($modulo->claveFoto(), $ids)->delete();
            DB::table("ficha_{$tabla}")->whereIn($modulo->claveFicha(), $ids)->delete();
            DB::table($tabla)->whereIn('id', $ids)->delete();
        }

        DB::table('vehiculo_caso')->where('persona_id', $this->id)->orWhereIn('registro_criminal_id', $registros)->delete();
        DB::table('inspeccion_tecnica')->where('persona_id', $this->id)->delete();
        DB::table('fotos_registro')->whereIn('id_registro_criminal', $registros)->delete();
        DB::table('ficha_registro')->whereIn('registro_criminal_id', $registros)->delete();
        DB::table('registro_criminal')->whereIn('id', $registros)->delete();
        DB::table('telefono')->where('persona_id', $this->id)->delete();
        DB::table('multimedia')->where('id_persona', $this->id)->orWhereIn('id_mandamiento', $mandamientos)->delete();
        DB::table('mandamiento')->whereIn('id', $mandamientos)->delete();

        return $archivos;
    }
}
