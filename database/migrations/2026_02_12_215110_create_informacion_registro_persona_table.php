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

        // Schema::create('alias_registro', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('alias', 100);
        //     $table->foreignId('id_registro_criminal')->constrained('registro_criminal')->onDelete('restrict');
        //     $table->foreignId('id_usuario')->constrained('users')->onDelete('restrict');
        //     $table->timestamps();
        // });


        Schema::create('fotos_registro', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['FRONTAL', 'LATERAL']);
            $table->string('ruta_archivo', 255);
            $table->foreignId('id_registro_criminal')->constrained('registro_criminal')->onDelete('restrict');
            $table->foreignId('id_usuario')->constrained('users')->onDelete('restrict');
            $table->timestamps();
        });


        // Schema::create('observacion', function (Blueprint $table) {
        //     $table->id();
        //     $table->text('observacion');
        //     $table->foreignId('id_registro_criminal')->constrained('registro_criminal')->onDelete('restrict');
        //     $table->foreignId('id_usuario')->constrained('users')->onDelete('restrict');
        //     $table->timestamps();
        // });


        // Schema::create('modus_operandi', function (Blueprint $table) {
        //     $table->id();
        //     $table->text('descripcion');
        //     $table->foreignId('id_registro_criminal')->constrained('registro_criminal')->onDelete('restrict');
        //     $table->foreignId('id_usuario')->constrained('users')->onDelete('restrict');
        //     $table->timestamps();
        // });

        // Schema::create('zonas_operacion', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('zona', 150);
        //     $table->foreignId('id_registro_criminal')->constrained('registro_criminal')->onDelete('restrict');
        //     $table->foreignId('id_usuario')->constrained('users')->onDelete('restrict');
        //     $table->timestamps();
        // });


        Schema::create('documento', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo_documento', ['CI', 'PASAPORTE', 'LICENCIA', 'OTRO']);
            $table->string('numero_documento', 50);
            $table->string('complemento', 10)->nullable();
            $table->string('expedido_en', 50)->nullable();
            $table->foreignId('id_persona')->constrained('persona')->onDelete('restrict');
            $table->unique(['tipo_documento', 'numero_documento']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento');
        // Schema::dropIfExists('zonas_operacion');
        // Schema::dropIfExists('modus_operandi');
        // Schema::dropIfExists('observacion');
        Schema::dropIfExists('fotos_registro');
        // Schema::dropIfExists('alias_registro');
    }
};
