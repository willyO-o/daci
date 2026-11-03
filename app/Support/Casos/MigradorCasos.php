<?php

namespace App\Support\Casos;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Casos, FASE 2 de 4 (D-037): registra cada caso o CUD escrito como texto en la tabla `caso` y guarda su id en
 * `id_caso` de la misma fila. Lógica común de `CasosSeeder` y `php artisan casos:migrar`.
 *
 *  - Por fases: una tabla a la vez, cada una en su propia transacción. Si una falla, se deshace sólo esa (las anteriores
 *    quedan bien) y se puede volver a correr.
 *  - Si el caso ya existe se toma su id; si no, se crea. Texto nulo, vacío, sólo guiones o relleno (`S/N`, `N/A`…):
 *    `id_caso` queda nulo.
 *  - SÓLO escribe `id_caso`, y sólo en filas donde está nulo: nunca pisa uno puesto por un usuario ni por una corrida
 *    anterior. No toca `updated_at`. Incluye las filas eliminadas (soft delete).
 *  - Antes de confirmar cada tabla verifica que las demás columnas no cambiaron (misma huella de todas las filas) y
 *    que cada fila con caso quedó con el id del caso de su mismo texto. Si algo no cuadra, deshace esa tabla.
 *  - Con `$simular`, hace todo y lo deshace al final de cada tabla: sólo informa.
 */
final class MigradorCasos
{
    /** Tabla => columna de texto del caso, en el orden en que se migran. */
    public const FASES = [
        'telefono' => 'caso',
        'registro_criminal' => 'cud',
        'falta_contravencion' => 'cud',
        'vehiculo' => 'caso_relacionado',
        'vehiculo_caso' => 'numero_informacion',
    ];

    /**
     * Relleno que significa «sin caso», comparado sólo por sus letras y números (`S/N`, `s.n.`, `N/A`, `Sin caso`…).
     * Además, los textos que son sólo ceros o sólo equis.
     */
    private const SIN_CASO = [
        'SN', 'SC', 'SD', 'NA', 'NC', 'ND', 'NO', 'NOTIENE', 'NOAPLICA', 'NOHAY', 'NINGUNO', 'NINGUNA', 'NINGUN',
        'SINCASO', 'SINCUD', 'SINDATO', 'SINDATOS', 'SINNUMERO', 'SINNRO', 'SINREGISTRO', 'NULL', 'NULO', 'NONE',
        'CASO', 'CUD',
    ];

    private const LOTE = 1000;

    /** Texto normalizado => id del caso (se vacía en cada tabla: una simulación deshace los casos creados). */
    private array $casos = [];

    private int $casosCreados = 0;

    /**
     * @return array<string, array{columna: string, filas: int, sin_caso: int, relleno: int, ya_tenian: int, asignadas: int, casos_creados: int, no_coinciden: int, omitidos: array<string, int>}>
     */
    public function migrar(bool $simular = false, ?string $soloTabla = null): array
    {
        if ($soloTabla !== null && ! array_key_exists($soloTabla, self::FASES)) {
            throw new RuntimeException("Tabla desconocida: {$soloTabla}. Opciones: ".implode(', ', array_keys(self::FASES)).'.');
        }

        $this->comprobarEsquema();
        $resultado = [];

        foreach (self::FASES as $tabla => $columna) {
            if ($soloTabla === null || $soloTabla === $tabla) {
                $resultado[$tabla] = $this->fase($tabla, $columna, $simular);
            }
        }

        return $resultado;
    }

    /**
     * Texto del caso como se guarda en `caso.codigo`, o null si la fila no tiene caso: nulo, vacío o sólo espacios
     * (también no separables y tabuladores), sólo guiones o signos (`-`, `--`, `—`, `...`), relleno (`S/N`, `N/A`,
     * `SIN CASO`, `0`, `XXX`) o error de Excel (`#¿NOMBRE?`, `#N/A`). Unifica espacios, quita guiones y signos sueltos de
     * los bordes («- caso x -» → «CASO X») y pasa a MAYÚSCULA. Los guiones de dentro se conservan (`2011-0201`).
     */
    public static function normalizar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        // Si el texto no es UTF-8 válido el patrón falla: se trata como relleno (se informa y la fila no se toca).
        $texto = preg_replace('/[\s\p{Z}\x{200B}-\x{200D}\x{FEFF}]+/u', ' ', $valor);

        if ($texto === null) {
            return null;
        }

        $texto = mb_strtoupper(trim($texto), 'UTF-8');

        if (preg_match('/^#(¿?NOMBRE\?|NAME\?|N\/A|VALOR!|VALUE!|REF!|DIV\/0!|NUM!|NULO!|NULL!)$/u', $texto)) {
            return null;
        }

        $texto = trim((string) preg_replace('/^[\s\p{Pd}_.,;:\/\\\\*~=+|]+|[\s\p{Pd}_.,;:\/\\\\*~=+|]+$/u', '', $texto));
        $clave = (string) preg_replace('/[^\p{L}\p{N}]/u', '', $texto);

        if ($clave === '' || in_array($clave, self::SIN_CASO, true) || preg_match('/^(0+|X+)$/u', $clave)) {
            return null;
        }

        return mb_substr($texto, 0, 255);
    }

    private function comprobarEsquema(): void
    {
        $faltan = collect(self::FASES)->keys()
            ->reject(fn (string $tabla) => Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'id_caso'))
            ->map(fn (string $tabla) => "{$tabla}.id_caso");

        if (! Schema::hasTable('caso')) {
            $faltan->prepend('caso');
        }

        if ($faltan->isNotEmpty()) {
            throw new RuntimeException('Faltan '.$faltan->implode(', ').'. Correr antes la fase 1: php artisan migrate --force');
        }
    }

    /**
     * @return array{columna: string, filas: int, sin_caso: int, relleno: int, ya_tenian: int, asignadas: int, casos_creados: int, no_coinciden: int, omitidos: array<string, int>}
     */
    private function fase(string $tabla, string $columna, bool $simular): array
    {
        $this->casos = [];
        $this->casosCreados = 0;
        $resumen = [
            'columna' => $columna, 'filas' => 0, 'sin_caso' => 0, 'relleno' => 0, 'ya_tenian' => 0, 'asignadas' => 0,
            'casos_creados' => 0, 'no_coinciden' => 0, 'omitidos' => [],
        ];
        /** @var array<int, int> $asignadas id de la fila => id del caso puesto en esta corrida */
        $asignadas = [];

        DB::beginTransaction();

        try {
            $huellaAntes = $this->huella($tabla);

            $this->filas($tabla, $columna)->chunkById(self::LOTE, function ($filas) use ($tabla, &$resumen, &$asignadas) {
                $porCaso = [];

                foreach ($filas as $fila) {
                    $resumen['filas']++;

                    if ($fila->id_caso !== null) {
                        $resumen['ya_tenian']++;

                        continue;
                    }

                    $texto = self::normalizar($fila->valor);

                    if ($texto === null) {
                        if (trim((string) $fila->valor) === '') {
                            $resumen['sin_caso']++;
                        } else {
                            $resumen['relleno']++;
                            $valor = trim((string) $fila->valor);
                            $resumen['omitidos'][$valor] = ($resumen['omitidos'][$valor] ?? 0) + 1;
                        }

                        continue;
                    }

                    $porCaso[$this->idDelCaso($texto)][] = (int) $fila->id;
                }

                foreach ($porCaso as $caso => $ids) {
                    // Sólo filas con `id_caso` nulo. Sin `updated_at`: la fila no cambió para el usuario.
                    $cambiadas = DB::table($tabla)->whereIn('id', $ids)->whereNull('id_caso')->update(['id_caso' => $caso]);

                    if ($cambiadas !== count($ids)) {
                        throw new RuntimeException("Casos: {$tabla} cambió durante la corrida. Ejecutarla con el sistema en mantenimiento (php artisan down).");
                    }

                    $resumen['asignadas'] += $cambiadas;
                    $asignadas += array_fill_keys($ids, $caso);
                }
            }, 'id', 'id');

            $resumen['no_coinciden'] = $this->verificar($tabla, $columna, $asignadas);

            if ($this->huella($tabla) !== $huellaAntes) {
                throw new RuntimeException("Casos: deshecho {$tabla}: cambió otra columna además de id_caso. Ejecutarla con el sistema en mantenimiento.");
            }

            $resumen['casos_creados'] = $this->casosCreados;

            $simular ? DB::rollBack() : DB::commit();

            return $resumen;
        } catch (Throwable $error) {
            DB::rollBack();

            throw $error;
        }
    }

    /**
     * Todas las filas (también las eliminadas) con su texto de caso y su `id_caso`.
     */
    private function filas(string $tabla, string $columna)
    {
        return DB::table($tabla)->select(['id', "{$columna} as valor", 'id_caso']);
    }

    private function idDelCaso(string $texto): int
    {
        if (isset($this->casos[$texto])) {
            return $this->casos[$texto];
        }

        // La búsqueda la resuelve la colación de la base (no distingue mayúsculas ni tildes), igual que el índice único.
        $id = DB::table('caso')->where('codigo', $texto)->value('id');

        if ($id === null) {
            $ahora = now();
            $id = DB::table('caso')->insertGetId(['codigo' => $texto, 'estado' => 'ACTIVO', 'created_at' => $ahora, 'updated_at' => $ahora]);
            $this->casosCreados++;
        }

        return $this->casos[$texto] = (int) $id;
    }

    /**
     * Cada fila puesta en esta corrida tiene el caso de su texto, y ninguna fila con caso quedó con `id_caso` nulo.
     * Devuelve cuántas filas tienen un `id_caso` que no es el de su texto (lo cambió un usuario): sólo se informan.
     *
     * @param  array<int, int>  $asignadas
     */
    private function verificar(string $tabla, string $columna, array $asignadas): int
    {
        $noCoinciden = 0;
        $codigos = DB::table('caso')->pluck('id', 'codigo')->mapWithKeys(fn ($id, $codigo) => [mb_strtoupper((string) $codigo) => (int) $id]);

        $this->filas($tabla, $columna)->chunkById(self::LOTE, function ($filas) use ($tabla, $asignadas, $codigos, &$noCoinciden) {
            foreach ($filas as $fila) {
                $texto = self::normalizar($fila->valor);
                $actual = $fila->id_caso === null ? null : (int) $fila->id_caso;

                if (isset($asignadas[$fila->id]) && $actual !== $asignadas[$fila->id]) {
                    throw new RuntimeException("Casos: deshecho {$tabla}: la fila {$fila->id} no quedó con su caso.");
                }

                if ($texto !== null && $actual === null) {
                    throw new RuntimeException("Casos: deshecho {$tabla}: la fila {$fila->id} tiene caso y quedó sin id_caso.");
                }

                if ($actual !== null && ($texto === null || ($this->casos[$texto] ?? $codigos[$texto] ?? null) !== $actual)) {
                    $noCoinciden++;
                }
            }
        }, 'id', 'id');

        return $noCoinciden;
    }

    /**
     * Huella de todas las columnas de la tabla menos `id_caso`: cantidad de filas y dos sumas independientes del MD5 de
     * cada fila. Si cualquier otro dato cambia, la huella cambia.
     *
     * @return array<string, string>
     */
    private function huella(string $tabla): array
    {
        $columnas = collect(Schema::getColumnListing($tabla))
            ->reject(fn (string $c) => $c === 'id_caso')
            ->map(fn (string $c) => "COALESCE(`{$c}`, CHAR(0))")
            ->implode(', ');
        $md5 = "MD5(CONCAT_WS(CHAR(31), {$columnas}))";

        $fila = DB::selectOne(
            "SELECT COUNT(*) AS filas, BIT_XOR(CAST(CONV(SUBSTRING({$md5}, 1, 16), 16, 10) AS UNSIGNED)) AS x, "
            ."SUM(CAST(CONV(SUBSTRING({$md5}, 17, 8), 16, 10) AS UNSIGNED)) AS s FROM `{$tabla}`"
        );

        return ['filas' => (string) $fila->filas, 'x' => (string) $fila->x, 's' => (string) $fila->s];
    }
}
