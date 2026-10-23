<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Ciudad y país aproximados de una IP pública. Reemplaza a `stevebauman/location` (sin soporte para Laravel 13)
 * usando el mismo servicio que ese paquete traía por defecto (ip-api.com). Devuelve las mismas claves que la vista
 * de auditoría ya leía (`cityName`, `countryName`, `regionName`).
 */
class UbicacionIp
{
    /**
     * @return array{cityName: ?string, regionName: ?string, countryName: ?string}|null
     */
    public static function buscar(?string $ip): ?array
    {
        if ($ip === null || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        return Cache::remember("ubicacion_ip:{$ip}", now()->addDay(), function () use ($ip): ?array {
            try {
                $respuesta = Http::timeout(3)
                    ->get("http://ip-api.com/json/{$ip}", ['fields' => 'status,country,regionName,city'])
                    ->json();
            } catch (Throwable) {
                return null;
            }

            if (($respuesta['status'] ?? null) !== 'success') {
                return null;
            }

            return [
                'cityName' => $respuesta['city'] ?? null,
                'regionName' => $respuesta['regionName'] ?? null,
                'countryName' => $respuesta['country'] ?? null,
            ];
        });
    }
}
