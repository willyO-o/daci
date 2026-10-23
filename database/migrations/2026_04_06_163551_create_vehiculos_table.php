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
        Schema::create('vehiculo', function (Blueprint $table) {
            $table->id();
            $table->string('placa');
            $table->string('descripcion')->nullable();
            $table->string('responsable')->nullable();
            $table->string('caso_relacionado')->nullable();
            $table->string('bsisa')->nullable();
            $table->string('ci_bsisa')->nullable();
            $table->string('ruat')->nullable();
            $table->string('anh')->nullable();
            $table->string('itb')->nullable();
            $table->string('soat')->nullable();

            $table->timestamps();
            $table->softDeletes();

        });

        Schema::create('vehiculo_caso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculo')->onDelete('restrict');
            $table->foreignId('persona_id')->nullable()->constrained('persona')->onDelete('restrict');
            $table->foreignId('registro_criminal_id')->nullable()->constrained('registro_criminal')->onDelete('restrict');
            $table->string('tipo')->nullable();
            $table->string('caso')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehiculo');
        Schema::dropIfExists('vehiculo_caso');
    }
};
