<?php

namespace App\Support;

use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as OpcionesCsv;
use OpenSpout\Reader\CSV\Reader as LectorCsv;
use OpenSpout\Reader\XLSX\Reader as LectorXlsx;

/**
 * Lee un CSV o un Excel (XLSX) de importación y devuelve sus filas como arreglos asociativos por cabecera, con las
 * mismas reglas que el sistema anterior:
 *  - la primera fila son las cabeceras, normalizadas (`HOJA DE RUTA O MEMORANDUM` → `HOJA_DE_RUTA_O_MEMORANDUM`,
 *    `MONTO BS.` → `MONTO_BS`, `AÑO` → `ANO`);
 *  - en un CSV se detecta el delimitador (`,`, `;` o tabulador) por la primera línea;
 *  - las filas vacías se saltan y la lectura se detiene tras 20 vacías seguidas;
 *  - las fechas de Excel llegan como texto `Y-m-d H:i:s`.
 * A diferencia del anterior, una fila con menos celdas que cabeceras se completa con vacíos en lugar de fallar.
 */
class LectorTabular
{
    private const FILAS_VACIAS_PARA_CORTAR = 20;

    /**
     * @return list<array{cabeceras: list<string>, filas: list<array<string, mixed>>}> una entrada por hoja
     */
    public static function leer(string $ruta, string $extension, bool $todasLasHojas = false): array
    {
        $esCsv = strtolower($extension) === 'csv';

        if ($esCsv) {
            $opciones = new OpcionesCsv;
            $opciones->FIELD_DELIMITER = self::detectarDelimitador($ruta);
            $lector = new LectorCsv($opciones);
        } else {
            $lector = new LectorXlsx;
        }

        $lector->open($ruta);
        $hojas = [];

        try {
            foreach ($lector->getSheetIterator() as $hoja) {
                $hojas[] = self::leerHoja($hoja->getRowIterator());

                if ($esCsv || ! $todasLasHojas) {
                    break;
                }
            }
        } finally {
            $lector->close();
        }

        return $hojas;
    }

    /**
     * Normalización de cabeceras del sistema anterior (`convertirCabeceras`).
     */
    public static function normalizarCabecera(mixed $celda): string
    {
        return str_replace([' ', '.', 'Ñ'], ['_', '', 'N'], (string) convertirMayusculas(eliminarEspaciosMultiples((string) $celda)));
    }

    /**
     * @param  iterable<int, Row>  $filas
     * @return array{cabeceras: list<string>, filas: list<array<string, mixed>>}
     */
    private static function leerHoja(iterable $filas): array
    {
        $cabeceras = [];
        $datos = [];
        $vaciasSeguidas = 0;

        foreach ($filas as $fila) {
            $celdas = array_map(self::valor(...), $fila->toArray());

            if ($cabeceras === []) {
                $cabeceras = array_map(self::normalizarCabecera(...), $celdas);

                continue;
            }

            $vacia = array_filter($celdas, fn ($celda) => $celda !== null && trim((string) $celda) !== '') === [];

            if ($vacia) {
                if (++$vaciasSeguidas > self::FILAS_VACIAS_PARA_CORTAR) {
                    break;
                }

                continue;
            }

            $vaciasSeguidas = 0;
            $celdas = array_slice(array_pad($celdas, count($cabeceras), null), 0, count($cabeceras));
            $datos[] = array_combine($cabeceras, $celdas);
        }

        return ['cabeceras' => $cabeceras, 'filas' => $datos];
    }

    private static function valor(mixed $celda): mixed
    {
        if ($celda instanceof DateTimeInterface) {
            return $celda->format('Y-m-d H:i:s');
        }

        return is_string($celda) ? trim($celda) : $celda;
    }

    private static function detectarDelimitador(string $ruta): string
    {
        $archivo = fopen($ruta, 'r');
        $primeraLinea = $archivo ? (string) fgets($archivo) : '';

        if ($archivo) {
            fclose($archivo);
        }

        if ($primeraLinea === '') {
            return ',';
        }

        $conteos = [
            ',' => substr_count($primeraLinea, ','),
            ';' => substr_count($primeraLinea, ';'),
            "\t" => substr_count($primeraLinea, "\t"),
        ];

        return (string) array_search(max($conteos), $conteos, true);
    }
}
