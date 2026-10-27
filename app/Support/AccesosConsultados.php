<?php

namespace App\Support;

use App\Models\Imei;
use App\Models\Mandamiento;
use App\Models\Persona;
use App\Models\RegistroAntecedente;
use App\Models\RegistroCriminal;
use App\Models\Telefono;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Resuelve los registros abiertos durante una consulta (`auditar_consultas.ids_accedidos`) a una descripción y un
 * enlace. Reemplaza a `AuditoriaHelper::resolverIdsAccedidos`, que devolvía HTML armado con datos sin escapar; aquí
 * la vista recibe texto y URL por separado.
 */
class AccesosConsultados
{
    /**
     * @param  list<array{id?: int, modulo?: string, fecha_acceso?: string}>|null  $accesos
     * @return list<array{id: int, modelo: string, descripcion: string, url: ?string, fecha_acceso: ?string}>
     */
    public static function resolver(?array $accesos): array
    {
        $resueltos = [];

        foreach ($accesos ?? [] as $acceso) {
            $clase = str_replace('\\\\', '\\', (string) ($acceso['modulo'] ?? ''));
            $id = $acceso['id'] ?? null;

            if ($id === null || ! is_subclass_of($clase, Model::class)) {
                continue;
            }

            try {
                $registro = $clase::find($id);
            } catch (Throwable) {
                $registro = null;
            }

            if (! $registro) {
                continue;
            }

            [$descripcion, $url] = self::describir($registro);

            $resueltos[] = [
                'id' => (int) $id,
                'modelo' => class_basename($clase),
                'descripcion' => $descripcion,
                'url' => $url,
                'fecha_acceso' => $acceso['fecha_acceso'] ?? null,
            ];
        }

        return $resueltos;
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private static function describir(Model $registro): array
    {
        $persona = fn (?Persona $p) => $p ? trim("{$p->nombres} {$p->apellidos}").' - C.I: '.($p->ci ?? '') : 'Persona eliminada';

        return match (true) {
            $registro instanceof RegistroCriminal => [$persona($registro->persona), route('registro-criminal.showByCodigo', md5((string) $registro->id))],
            $registro instanceof RegistroAntecedente => [$persona($registro->persona), route($registro::modulo()->ruta('showByCodigo'), md5((string) $registro->id))],
            $registro instanceof Mandamiento => [$persona($registro->persona), route('mandamientos.showByCodigo', md5((string) $registro->id))],
            $registro instanceof Persona => [trim("{$registro->nombres} {$registro->apellidos}").' - C.I: '.($registro->ci ?? ''), route('personas.show', $registro->getKey())],
            $registro instanceof Telefono => ['Teléfono '.$registro->numero_celular, null],
            $registro instanceof Imei => ['IMEI '.$registro->imei, null],
            $registro instanceof Vehiculo => ['Vehículo placa '.$registro->placa, null],
            default => [class_basename($registro).' #'.$registro->getKey(), null],
        };
    }
}
