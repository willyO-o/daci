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

    /**
     * Suma el registro abierto a la consulta auditada `$identificador` del usuario. Devuelve si la encontró.
     */
    public static function agregarIdsAccedidos($identificador, $id, $modulo): bool
    {
        // Sin identificador no hay consulta a la que sumar el acceso (el sistema anterior lo sumaba a otra con identificador nulo).
        if (empty($identificador)) {
            return false;
        }

        // Sólo la consulta del propio usuario: un identificador ajeno no altera la auditoría de otra persona.
        $registro = self::where('identificador', $identificador)->where('user_id', auth()->id())->first();
        if ($registro) {
            // formato de objeto [{id: 1, fecha_acceso: '2024-06-01T12:00:00Z'}, {...}]
            $idsExistentes = $registro->ids_accedidos ?? [];
            $idsActualizados = array_merge($idsExistentes, [['id' => $id, 'fecha_acceso' => now()->toDateTimeString(), 'modulo' => $modulo]]);
            $registro->ids_accedidos = $idsActualizados;
            $registro->save();

            return true;
        }

        return false;
    }

    /**
     * Minutos en que la misma búsqueda (o el mismo detalle) del mismo usuario no se vuelve a registrar: paginar, volver
     * a la pantalla o recargarla no llena la auditoría de filas repetidas.
     */
    public const MINUTOS_SIN_REPETIR = 10;

    /**
     * Nombre legible de cada módulo en la pantalla de auditoría (los demás se muestran tal cual).
     *
     * @var array<string, string>
     */
    public const ETIQUETAS = [
        'consulta_mandamientos' => 'Consulta de mandamientos',
        'consulta_registros' => 'Consulta de registro criminal',
        'consulta_faltas_contravenciones' => 'Consulta de faltas y contravenciones',
        'listado_mandamientos' => 'Búsqueda en Mandamientos',
        'listado_registro_criminal' => 'Búsqueda en Registro Criminal',
        'listado_faltas_contravenciones' => 'Búsqueda en Faltas y Contravenciones',
        'listado_personas' => 'Búsqueda en Personas',
        'listado_telefonos' => 'Búsqueda en Teléfonos',
        'listado_imeis' => 'Búsqueda en IMEIs',
        'listado_vehiculos' => 'Búsqueda en Vehículos',
        'listado_usuarios' => 'Búsqueda en Usuarios',
        'detalle_mandamiento' => 'Detalle de mandamiento',
        'detalle_registro_criminal' => 'Detalle de registro criminal',
        'detalle_faltas_contravenciones' => 'Detalle de faltas y contravenciones',
        'detalle_persona' => 'Detalle de persona',
        'detalle_telefono' => 'Detalle de teléfono',
        'detalle_imei' => 'Detalle de IMEI',
        'detalle_vehiculo' => 'Detalle de vehículo',
        'exportar_reporte' => 'Exportación de reporte',
    ];

    /**
     * Búsqueda en un listado (Personas, Teléfonos…). Sólo si hay algún criterio: abrir el listado sin filtros no es una
     * consulta. La misma búsqueda del mismo usuario dentro de MINUTOS_SIN_REPETIR no se repite. Nunca interrumpe la
     * petición: si falla, queda en el log.
     *
     * @param  array<string, mixed>  $criterios
     */
    public static function registrarBusqueda(User $usuario, string $modulo, array $criterios, int $resultados): void
    {
        // Elegir sólo el campo («Filtrar por») sin escribir nada no es una búsqueda.
        if (blank($criterios['busqueda'] ?? null)) {
            unset($criterios['tipo_filtro']);
        }

        $criterios = array_filter($criterios, fn ($valor) => $valor !== null && $valor !== '' && $valor !== []);

        if ($criterios === []) {
            return;
        }

        rescue(function () use ($usuario, $modulo, $criterios, $resultados) {
            $ultima = self::query()
                ->where('user_id', $usuario->id)
                ->where('modulo', $modulo)
                ->where('created_at', '>=', now()->subMinutes(self::MINUTOS_SIN_REPETIR))
                ->latest('id')
                ->first();

            if ($ultima && $ultima->criterios_consulta == $criterios) {
                return;
            }

            self::crear($usuario, $modulo, $criterios, $resultados);
        });
    }

    /**
     * Apertura del detalle de un registro. Si viene de una consulta auditada (`identificador` del usuario) se suma a
     * ella, como siempre; si no, queda como acceso propio. El mismo detalle abierto otra vez dentro de
     * MINUTOS_SIN_REPETIR no se repite. Nunca interrumpe la petición.
     */
    public static function registrarAcceso(?string $identificador, Model $registro, string $modulo): void
    {
        rescue(function () use ($identificador, $registro, $modulo) {
            $usuario = auth()->user();

            if (! $usuario || self::agregarIdsAccedidos($identificador, $registro->getKey(), $registro::class)) {
                return;
            }

            $criterios = ['registro' => class_basename($registro), 'id' => $registro->getKey()];
            $repetido = self::query()
                ->where('user_id', $usuario->id)
                ->where('modulo', $modulo)
                ->where('created_at', '>=', now()->subMinutes(self::MINUTOS_SIN_REPETIR))
                ->latest('id')
                ->first();

            if ($repetido && $repetido->criterios_consulta == $criterios) {
                return;
            }

            self::crear($usuario, $modulo, $criterios, 1, [[
                'id' => $registro->getKey(),
                'fecha_acceso' => now()->toDateTimeString(),
                'modulo' => $registro::class,
            ]]);
        });
    }

    /**
     * Exportación de un reporte (todos los registros de un tipo). Siempre se registra.
     */
    public static function registrarExportacion(User $usuario, string $tipo, int $registros): void
    {
        rescue(fn () => self::crear($usuario, 'exportar_reporte', ['reporte' => $tipo], $registros));
    }

    /**
     * @param  array<string, mixed>  $criterios
     * @param  list<array{id: mixed, fecha_acceso: string, modulo: string}>|null  $accesos
     */
    private static function crear(User $usuario, string $modulo, array $criterios, int $resultados, ?array $accesos = null): void
    {
        $agente = new Agent;

        self::create([
            'user_id' => $usuario->id,
            'rol_usuario' => mb_substr($usuario->nombreRol() ?? 'desconocido', 0, 50),
            'modulo' => $modulo,
            'criterios_consulta' => $criterios,
            'cantidad_resultados' => $resultados,
            'ids_accedidos' => $accesos,
            'ip_usuario' => (string) request()->ip(),
            'user_agent' => [
                'browser' => $agente->browser(),
                'platform' => $agente->platform(),
                'device' => $agente->device(),
            ],
            'identificador' => null,
        ]);
    }
}
