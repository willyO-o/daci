<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo «Faltas y Contravenciones»: SUS PROPIAS tablas, copia de las de Registro Criminal con los mismos campos y
 * tipos — `falta_contravencion` (← `registro_criminal`), `foto_falta_contravencion` (← `fotos_registro`),
 * `ficha_falta_contravencion` (← `ficha_registro`) y `ficha_falta_contravencion_plantilla` (← `ficha_registro_plantilla`).
 *
 * Sólo CREA tablas nuevas. No modifica ninguna tabla, columna, índice ni dato existente: las claves foráneas viven en
 * las tablas nuevas y apuntan a `persona`, `division` y `users` sin alterarlas. `down()` borra únicamente estas
 * tablas. Si alguna ya existe, se detiene sin cambios; si falla a mitad, borra lo que alcanzó a crear.
 */
return new class extends Migration
{
    /**
     * Tabla principal del módulo y su nombre en la plantilla de la ficha.
     *
     * @var array<string, string>
     */
    private const MODULOS = [
        'falta_contravencion' => 'REGISTRO DE FALTAS Y CONTRAVENCIONES',
    ];

    public function up(): void
    {
        $ocupadas = collect($this->tablas())->filter(fn (string $tabla) => Schema::hasTable($tabla));

        if ($ocupadas->isNotEmpty()) {
            throw new RuntimeException('Migración de faltas y contravenciones detenida sin cambios: ya existen las tablas '.$ocupadas->implode(', ').'.');
        }

        try {
            foreach (self::MODULOS as $tabla => $nombreRegistro) {
                $this->crearModulo($tabla);
                $this->sembrarPlantilla($tabla, $nombreRegistro);
            }
        } catch (Throwable $error) {
            $this->down();

            throw $error;
        }
    }

    public function down(): void
    {
        // De hijas a madre, por las claves foráneas.
        foreach (array_reverse($this->tablas()) as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }

    /**
     * @return list<string>
     */
    private function tablas(): array
    {
        $tablas = [];

        foreach (array_keys(self::MODULOS) as $tabla) {
            array_push($tablas, $tabla, "foto_{$tabla}", "ficha_{$tabla}", "ficha_{$tabla}_plantilla");
        }

        return $tablas;
    }

    private function crearModulo(string $tabla): void
    {
        // Mismas columnas, tipos y largos que `registro_criminal`.
        Schema::create($tabla, function (Blueprint $t) {
            $t->id();
            $t->integer('nro_registro')->nullable();
            $t->date('fecha_registro');
            $t->string('nombre_supuesto', 250)->nullable();
            $t->string('alias', 20)->nullable();
            $t->string('especialidad', 250)->nullable();
            $t->string('edad_aproximada', 20)->nullable();
            $t->string('nombre_conyuge', 250)->nullable();
            $t->string('domicilio', 250)->nullable();
            $t->text('rasgos')->nullable();
            $t->text('modus_operandi')->nullable();
            $t->text('zonas_opera')->nullable();
            $t->text('observaciones')->nullable();
            $t->foreignId('id_persona')->constrained('persona');
            $t->foreignId('id_usuario')->constrained('users');
            $t->foreignId('id_division')->constrained('division');
            $t->timestamps();
            $t->softDeletes();
            $t->string('telefono')->nullable();
            $t->string('estatura')->nullable();
            $t->string('peso')->nullable();
            $t->string('cud')->nullable();
            $t->text('caracteristicas_particulares')->nullable();
            $t->text('hijos')->nullable();
        });

        // Como `fotos_registro` (FRONTAL / LATERAL); el tipo es texto validado en la app, no `enum` de MySQL.
        Schema::create("foto_{$tabla}", function (Blueprint $t) use ($tabla) {
            $t->id();
            $t->string('tipo', 20);
            $t->string('ruta_archivo');
            $t->foreignId("id_{$tabla}")->constrained($tabla);
            $t->foreignId('id_usuario')->constrained('users');
            $t->timestamps();
        });

        // Como `ficha_registro`.
        Schema::create("ficha_{$tabla}", function (Blueprint $t) use ($tabla) {
            $t->id();
            $t->integer('numero_ficha')->nullable();
            $t->integer('anio_ficha')->nullable();
            $t->string('caso_cud')->nullable();
            $t->text('introduccion')->nullable();
            $t->text('requerimiento')->nullable();
            $t->string('persona')->nullable();
            $t->text('resultado_busqueda')->nullable();
            $t->text('nota_certificacion')->nullable();
            $t->text('nota_general')->nullable();
            $t->string('fecha_literal')->nullable();
            $t->json('otros_datos')->nullable();
            $t->foreignId("{$tabla}_id")->constrained($tabla);
            $t->foreignId('user_id')->constrained('users');
            $t->timestamps();
            $t->softDeletes();
        });

        // Como `ficha_registro_plantilla`.
        Schema::create("ficha_{$tabla}_plantilla", function (Blueprint $t) {
            $t->id();
            $t->text('introduccion')->nullable();
            $t->text('requerimiento')->nullable();
            $t->string('persona')->nullable();
            $t->text('resultado_busqueda')->nullable();
            $t->text('nota_certificacion')->nullable();
            $t->text('nota_general')->nullable();
            $t->string('estado', 20)->default('ACTIVO');
            $t->timestamps();
        });
    }

    /**
     * La plantilla inicial es la activa de Registro Criminal (sólo se LEE), con el nombre del registro cambiado. Si la
     * base no tiene plantilla de Registro Criminal, se usa el texto de `PlantillaFichaSeeder`.
     */
    private function sembrarPlantilla(string $tabla, string $nombreRegistro): void
    {
        $origen = Schema::hasTable('ficha_registro_plantilla')
            ? DB::table('ficha_registro_plantilla')->where('estado', 'ACTIVO')->orderBy('id')->first()
            : null;

        $campos = $origen ? (array) $origen : [
            'introduccion' => 'En la ciudad de La Paz, del día {fecha_hora_actual}, dando cumplimiento a requerimiento fiscal emitido por .......................✏️, Director funcional de las Investigaciones en función a las investigaciones conferidas.',
            'requerimiento' => "\t\t\t QUE, POR LA UNIDAD Y/O SECCIÓN QUE CORRESPONDA, REMITA INFORME Y/O CERTIFICACIÓN EN RELACIÓN AL REGISTRO CRIMINAL Y/O FICHA DE REGISTRO DE:",
            'persona' => '{nombre_persona} con C.I. {ci_persona}',
            'resultado_busqueda' => 'REVISADA LA BASE DE DATOS DEL REGISTRO FOTOSTÁTICO SOMÁTICO COMPUTARIZADO DEL DEPARTAMENTO DE ANÁLISIS CRIMINAL E INTELIGENCIA "D.A.C.I." DE LA F.E.L.C.C. DE LA PAZ SE INFORMA QUE SE ENCUENTRAN REGISTRADO EN CALIDAD DE APREHENDIDO POR LA DIVISIÓN {division} DE LA FELCC - LA PAZ, EN FECHA {fecha_aprehension}, POR EL PRESUNTO DELITO DE {delito}.',
            'nota_certificacion' => 'Sin enbargo el peticionante debe recurrir a la Dirección Nacional de  Servicios Técnicos auxiliares de la Policía Boliviana para su verificación y Certificación de Antecedentes Policiales. Se expide la presente ficha de Información, para seguir con las investigaciones.',
            'nota_general' => ' NOTA. - Se hace conocer que únicamente se tiene el Registro Forostatico Somático de la Base de Datos desde el año 2005, bajo en la jurisdicción del Municipio de La Paz y no así a nivel nacional.',
        ];

        $ahora = now();

        DB::table("ficha_{$tabla}_plantilla")->insert([
            'introduccion' => $campos['introduccion'] ?? null,
            'requerimiento' => isset($campos['requerimiento']) ? str_replace('REGISTRO CRIMINAL', $nombreRegistro, $campos['requerimiento']) : null,
            'persona' => $campos['persona'] ?? null,
            'resultado_busqueda' => $campos['resultado_busqueda'] ?? null,
            'nota_certificacion' => $campos['nota_certificacion'] ?? null,
            'nota_general' => $campos['nota_general'] ?? null,
            'estado' => 'ACTIVO',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }
};
