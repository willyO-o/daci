<?php

namespace App\Models;

use App\Support\UbicacionIp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jenssegers\Agent\Agent;

class AuditarConsultas extends Model
{
    use SoftDeletes;

    protected $table = 'auditar_consultas';

    protected $fillable = [
        'user_id',
        'rol_usuario',
        'modulo',
        'criterios_consulta',
        'cantidad_resultados',
        'ids_accedidos',
        'ip_usuario',
        'user_agent',
        'identificador',
    ];

    protected $casts = [
        'criterios_consulta' => 'array',
        'ids_accedidos' => 'array',
        'user_agent' => 'array',
    ];

    // adicionar columna

    /**
     * Relación con el modelo User
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Accesor para obtener información de ubicación basada en la IP
     */
    public function getLocationInfo()
    {
        return UbicacionIp::buscar($this->ip_usuario);
    }

    public static function registrar($user, $modulo, $request)
    {

        $agent = new Agent;
        self::create([
            'user_id' => $user->id,
            'rol_usuario' => $user->nombreRol() ?? 'desconocido',
            'modulo' => $modulo,
            'criterios_consulta' => [
                'tipo_filtro' => $request->get('tipo_filtro', $request->get('filtro')),
                'busqueda' => $request->get('search'),
                'fecha_inicio' => $request->get('fecha_inicio'),
                'fecha_fin' => $request->get('fecha_fin'),
            ],
            'cantidad_resultados' => $request->get('cantidad_resultados', 0),
            'ip_usuario' => request()->ip(),
            'user_agent' => [
                'browser' => $agent->browser(),
                'platform' => $agent->platform(),
                'device' => $agent->device(),
            ],
            'identificador' => $request->get('identificador', null),
        ]);
    }

    public static function agregarIdsAccedidos($identificador, $id, $modulo)
    {
        // Sin identificador no hay consulta a la que sumar el acceso (el sistema anterior lo sumaba a otra con identificador nulo).
        if (empty($identificador)) {
            return;
        }

        $registro = self::where('identificador', $identificador)->first();
        if ($registro) {
            // formato de objeto [{id: 1, fecha_acceso: '2024-06-01T12:00:00Z'}, {...}]
            $idsExistentes = $registro->ids_accedidos ?? [];
            $idsActualizados = array_merge($idsExistentes, [['id' => $id, 'fecha_acceso' => now()->toDateTimeString(), 'modulo' => $modulo]]);
            $registro->ids_accedidos = $idsActualizados;
            $registro->save();
        }
    }
}
