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
        Schema::create('mandamiento', function (Blueprint $table) {
            $table->id();
            $table->string('hoja_ruta', 100)->nullable();
            $table->string('estado', 20);
            $table->date('fecha_ejecucion')->nullable();
            $table->text('detalle_ejecucion')->nullable();
            $table->text('asignado')->nullable();
            $table->string('tipo_documento')->nullable();
            $table->text('actividades_realizadas')->nullable();

            $table->foreignId('id_usuario')->constrained('users')->onDelete('cascade');
            $table->foreignId('id_juzgado')->constrained('juzgado')->onDelete('cascade');
            $table->foreignId('id_delito')->constrained('delito')->onDelete('cascade');
            $table->foreignId('id_tipo_mandamiento')->constrained('tipo_mandamiento')->onDelete('cascade');
            $table->foreignId('id_persona')->constrained('persona')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mandamiento');
    }
};
