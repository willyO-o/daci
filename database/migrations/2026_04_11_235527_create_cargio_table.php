<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::table('multimedia', function (Blueprint $table) {
            $table->foreignId('id_vehiculo')->nullable()->constrained('vehiculo')->onDelete('cascade');
        });

        Schema::table('vehiculo', function (Blueprint $table) {

            $table->dropColumn('bsisa');
            $table->dropColumn('ci_bsisa');
            $table->dropColumn('ruat');
            $table->dropColumn('anh');
            $table->dropColumn('itb');
            $table->dropColumn('soat');
        });
        Schema::table('vehiculo_caso', function (Blueprint $table) {

            $table->dropColumn('caso');
            $table->string('numero_informacion', 140)->nullable();
        });

        Schema::table('persona', function (Blueprint $table) {
            $table->string('nit_persona', 140)->nullable();
        });

        Schema::create('estacion_servicio', function (Blueprint $table) {
            $table->id();
            $table->string('eess', 240)->nullable();
            $table->string('nit', 40)->nullable();
            $table->string('telefono', 250)->nullable();
            $table->timestamps();
        });


        Schema::create('cargio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculo')->onDelete('restrict');
            $table->foreignId('estacion_servicio_id')->constrained('estacion_servicio')->onDelete('restrict');
            $table->string('nit_consumidor', 50)->nullable();
            $table->string('razon_social', 240)->nullable();
            $table->string('departamento', 140)->nullable();
            $table->string('producto', 240)->nullable();
            $table->string('factura', 20)->nullable();
            $table->string('nro_autorizacion', 30)->nullable();
            $table->string('codigo_control', 240)->nullable();
            $table->decimal('cantidad', 10, 2)->nullable();
            $table->decimal('monto', 10, 2)->nullable();
            $table->dateTime('fecha_venta')->nullable();
            $table->timestamps();
        });

        Schema::create('inspeccion_tecnica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculo')->onDelete('restrict');
            $table->foreignId('persona_id')->nullable()->constrained('persona')->onDelete('restrict');
            $table->string('dep', 140)->nullable();
            $table->string('resultado', 240)->nullable();
            $table->dateTime('fecha_inspeccion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cargio');
        Schema::dropIfExists('estacion_servicio');
        Schema::dropIfExists('inspeccion_tecnica');
        Schema::table('multimedia', function (Blueprint $table) {
            $table->dropForeign(['id_vehiculo']);
            $table->dropColumn('id_vehiculo');
        });
        Schema::table('vehiculo', function (Blueprint $table) {

            $table->string('bsisa')->nullable();
            $table->string('ci_bsisa')->nullable();
            $table->string('ruat')->nullable();
            $table->string('anh')->nullable();
            $table->string('itb')->nullable();
            $table->string('soat')->nullable();
        });
        Schema::table('vehiculo_caso', function (Blueprint $table) {
            $table->dropColumn('numero_informacion');
            $table->string('caso')->nullable();
        });
        Schema::table('persona', function (Blueprint $table) {
            $table->dropColumn('nit_persona');
        });


    }
};
