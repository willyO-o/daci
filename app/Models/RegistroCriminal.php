<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class RegistroCriminal extends Model implements AuditableContract
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'registro_criminal';

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

    // forto frente y perfil
    protected $appends = ['foto_frente', 'foto_perfil'];

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'id_persona');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'id_division');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function fotos()
    {
        return $this->hasMany(FotosRegistro::class, 'id_registro_criminal');
    }

    public function getFotoFrenteAttribute()
    {
        $fotoFrente = $this->fotos()->where('tipo', 'FRONTAL')->first();

        return $fotoFrente ? $fotoFrente->ruta_archivo : null;
    }

    public function getFotoPerfilAttribute()
    {
        $fotoPerfil = $this->fotos()->where('tipo', 'LATERAL')->first();

        return $fotoPerfil ? $fotoPerfil->ruta_archivo : null;
    }

    public function otrosRegistrosCriminales($idRegistro)
    {
        return $this->persona->registroCriminal()->where('id', '!=', $idRegistro)->get();
    }

    // capturar el usuario al momento de registrar un nuevo registro criminal

    public static function boot()
    {
        parent::boot();

        static::creating(function ($registro) {
            $registro->id_usuario = auth()->id();
            $registro->nro_registro = self::lastNroRegistro() + 1;
        });
    }

    protected static function lastNroRegistro()
    {
        $ultimoRegistro = self::orderBy('nro_registro', 'desc')->first();

        return $ultimoRegistro ? $ultimoRegistro->nro_registro : 0;
    }

    public static function getRegistros($filters = [])
    {
        $query = self::select('registro_criminal.*')
            ->join('persona', 'registro_criminal.id_persona', '=', 'persona.id')
            ->join('division', 'registro_criminal.id_division', '=', 'division.id')
            ->leftJoin('pais', 'persona.id_pais', '=', 'pais.id')
            ->addSelect(
                [
                    'persona' => DB::raw('persona.nombres, persona.apellidos, persona.ci, persona.genero, persona.fecha_nacimiento'),
                    'division.division as division',
                    'pais.gentilicio as gentilicio',
                    'foto_frente' => FotosRegistro::select('ruta_archivo')
                        ->whereColumn('id_registro_criminal', 'registro_criminal.id')
                        ->where('tipo', 'FRONTAL')
                        ->limit(1),
                    'foto_perfil' => FotosRegistro::select('ruta_archivo')
                        ->whereColumn('id_registro_criminal', 'registro_criminal.id')
                        ->where('tipo', 'LATERAL')
                        ->limit(1),
                    'imagenes' => DB::raw('(SELECT JSON_ARRAYAGG(ruta_archivo) FROM fotos_registro WHERE id_registro_criminal = registro_criminal.id) as imagenes'),
                ]
            )->when(! empty($filters['filtro']), function ($query) use ($filters) {

                switch ($filters['filtro']) {
                    case 'nombres':
                        $query->whereRaw("CONCAT(persona.nombres, ' ', persona.apellidos) LIKE ?", ['%'.$filters['valor'].'%']);
                        break;
                    case 'apellidos':
                        $query->where('persona.apellidos', 'like', '%'.$filters['valor'].'%');
                        break;
                    case 'alias':
                        $query->where('registro_criminal.alias', 'like', '%'.$filters['valor'].'%');
                        break;
                    case 'ci':
                        $query->where('persona.ci', 'like', '%'.$filters['valor'].'%');
                        break;
                    case 'celular':
                        $query->where('registro_criminal.telefono', 'like', '%'.$filters['valor'].'%');
                        break;
                    case 'cud':
                        $query->where('registro_criminal.cud', 'like', '%'.$filters['valor'].'%');
                        break;
                    case 'padre':
                        $query->where('registro_criminal.nombre_conyuge', 'like', '%'.$filters['valor'].'%');
                        break;
                    case 'nombre_supuesto':
                        $query->where('registro_criminal.nombre_supuesto', 'like', '%'.$filters['valor'].'%');
                        break;
                }

            })

            ->orderBy('registro_criminal.created_at', 'desc');

        if (! empty($filters['nombre_supuesto'])) {
            $query->where('nombre_supuesto', 'like', '%'.$filters['nombre_supuesto'].'%');
        }

        if (! empty($filters['alias'])) {
            $query->where('alias', 'like', '%'.$filters['alias'].'%');
        }

        if (! empty($filters['especialidad'])) {
            $query->where('especialidad', 'like', '%'.$filters['especialidad'].'%');
        }

        if (! empty($filters['id_division'])) {
            $query->where('id_division', $filters['id_division']);
        }

        return $query;
    }
}
