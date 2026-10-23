<?php

namespace App\Lib;

/**
 * Helper para resolver información de auditoría
 * Maneja la resolución de modelos y sus descripciones
 */
class AuditoriaHelper
{
    /**
     * Resolver los IDs accedidos para obtener la información del modelo correspondiente
     */
    public static function resolverIdsAccedidos($idsAccedidos)
    {
        if (! $idsAccedidos || empty($idsAccedidos)) {
            return [];
        }

        $resueltos = [];

        foreach ($idsAccedidos as $acceso) {
            $modeloPath = $acceso['modulo'] ?? null;
            $id = $acceso['id'] ?? null;
            $fechaAcceso = $acceso['fecha_acceso'] ?? null;

            if (! $modeloPath || ! $id) {
                continue;
            }

            try {
                // Convertir el namespace del modelo a la clase
                $modelo = self::obtenerInstanciaModelo($modeloPath);

                if ($modelo) {
                    $registro = $modelo::find($id);

                    if ($registro) {
                        $resueltos[] = [
                            'id' => $id,
                            'modelo' => self::extraerNombreModelo($modeloPath),
                            'modelo_completo' => $modeloPath,
                            'fecha_acceso' => $fechaAcceso,
                            'descripcion' => self::obtenerDescripcionRegistro($registro, $modeloPath),
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Si ocurre error al resolver, registrarlo pero continuar
                \Log::warning("Error resolviendo modelo: {$modeloPath}, ID: {$id}", ['error' => $e->getMessage()]);
            }
        }

        return $resueltos;
    }

    /**
     * Obtener instancia del modelo basado en el namespace
     */
    public static function obtenerInstanciaModelo($modeloPath)
    {
        try {
            $class = str_replace('\\\\', '\\', $modeloPath);
            if (class_exists($class)) {
                return new $class;
            }
        } catch (\Exception $e) {
            \Log::warning("Clase no encontrada: {$modeloPath}");
        }

        return null;
    }

    /**
     * Extraer el nombre simple del modelo del namespace
     */
    public static function extraerNombreModelo($modeloPath)
    {
        return class_basename(str_replace('\\\\', '\\', $modeloPath));
        // $partes = explode('\\', str_replace('\\\\', '\\', $modeloPath));
        // return end($partes) ?? 'Desconocido';
    }

    /**
     * Obtener descripción del registro basado en el modelo
     */
    public static function obtenerDescripcionRegistro($registro, $modeloPath)
    {
        $nombreModelo = self::extraerNombreModelo($modeloPath);

        try {
            switch ($nombreModelo) {
                case 'Persona':
                    if (isset($registro->nombres) && isset($registro->apellidos)) {
                        return trim($registro->nombres.' '.$registro->apellidos);
                    } elseif (isset($registro->ci)) {
                        return "C.I: {$registro->ci}";
                    }
                    break;

                case 'RegistroCriminal':

                    $ruta = route('registro-criminal.showByCodigo', md5($registro->id));
                    $persona = "{$registro->persona->nombres} {$registro->persona->apellidos} - C.I: {$registro->persona->ci}";

                    return "<a href='{$ruta}' class='text-decoration-underline' target='_blank'>{$persona}</a>";

                    break;

                case 'Mandamiento':
                    $ruta = route('mandamientos.showByCodigo', md5($registro->id));
                    $persona = "{$registro->persona->nombres} {$registro->persona->apellidos} - C.I: {$registro->persona->ci}";

                    return "<a href='{$ruta}' class='text-decoration-underline' target='_blank'>{$persona}</a>";

                case 'Vehiculo':
                    if (isset($registro->placa)) {
                        return "Placa: {$registro->placa}";
                    } elseif (isset($registro->numero_motor)) {
                        return "Motor: {$registro->numero_motor}";
                    }
                    break;

                case 'Telefono':
                    if (isset($registro->numero)) {
                        return "Teléfono: {$registro->numero}";
                    }
                    break;

                case 'Imei':
                    if (isset($registro->numero_imei)) {
                        return "IMEI: {$registro->numero_imei}";
                    }
                    break;

                case 'Delito':
                    if (isset($registro->nombre)) {
                        return $registro->nombre;
                    } elseif (isset($registro->descripcion)) {
                        return $registro->descripcion;
                    }
                    break;

                case 'Division':
                    if (isset($registro->nombre)) {
                        return $registro->nombre;
                    } elseif (isset($registro->descripcion)) {
                        return $registro->descripcion;
                    }
                    break;

                case 'Juzgado':
                    if (isset($registro->nombre)) {
                        return $registro->nombre;
                    } elseif (isset($registro->codigo)) {
                        return $registro->codigo;
                    }
                    break;

                default:
                    // Intenta obtener un nombre genérico del registro
                    if (isset($registro->nombre)) {
                        return $registro->nombre;
                    } elseif (isset($registro->title)) {
                        return $registro->title;
                    } elseif (isset($registro->descripcion)) {
                        return $registro->descripcion;
                    } elseif (isset($registro->codigo)) {
                        return $registro->codigo;
                    }
                    break;
            }
        } catch (\Exception $e) {
            \Log::warning('Error obteniendo descripción: '.$e->getMessage());

        }

        return "ID: {$registro->id}";
    }

    /**
     * Obtener el nombre mostrable de un modelo
     * Convierte "App\Models\RegistroCriminal" a "Registro Criminal"
     */
    public static function getNombreModeloMostrable($modeloPath)
    {
        $nombreModelo = self::extraerNombreModelo($modeloPath);

        // Agregar espacios antes de mayúsculas (CamelCase a Spaced)
        $nombre = preg_replace('/(?<!^)(?=[A-Z])/', ' ', $nombreModelo);

        return trim($nombre);
    }

    /**
     * Mapeador de rutas para diferentes modelos
     * Retorna la ruta para ver un registro específico
     */
    public static function obtenerRutaModelo($modeloPath, $id)
    {
        $nombreModelo = self::extraerNombreModelo($modeloPath);

        switch ($nombreModelo) {
            case 'Persona':
                return route('personas.show', $id);
            case 'RegistroCriminal':
                return route('registro-criminal.show', $id);
            case 'Mandamiento':
                return route('mandamientos.show', $id);
            case 'Vehiculo':
                return route('vehiculos.show', $id);
            case 'Telefono':
                return route('telefonos.show', $id);
            case 'Imei':
                return route('imeis.show', $id);
            case 'Delito':
                return route('delitos.show', $id);
            case 'Division':
                return route('divisiones.show', $id);
            case 'Juzgado':
                return route('juzgados.show', $id);
            default:
                return null;
        }
    }
}
