<?php

namespace App\Models;

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

    public function deleteRelationsData()
    {

        $relaciones = [
            // 'documento' => 'id_persona',
            'mandamiento' => 'id_persona',
            'registro_criminal' => 'id_persona',
            'telefono' => 'persona_id',
            'multimedia' => 'id_persona',
            'vehiculo_caso' => 'persona_id',
            'inspeccion_tecnica' => 'persona_id',
        ];

        foreach ($relaciones as $tabla => $campo) {
            DB::table($tabla)
                ->where($campo, $this->id)
                ->delete();
        }
    }
}
