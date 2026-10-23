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
        Schema::create('persona', function (Blueprint $table) {
            $table->id();
            $table->string('nombres', 150);
            $table->string('apellidos', 150)->nullable();
            $table->string('ci', 20)->nullable()->unique();
            $table->text('domicilio')->nullable();
            $table->string('telefono', 25)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('lugar_nacimiento', 250)->nullable();
            $table->string('complemento', 40)->nullable();
            $table->enum('genero', ['MASCULINO', 'FEMENINO'])->nullable();
            $table->enum('estado_civil', ['SOLTERO', 'CASADO', 'DIVORCIADO', 'VIUDO', 'CONYUGUE'])->nullable();
            $table->string('nombre_conyuge', 250)->nullable();
            $table->string('ocupacion', 150)->nullable();
            $table->foreignId('id_pais')->nullable()->constrained('pais')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('persona');
    }
};
