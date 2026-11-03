<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo EVIDENCIAS (D-038): objetos secuestrados, recuperados o recolectados en procedimientos policiales.
 *
 *   tipo_articulo        catálogo configurable (el «grupo» decide qué campos pide el formulario)
 *   evidencia            el procedimiento o acta (caso/CUD → `caso`, unidad → `division`, quien registra → `users`)
 *   evidencia_articulo   cada artículo, con su publicación individual para el futuro portal ciudadano
 *   evidencia_archivo    fotografías y documentos (en el disco privado), del registro o de un artículo
 *
 * Sólo CREA tablas nuevas: no modifica ninguna tabla, columna, índice ni dato existente; las claves foráneas viven en
 * las tablas nuevas. Antes de crear nada comprueba (sólo lecturas) que ninguna exista y que las tablas a las que apunta
 * existan y sean InnoDB; si algo no calza, se detiene sin cambios. Si falla a mitad, borra lo creado. `down()` borra
 * sólo estas tablas. Funciona con `--pretend`.
 *
 * La cadena de custodia (`evidencia_movimiento`: artículo, quién entrega, quién recibe, fecha, motivo) queda para una
 * etapa posterior; estas tablas no necesitan cambios para agregarla.
 */
return new class extends Migration
{
    private const TABLAS = ['tipo_articulo', 'evidencia', 'evidencia_articulo', 'evidencia_archivo'];

    /** Tablas existentes a las que apuntan las claves foráneas. */
    private const REFERIDAS = ['users', 'caso', 'division'];

    /**
     * Catálogo inicial (nombre => grupo). Se administra después en «Tipos de Artículo».
     *
     * @var array<string, string>
     */
    private const TIPOS = [
        'TELÉFONO CELULAR' => 'CELULAR',
        'TABLET' => 'CELULAR',
        'LAPTOP' => 'EQUIPO',
        'ELECTRODOMÉSTICO' => 'EQUIPO',
        'HERRAMIENTA' => 'EQUIPO',
        'MATERIAL' => 'MATERIAL',
        'ACCESORIO' => 'OTRO',
        'OTRO' => 'OTRO',
    ];

    public function up(): void
    {
        if (! DB::pretending()) {
            $this->comprobar();
        }

        try {
            Schema::create('tipo_articulo', function (Blueprint $t) {
                $t->id();
                $t->string('nombre', 100)->unique();
                // CELULAR (marca, modelo, color, IMEI, serie) · EQUIPO (marca, modelo, color, serie) ·
                // MATERIAL (cantidad y unidad) · OTRO (descripción, color, cantidad).
                $t->string('grupo', 20);
                $t->string('estado', 20)->default('ACTIVO');
                $t->foreignId('user_id')->nullable()->constrained('users');
                $t->timestamps();
            });

            Schema::create('evidencia', function (Blueprint $t) {
                $t->id();
                // Código interno: lo escribe el usuario (opcional); si se escribe, no se repite.
                $t->string('codigo', 50)->nullable()->unique();
                $t->foreignId('id_caso')->nullable()->constrained('caso');
                $t->foreignId('id_division')->nullable()->constrained('division');
                $t->dateTime('fecha_hora');
                $t->string('lugar', 255);
                $t->text('detalle_lugar')->nullable();
                $t->string('funcionario_responsable', 200);
                $t->string('numero_acta', 100)->nullable();
                $t->text('observaciones')->nullable();
                $t->string('estado', 20)->default('REGISTRADA');
                $t->foreignId('user_id')->constrained('users');
                $t->timestamps();
                $t->softDeletes();
                $t->index('fecha_hora');
                $t->index('estado');
            });

            Schema::create('evidencia_articulo', function (Blueprint $t) {
                $t->id();
                $t->foreignId('evidencia_id')->constrained('evidencia');
                $t->foreignId('tipo_articulo_id')->constrained('tipo_articulo');
                $t->string('descripcion', 500)->nullable();
                $t->string('marca', 100)->nullable();
                $t->string('modelo', 100)->nullable();
                $t->string('color', 50)->nullable();
                // Texto, no número: conservan ceros y no se redondean.
                $t->string('imei_1', 20)->nullable()->index();
                $t->string('imei_2', 20)->nullable()->index();
                $t->string('numero_serie', 100)->nullable()->index();
                $t->decimal('cantidad', 10, 2)->default(1);
                $t->string('unidad_medida', 30)->nullable();
                $t->string('estado_fisico', 20)->nullable();
                $t->string('funcionario_recoge', 200)->nullable();
                $t->date('fecha_recepcion')->nullable();
                $t->text('observaciones')->nullable();
                // Publicación individual: nada se publica solo; la aprueba quien tenga `evidencias.publicar`.
                $t->string('estado_publicacion', 20)->default('NO_PUBLICAR')->index();
                $t->dateTime('publicado_at')->nullable();
                $t->foreignId('publicado_por')->nullable()->constrained('users');
                $t->string('motivo_publicacion', 500)->nullable();
                // Identificador no correlativo para un uso externo (no expone los ids de la base).
                $t->ulid('codigo_publico')->unique();
                $t->timestamps();
                $t->softDeletes();
            });

            Schema::create('evidencia_archivo', function (Blueprint $t) {
                $t->id();
                $t->foreignId('evidencia_id')->constrained('evidencia');
                $t->foreignId('evidencia_articulo_id')->nullable()->constrained('evidencia_articulo');
                // FOTO_ARTICULO · FOTO_IMEI · ACTA · COMPROBANTE · DOCUMENTO
                $t->string('tipo', 20);
                $t->string('nombre_archivo', 255);
                $t->string('ruta', 255);
                $t->string('mime', 100)->nullable();
                $t->unsignedInteger('tamano')->nullable();
                $t->string('descripcion', 255)->nullable();
                // Privado por defecto; sólo una FOTO_ARTICULO puede marcarse pública (lo hace quien publica).
                $t->boolean('es_publico')->default(false);
                $t->foreignId('user_id')->constrained('users');
                $t->timestamps();
                $t->softDeletes();
            });

            if (! DB::pretending()) {
                $ahora = now();
                DB::table('tipo_articulo')->insert(array_map(
                    fn (string $nombre, string $grupo) => ['nombre' => $nombre, 'grupo' => $grupo, 'estado' => 'ACTIVO', 'created_at' => $ahora, 'updated_at' => $ahora],
                    array_keys(self::TIPOS),
                    self::TIPOS,
                ));
            }
        } catch (Throwable $error) {
            $this->down();

            throw $error;
        }
    }

    public function down(): void
    {
        // De hijas a madre, por las claves foráneas.
        foreach (array_reverse(self::TABLAS) as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }

    /**
     * Sólo lecturas. La base de producción es otra y puede diferir: si algo no calza, se detiene sin cambios y dice qué.
     */
    private function comprobar(): void
    {
        $ocupadas = collect(self::TABLAS)->filter(fn (string $tabla) => Schema::hasTable($tabla));
        if ($ocupadas->isNotEmpty()) {
            throw new RuntimeException('Evidencias detenida sin cambios: ya existen las tablas '.$ocupadas->implode(', ').'.');
        }

        $faltan = collect(self::REFERIDAS)->reject(fn (string $tabla) => Schema::hasTable($tabla));
        if ($faltan->isNotEmpty()) {
            throw new RuntimeException('Evidencias detenida sin cambios: faltan las tablas '.$faltan->implode(', ').' (la de casos se crea en 2026_11_01_100000).');
        }

        $noInnodb = DB::table('information_schema.TABLES')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->whereIn('TABLE_NAME', self::REFERIDAS)
            ->where('ENGINE', '<>', 'InnoDB')
            ->pluck('TABLE_NAME');
        if ($noInnodb->isNotEmpty()) {
            throw new RuntimeException('Evidencias detenida sin cambios: no son InnoDB (no admiten clave foránea) '.$noInnodb->implode(', ').'.');
        }
    }
};
