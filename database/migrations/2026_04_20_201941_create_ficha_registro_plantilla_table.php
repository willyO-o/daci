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
        Schema::create('ficha_registro_plantilla', function (Blueprint $table) {
            $table->id();
            $table->text('introduccion')->nullable();
            $table->text('requerimiento')->nullable();
            $table->string('persona')->nullable();
            $table->text('resultado_busqueda')->nullable();
            $table->text('nota_certificacion')->nullable();
            $table->text('nota_general')->nullable();
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ficha_registro_plantilla');
    }
};
