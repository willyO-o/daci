<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
// usar soft deletes
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Mandamiento extends Model implements AuditableContract
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'mandamiento';

    protected $fillable = [
        'hoja_ruta',
        'estado',
        'fecha_ejecucion',
        'detalle_ejecucion',
        'asignado',
        'tipo_documento',
        'actividades_realizadas',
        'id_usuario',
        'id_juzgado',
        'id_delito',
        'id_tipo_mandamiento',
        'id_persona',
        'telefono',
        'vehiculos',
        'domicilio',
        'ejecutado_por',

    ];

    protected $casts = [
        'fecha_ejecucion' => 'date',
    ];

    public static $rules = [
        'hoja_ruta' => 'nullable|string|max:100',
        'telefono' => 'nullable|string|max:150',
        'estado' => 'required|string|max:200',
        'id_juzgado' => 'required|exists:juzgado,id',
        'id_delito' => 'required|exists:delito,id',
        'id_tipo_mandamiento' => 'required|exists:tipo_mandamiento,id',
        'id_persona' => 'required|exists:persona,id',
        'actividades_realizadas' => 'nullable|string',
        'fecha_ejecucion' => 'nullable|date|before_or_equal:today',
        'tipo_documento' => 'nullable|string|max:255',
        'asignado' => 'nullable|string|max:255',
        'ejecutado_por' => 'nullable|required_if:estado,EJECUTADO|string|max:200',
        'imagen_mandamiento' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        'acta_ejecucion' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
    ];

    /**
     * Relación con usuario
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    /**
     * Relación con juzgado
     */
    public function juzgado()
    {
        return $this->belongsTo(Juzgado::class, 'id_juzgado');
    }

    /**
     * Relación con delito
     */
    public function delito()
    {
        return $this->belongsTo(Delito::class, 'id_delito');
    }

    /**
     * Relación con tipo de mandamiento
     */
    public function tipoMandamiento()
    {
        return $this->belongsTo(TipoMandamiento::class, 'id_tipo_mandamiento');
    }

    /**
     * Relación con persona
     */
    public function persona()
    {
        return $this->belongsTo(Persona::class, 'id_persona');
    }

    /**
     * Relación con multimedia
     */
    public function multimedia()
    {
        return $this->hasMany(Multimedia::class, 'id_mandamiento');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($mandamiento) {
            $mandamiento->id_usuario = auth()->id() ?? null; // Asignar el ID del usuario autenticado o null si no hay ninguno
        });
    }

    public static function getMandamientos($filtros = [], $idMandamiento = null, $md5Id = false)
    {

        $search = $filtros['search'] ?? null;

        $tipoFiltro = $filtros['tipo_filtro'] ?? null;

        $query = self::select('mandamiento.*')
            ->leftJoin('persona', 'mandamiento.id_persona', '=', 'persona.id')
            ->leftJoin('delito', 'mandamiento.id_delito', '=', 'delito.id')
            ->leftJoin('juzgado', 'mandamiento.id_juzgado', '=', 'juzgado.id')
            ->leftJoin('tipo_mandamiento', 'mandamiento.id_tipo_mandamiento', '=', 'tipo_mandamiento.id')
            // ->leftJoin('multimedia', 'mandamiento.id', '=', 'multimedia.id_mandamiento')
            ->addSelect([
                'nombre_completo' => DB::raw("CONCAT(COALESCE(persona.nombres, ''), ' ', COALESCE(persona.apellidos, '')) as nombre_completo"),
                'ci' => 'persona.ci',
                'nombre_delito' => 'delito.nombre_delito',
                'nombre_juzgado' => 'juzgado.nombre_juzgado',
                'tipo_mandamiento' => 'tipo_mandamiento.tipo_mandamiento',
                'archivo_mandamiento' => DB::raw("(SELECT JSON_OBJECT('ruta', m.ruta, 'tipo_archivo', m.tipo_archivo)
                                            FROM multimedia m
                                            WHERE m.id_mandamiento = mandamiento.id AND m.tipo  = 'mandamiento'
                                            ORDER BY m.created_at DESC
                                            LIMIT 1) as archivo_mandamiento"),
                'imagenes' => DB::raw('(SELECT JSON_ARRAYAGG(m.ruta)
                                            FROM multimedia m
                                            WHERE m.id_persona = persona.id) as imagenes_persona'),
            ])
            ->addSelect([
                'acta_ejecucion' => DB::raw("(SELECT JSON_OBJECT('ruta', m1.ruta, 'tipo_archivo', m1.tipo_archivo)
                                            FROM multimedia m1
                                            WHERE m1.id_mandamiento = mandamiento.id AND m1.tipo  = 'acta_ejecucion'
                                            ORDER BY m1.created_at DESC
                                            LIMIT 1) as acta_ejecucion"),
            ])
            ->orderBy('mandamiento.id', 'desc')
            ->whereNull('persona.deleted_at')
            ->whereNull('mandamiento.deleted_at'); // Excluir mandamientos eliminados

        if ($search && empty($tipoFiltro)) {
            $search = str_replace(' ', '%', $search); // Reemplazar espacios por comodines para mejorar la búsqueda
            $query->where(function ($q) use ($search) {
                $q->where('persona.nombres', 'like', "%$search%")
                    ->orWhere('persona.apellidos', 'like', "%$search%")
                    ->orWhere('persona.ci', 'like', "%$search%")
                    ->orWhere('delito.nombre_delito', 'like', "%$search%")
                    ->orWhere('juzgado.nombre_juzgado', 'like', "%$search%")
                    ->orWhere('tipo_mandamiento.tipo_mandamiento', 'like', "%$search%")
                    ->orWhere('mandamiento.hoja_ruta', 'like', "%$search%")
                    ->orWhereRaw("CONCAT(COALESCE(persona.nombres, ''), ' ', COALESCE(persona.apellidos, '')) like ?", ["%$search%"])
                    ->orWhereRaw("CONCAT(COALESCE(persona.ci, ''), ' ', COALESCE(persona.nombres, ''), ' ', COALESCE(persona.apellidos, '')) like ?", ["%$search%"])
                    ->orWhereRaw("CONCAT(COALESCE(persona.nombres, ''), ' ', COALESCE(persona.apellidos, ''), ' ', COALESCE(persona.ci, '')) like ?", ["%$search%"]);

            });

        } elseif ($search && ! empty($tipoFiltro)) {
            $search = str_replace(' ', '%', $search); // Reemplazar espacios por comodines para mejorar la búsqueda
            switch ($tipoFiltro) {
                case 'nombre_persona':
                    $query->where(function ($q) use ($search) {
                        $q->where('persona.nombres', 'like', "%$search%")
                            ->orWhere('persona.apellidos', 'like', "%$search%")
                            ->orWhereRaw("CONCAT(COALESCE(persona.nombres, ''), ' ', COALESCE(persona.apellidos, '')) like ?", ["%$search%"]);
                    });
                    break;
                case 'ci':
                    $query->where('persona.ci', 'like', "%$search%");
                    break;
                case 'nombre_delito':
                    $query->where('delito.nombre_delito', 'like', "%$search%");
                    break;
                case 'nombre_juzgado':
                    $query->where('juzgado.nombre_juzgado', 'like', "%$search%");
                    break;
                case 'tipo_mandamiento':
                    $query->where('tipo_mandamiento.tipo_mandamiento', 'like', "%$search%");
                    break;
                case 'hoja_ruta':
                    $query->where('mandamiento.hoja_ruta', 'like', "%$search%");
                    break;
                case 'estado':
                    $query->where('mandamiento.estado', 'like', "%$search%");
                    break;
                case 'apellidos':
                    $query->where('persona.apellidos', 'like', "%$search%");
                    break;
                case 'nombre_persona':
                    $query->where('persona.nombres', 'like', "%$search%");
                    break;
                case 'nombre_completo':
                    $query->whereRaw("CONCAT(COALESCE(persona.nombres, ''), ' ', COALESCE(persona.apellidos, '')) like ?", ["%$search%"]);
                    break;
                case 'encargado':
                    $query->where('mandamiento.asignado', 'like', "%$search%");
                    break;
            }
        }

        $idDelito = $filtros['id_delito'] ?? null;
        if ($idDelito) {
            $query->where('mandamiento.id_delito', $idDelito);
        }

        $estado = $filtros['estado'] ?? null;
        if ($estado) {
            $query->where('mandamiento.estado', $estado);
        }

        // Filtro de rango de fechas de ejecución
        $fechaInicio = $filtros['fecha_inicio'] ?? null;
        $fechaFin = $filtros['fecha_fin'] ?? null;

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('mandamiento.fecha_ejecucion', [$fechaInicio, $fechaFin]);
        } elseif ($fechaInicio) {
            $query->whereDate('mandamiento.fecha_ejecucion', '>=', $fechaInicio);
        } elseif ($fechaFin) {
            $query->whereDate('mandamiento.fecha_ejecucion', '<=', $fechaFin);
        }

        if ($idMandamiento) {
            if ($md5Id) {
                $query->whereRaw('MD5(mandamiento.id) = ?', [$idMandamiento]);
            } else {
                $query->where('mandamiento.id', $idMandamiento);
            }
        }

        return $query;
    }
}
