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
        Schema::create('ficha_registro', function (Blueprint $table) {
            $table->id();
            $table->integer('numero_ficha')->nullable();
            $table->integer('anio_ficha')->nullable();
            $table->string('caso_cud')->nullable();
            $table->text('introduccion')->nullable();
            $table->text('requerimiento')->nullable();
            $table->string('persona')->nullable();
            $table->text('resultado_busqueda')->nullable();
            $table->text('nota_certificacion')->nullable();
            $table->text('nota_general')->nullable();
            $table->string('fecha_literal')->nullable();
            $table->json('otros_datos')->nullable();
            $table->foreignId('registro_criminal_id')->constrained('registro_criminal')->onDelete('restrict');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ficha_registro');
    }
};
