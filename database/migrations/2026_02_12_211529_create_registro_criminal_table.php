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

        Schema::create('registro_criminal', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_registro');
            $table->string('nombre_supuesto', 250)->nullable();
            $table->string('alias', 20)->nullable();
            $table->string('especialidad', 250)->nullable();
            $table->string('edad_aproximada', 20)->nullable();
            $table->string('nombre_conyuge', 250)->nullable();
            $table->string('domicilio', 250)->nullable();
            $table->text('rasgos')->nullable();
            $table->text('modus_operandi')->nullable();
            $table->text('zonas_opera')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('id_persona')->constrained('persona')->onDelete('restrict');
            $table->foreignId('id_usuario')->constrained('users')->onDelete('restrict');
            $table->foreignId('id_division')->constrained('division')->onDelete('restrict');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registro_criminal');
    }
};
