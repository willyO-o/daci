<?php

namespace App\Http\Controllers;

use App\Models\Cargio;
use App\Models\Delito;
use App\Models\EstacionServicio;
use App\Models\Imei;
use App\Models\ImeiTelefono;
use App\Models\InspeccionTecnica;
use App\Models\Juzgado;
use App\Models\Mandamiento;
use App\Models\Persona;
use App\Models\Telefono;
use App\Models\TipoMandamiento;
use App\Models\Vehiculo;
use App\Models\VehiculoCaso;
use App\Support\LectorTabular;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Importaciones masivas desde CSV / Excel. Mismas columnas, reglas y respuestas que el controlador `Importacion`
 * del sistema anterior; la lectura del archivo la hace {@see LectorTabular}.
 */
class ImportacionController extends Controller
{
    // ---------------------------------------------------------------- Pantallas

    public function indexPersonas(): Response
    {
        return Inertia::render('Importar/Personas');
    }

    public function indexMandamientos(): Response
    {
        return Inertia::render('Importar/Mandamientos', ['total' => Mandamiento::count()]);
    }

    public function indexTelefonos(): Response
    {
        return Inertia::render('Importar/Telefonos', ['totalTelefonos' => Telefono::count(), 'totalImeis' => Imei::count()]);
    }

    public function indexVehiculos(): Response
    {
        return Inertia::render('Importar/Vehiculos', [
            'totalVehiculos' => Vehiculo::count(),
            'totalCargios' => Cargio::count(),
            'totalInspecciones' => InspeccionTecnica::count(),
        ]);
    }

    // ---------------------------------------------------------------- Personas

    /**
     * Plantilla CSV de personas (con dos filas de ejemplo, separador `;`).
     */
    public function plantillaPersonas(): StreamedResponse
    {
        $ejemplo = [
            ['N REGISTRO', 'NOMBRE', 'CI', 'DATOS_SEGIP', 'responsable', 'ESTADO'],
            [1, 'JUAN PEREZ GARCIA', '1234567', "Nombres y Apellidos: JUAN PEREZ GARCIA\nFecha de Nacimiento: 15/01/1990\nDomicilio: Calle Principal 123\nGénero: MASCULINO\nEstado Civil: SOLTERO\nProfesión: Ingeniero\nPaís: BOLIVIA", 'JHONY', 'En investigación'],
            [2, 'MARIA LOPEZ QUISPE', '7654321', "Nombres y Apellidos: MARIA LOPEZ QUISPE\nFecha de Nacimiento: 22/03/1985\nDomicilio: Calle Secundaria 456\nGénero: FEMENINO\nEstado Civil: CASADA\nProfesión: Abogada\nPaís: BOLIVIA", 'ENZO', 'Pendiente'],
        ];

        return response()->streamDownload(function () use ($ejemplo) {
            $salida = fopen('php://output', 'w');
            foreach ($ejemplo as $fila) {
                fputcsv($salida, $fila, ';');
            }
            fclose($salida);
        }, 'plantilla_personas_'.date('Y-m-d_His').'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    public function storePersonas(Request $request): JsonResponse
    {
        $archivo = $this->validarArchivo($request, ['csv', 'xlsx', 'xls'], 'CSV o Excel');
        $filas = $this->primeraHoja($archivo);

        if ($filas === []) {
            return response()->json(['error' => 'El archivo está vacío o no contiene registros válidos'], 400);
        }

        $importadas = 0;
        $errores = [];

        DB::transaction(function () use ($filas, &$importadas, &$errores) {
            foreach ($filas as $indice => $fila) {
                try {
                    $partes = $this->separarNombreApellidos($fila['NOMBRE'] ?? '');
                    $datos = array_filter([
                        'nombres' => $partes['nombres'],
                        'apellidos' => $partes['apellidos'],
                        'ci' => filled($fila['CI'] ?? null) ? preg_replace('/[^0-9]/', '', trim((string) $fila['CI'])) : null,
                        'responsable' => $fila['RESPONSABLE'] ?? null,
                        'datos_segip' => $fila['DATOS_SEGIP'] ?? null,
                        'estado_investigacion' => $fila['ESTADO'] ?? null,
                    ], fn ($valor) => $valor !== null && $valor !== '');

                    // Una persona con el mismo CI se actualiza en lugar de duplicarse.
                    $existente = filled($datos['ci'] ?? null) ? Persona::where('ci', $datos['ci'])->first() : null;
                    $existente ? $existente->update($datos) : Persona::create($datos);
                    $importadas++;
                } catch (Throwable $e) {
                    $errores[] = ['fila' => $indice + 2, 'nombre' => $fila['NOMBRE'] ?? 'Sin nombre', 'error' => $e->getMessage()];
                }
            }
        });

        $mensaje = "Se importaron {$importadas} registros correctamente.".($errores ? ' '.count($errores).' registros tuvieron errores.' : '');

        return response()->json(['success' => $mensaje, 'importadas' => $importadas, 'errores' => $errores, 'total' => count($filas)]);
    }

    // ---------------------------------------------------------------- Mandamientos

    public function storeMandamientos(Request $request): JsonResponse
    {
        $filas = $this->primeraHoja($this->validarArchivo($request, ['csv'], 'CSV'));

        try {
            $contador = DB::transaction(function () use ($filas) {
                $contador = 0;

                foreach ($filas as $fila) {
                    $nombre = $this->separarNombreApellidos($fila['NOMBRE'] ?? '', true);

                    Mandamiento::create([
                        'hoja_ruta' => campoDB($fila['HOJA_DE_RUTA_O_MEMORANDUM'] ?? null),
                        'id_persona' => Persona::idPersonaDatos(['nombres' => $nombre['nombres'], 'apellidos' => $nombre['apellidos'], 'ci' => campoDB($fila['CI'] ?? null)]),
                        'id_tipo_mandamiento' => TipoMandamiento::idtipoMandamientoNombre(campoDB($fila['TIPO_DE_MANDAMIENTO'] ?? null)),
                        'tipo_documento' => campoDB($fila['ORIGINAL_O_FOTOCOPIA'] ?? null),
                        'id_delito' => Delito::idDelitoNombre(campoDB($fila['DELITO'] ?? null)),
                        'id_juzgado' => Juzgado::idJuzgadoNombre(campoDB($fila['JUZGADO'] ?? null)),
                        'estado' => campoDB($fila['ESTADO'] ?? null),
                        'domicilio' => $fila['DOMICILIO'] ?? null,
                        'vehiculos' => $fila['VEHICULOS'] ?? null,
                        'telefono' => $fila['TELEFONO'] ?? null,
                        'asignado' => campoDB($fila['ASIGNADO'] ?? null),
                        'actividades_realizadas' => $fila['ACTIVIDADES_REALIZADAS'] ?? null,
                        'detalle_ejecucion' => nuloSiVacio((string) ($fila['DETALLE_EJECUCION'] ?? '')),
                    ]);
                    $contador++;
                }

                return $contador;
            });
        } catch (Throwable $e) {
            $mensaje = 'No se pudieron importar los mandamientos. Error: '.$e->getMessage();

            return response()->json(['message' => $mensaje, 'error' => $mensaje], 500);
        }

        return response()->json([
            'success' => "Migración de mandamientos completada, Se importaron {$contador} mandamientos",
            'data' => $contador,
            'total' => count($filas),
            'importadas' => $contador,
            'errores' => [],
        ]);
    }

    // ---------------------------------------------------------------- Teléfonos e IMEIs

    public function storeTelefonos(Request $request): JsonResponse
    {
        $hoja = $this->hojas($this->validarArchivo($request, ['csv'], 'CSV'))[0] ?? ['cabeceras' => [], 'filas' => []];

        if (! in_array('NUMERO_DE_CELULAR', $hoja['cabeceras'], true)) {
            return $this->faltaColumna('NUMERO_DE_CELULAR', ['NUMERO_DE_CELULAR', 'PERSONA_DEL_CASO', 'CASO', 'EMPRESA', 'RESP_A_REQ', 'CI', 'INFO', 'CALLAPP', 'TRUECALL', 'UNINET'], $hoja['cabeceras']);
        }

        return $this->ejecutar('teléfonos', count($hoja['filas']), function () use ($hoja) {
            $contador = 0;

            foreach ($hoja['filas'] as $fila) {
                $numero = campoDB($fila['NUMERO_DE_CELULAR'] ?? null);

                // Los registros vacíos se ignoran (como dice la guía de la pantalla).
                if (blank($numero)) {
                    continue;
                }

                $nombre = $this->separarNombreApellidos($fila['RESP_A_REQ'] ?? '', true);

                Telefono::create([
                    'numero_celular' => $numero,
                    'persona_caso' => campoDB($fila['PERSONA_DEL_CASO'] ?? null),
                    'caso' => campoDB($fila['CASO'] ?? null),
                    'empresa' => campoDB($fila['EMPRESA'] ?? null),
                    'respuesta_requerimiento' => campoDB($fila['RESP_A_REQ'] ?? null),
                    'persona_id' => Persona::idPersonaDatos(['nombres' => $nombre['nombres'], 'apellidos' => $nombre['apellidos'], 'ci' => limpiarCI($fila['CI'] ?? null)]),
                    'informacion' => campoDB($fila['INFO'] ?? null),
                    'callapp' => $fila['CALLAPP'] ?? null,
                    'truecall' => $fila['TRUECALL'] ?? null,
                    'uninet' => $fila['UNINET'] ?? null,
                ]);
                $contador++;
            }

            return $contador;
        });
    }

    public function storeImeis(Request $request): JsonResponse
    {
        $hoja = $this->hojas($this->validarArchivo($request, ['csv'], 'CSV'))[0] ?? ['cabeceras' => [], 'filas' => []];

        foreach (['NUMERO_ASOCIADO', 'NUMERO_IMEI'] as $columna) {
            if (! in_array($columna, $hoja['cabeceras'], true)) {
                return $this->faltaColumna($columna, ['NUMERO_ASOCIADO', 'NUMERO_IMEI'], $hoja['cabeceras']);
            }
        }

        return $this->ejecutar('IMEIs', count($hoja['filas']), function () use ($hoja) {
            $contador = 0;

            foreach ($hoja['filas'] as $fila) {
                $imei = campoDB($fila['NUMERO_IMEI'] ?? null);
                $numero = campoDB($fila['NUMERO_ASOCIADO'] ?? null);

                if (blank($imei) || blank($numero)) {
                    continue;
                }

                ImeiTelefono::firstOrCreate([
                    'telefono_id' => Telefono::firstOrCreate(['numero_celular' => $numero])->id,
                    'imei_id' => Imei::firstOrCreate(['imei' => $imei])->id,
                ]);
                $contador++;
            }

            return $contador;
        });
    }

    // ---------------------------------------------------------------- Vehículos, carguíos e ITV

    public function storeVehiculos(Request $request): JsonResponse
    {
        $hoja = $this->hojas($this->validarArchivo($request, ['csv'], 'CSV'))[0] ?? ['cabeceras' => [], 'filas' => []];

        if (! in_array('PLACA', $hoja['cabeceras'], true)) {
            return $this->faltaColumna('PLACA', ['PLACA', 'CARACTERISTICAS', 'RESPONSABLE', 'CASO_RELACIONADO', 'RUAT', 'CI_RUAT', 'BSISA', 'CI_BSISA', 'SOAT', 'CI_SOAT'], $hoja['cabeceras']);
        }

        return $this->ejecutar('vehículos', count($hoja['filas']), function () use ($hoja) {
            $contador = 0;

            foreach ($hoja['filas'] as $fila) {
                $placa = limpiarCI($fila['PLACA'] ?? null);

                if (blank($placa)) {
                    continue;
                }

                $vehiculo = Vehiculo::firstOrCreate(['placa' => $placa], [
                    'placa' => $placa,
                    'descripcion' => campoDB($fila['CARACTERISTICAS'] ?? null),
                    'responsable' => campoDB($fila['RESPONSABLE'] ?? null),
                    'caso_relacionado' => campoDB($fila['CASO_RELACIONADO'] ?? null),
                ]);

                foreach (['RUAT', 'BSISA', 'SOAT'] as $tipo) {
                    if (filled($fila[$tipo] ?? null) || filled($fila["CI_{$tipo}"] ?? null)) {
                        $nombre = $this->separarNombreApellidos($fila[$tipo] ?? '', true);
                        VehiculoCaso::firstOrCreate([
                            'vehiculo_id' => $vehiculo->id,
                            'persona_id' => Persona::idPersonaDatos(['nombres' => $nombre['nombres'], 'apellidos' => $nombre['apellidos'], 'ci' => limpiarCI($fila["CI_{$tipo}"] ?? null)]),
                            'tipo' => $tipo,
                        ]);
                    }
                }

                $contador++;
            }

            return $contador;
        });
    }

    /**
     * Carguíos de combustible (CSV o Excel; en Excel se leen todas las hojas). Un carguío idéntico no se repite.
     */
    public function storeCargios(Request $request): JsonResponse
    {
        $archivo = $this->validarArchivo($request, ['csv', 'xlsx'], 'CSV, XLSX');
        $hojas = $this->hojas($archivo, true);

        foreach ($hojas as $hoja) {
            foreach (['EESS', 'PLACA', 'FECHA_VENTA'] as $columna) {
                if ($hoja['filas'] !== [] && ! in_array($columna, $hoja['cabeceras'], true)) {
                    return $this->faltaColumna($columna, ['EESS', 'NIT_ESTACION', 'DEPARTAMENTO', 'PRODUCTO', 'RAZON_SOCIAL', 'NIT_CONSUMIDOR', 'FACTURA', 'NRO_AUTORIZACION', 'CODIGO_CONTROL', 'CANTIDAD', 'MONTO_BS', 'FECHA_VENTA', 'PLACA'], $hoja['cabeceras']);
                }
            }
        }

        $filas = array_merge(...array_column($hojas, 'filas'));

        return $this->ejecutar('carguíos', count($filas), function () use ($filas) {
            $contador = 0;

            foreach ($filas as $fila) {
                $placa = limpiarCI($fila['PLACA'] ?? null);

                if (blank($placa)) {
                    continue;
                }

                $datos = [
                    'vehiculo_id' => Vehiculo::firstOrCreate(['placa' => $placa], ['placa' => $placa])->id,
                    'estacion_servicio_id' => EstacionServicio::idEstacionDatos(limpiarCI($fila['NIT_ESTACION'] ?? null), campoDB($fila['EESS'] ?? null)),
                    'nit_consumidor' => limpiarCI($fila['NIT_CONSUMIDOR'] ?? null),
                    'razon_social' => campoDB($fila['RAZON_SOCIAL'] ?? null),
                    'departamento' => campoDB($fila['DEPARTAMENTO'] ?? null),
                    'producto' => campoDB($fila['PRODUCTO'] ?? null),
                    'factura' => campoDB($fila['FACTURA'] ?? null),
                    'nro_autorizacion' => campoDB($fila['NRO_AUTORIZACION'] ?? null),
                    'codigo_control' => campoDB($fila['CODIGO_CONTROL'] ?? null),
                    'cantidad' => campoDBNumero($fila['CANTIDAD'] ?? null),
                    'monto' => campoDBNumero($fila['MONTO_BS'] ?? null),
                    'fecha_venta' => campoDBFechaHora($fila['FECHA_VENTA'] ?? null),
                ];

                $existe = Cargio::query()
                    ->where('vehiculo_id', $datos['vehiculo_id'])
                    ->where('estacion_servicio_id', $datos['estacion_servicio_id'])
                    ->where('producto', $datos['producto'])
                    ->where('nit_consumidor', $datos['nit_consumidor'])
                    ->where('factura', $datos['factura'])
                    ->where('fecha_venta', $datos['fecha_venta'])
                    ->exists();

                if (! $existe) {
                    Cargio::create($datos);
                    $contador++;
                }
            }

            return $contador;
        });
    }

    /**
     * Inspecciones técnicas (ITV). Una inspección del mismo vehículo y año no se repite.
     */
    public function storeInspecciones(Request $request): JsonResponse
    {
        $archivo = $this->validarArchivo($request, ['csv', 'xlsx'], 'CSV, XLSX');
        $hojas = $this->hojas($archivo, true);

        foreach ($hojas as $hoja) {
            foreach (['ANO', 'PLACA'] as $columna) {
                if ($hoja['filas'] !== [] && ! in_array($columna, $hoja['cabeceras'], true)) {
                    return $this->faltaColumna($columna, ['ANO', 'NOMBRE', 'CI', 'DETALLE', 'PLACA'], $hoja['cabeceras'], 'PLACA y AÑO');
                }
            }
        }

        $filas = array_merge(...array_column($hojas, 'filas'));

        return $this->ejecutar('inspecciones', count($filas), function () use ($filas) {
            $contador = 0;

            foreach ($filas as $fila) {
                $placa = limpiarCI($fila['PLACA'] ?? null);

                if (blank($placa)) {
                    continue;
                }

                $vehiculo = Vehiculo::firstOrCreate(['placa' => $placa], ['placa' => $placa]);
                $anio = campoDB($fila['ANO'] ?? null);

                if (InspeccionTecnica::where('vehiculo_id', $vehiculo->id)->where('anio', $anio)->exists()) {
                    continue;
                }

                $personaId = null;
                if (filled($fila['CI'] ?? null) || filled($fila['NOMBRE'] ?? null)) {
                    $nombre = $this->separarNombreApellidos($fila['NOMBRE'] ?? '', true);
                    $personaId = Persona::idPersonaDatos(['nombres' => $nombre['nombres'], 'apellidos' => $nombre['apellidos'], 'ci' => limpiarCI($fila['CI'] ?? null)]);
                }

                InspeccionTecnica::create([
                    'vehiculo_id' => $vehiculo->id,
                    'persona_id' => $personaId,
                    'resultado' => nuloSiVacio((string) ($fila['DETALLE'] ?? '')),
                    'anio' => $anio,
                ]);
                $contador++;
            }

            return $contador;
        });
    }

    // ---------------------------------------------------------------- Apoyo

    /**
     * @param  list<string>  $extensiones
     */
    private function validarArchivo(Request $request, array $extensiones, string $formatos): UploadedFile
    {
        $request->validate([
            'archivo' => ['required', 'file', 'extensions:'.implode(',', $extensiones), 'max:10240'],
        ], [
            'archivo.required' => 'El archivo es obligatorio',
            'archivo.extensions' => "El archivo debe ser {$formatos}",
            'archivo.max' => 'El archivo no debe exceder 10MB',
        ]);

        return $request->file('archivo');
    }

    /**
     * @return list<array{cabeceras: list<string>, filas: list<array<string, mixed>>}>
     */
    private function hojas(UploadedFile $archivo, bool $todasLasHojas = false): array
    {
        return LectorTabular::leer($archivo->getRealPath(), $archivo->getClientOriginalExtension(), $todasLasHojas);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function primeraHoja(UploadedFile $archivo): array
    {
        return $this->hojas($archivo)[0]['filas'] ?? [];
    }

    /**
     * Ejecuta una importación «todo o nada» y responde con el contrato del sistema anterior.
     */
    private function ejecutar(string $entidad, int $total, callable $importar): JsonResponse
    {
        try {
            $importadas = DB::transaction($importar);
        } catch (Throwable $e) {
            return response()->json(['error' => "No se pudieron importar los {$entidad}. Error: {$e->getMessage()}"], 500);
        }

        return response()->json([
            'success' => "Migración de {$entidad} completada, Se importaron {$importadas} {$entidad}",
            'total' => $total,
            'importadas' => $importadas,
            'errores' => [],
        ]);
    }

    /**
     * @param  list<string>  $requeridas
     * @param  list<string>  $cabeceras
     */
    private function faltaColumna(string $columna, array $requeridas, array $cabeceras, ?string $texto = null): JsonResponse
    {
        $faltantes = implode(', ', array_diff($requeridas, $cabeceras));
        $mensaje = 'El archivo debe contener al menos la columna '.($texto ?? $columna).". Columnas faltantes: {$faltantes}";

        return response()->json(['errors' => ['archivo' => [$mensaje]], 'message' => $mensaje], 422);
    }

    /**
     * Separa «NOMBRES APELLIDO APELLIDO»: con más de dos palabras, las dos últimas son apellidos.
     *
     * @return array{nombres: string, apellidos: string}
     */
    private function separarNombreApellidos(mixed $nombreCompleto, bool $alterno = false): array
    {
        if (blank($nombreCompleto) && ! $alterno) {
            return ['nombres' => 'Sin', 'apellidos' => 'Nombre'];
        }

        $partes = explode(' ', (string) convertirMayusculas(eliminarEspaciosMultiples((string) $nombreCompleto)));

        [$nombres, $apellidos] = count($partes) > 2
            ? [array_slice($partes, 0, -2), array_slice($partes, -2)]
            : [array_slice($partes, 0, 1), array_slice($partes, 1)];

        return ['nombres' => trim(implode(' ', $nombres)), 'apellidos' => trim(implode(' ', $apellidos))];
    }
}
