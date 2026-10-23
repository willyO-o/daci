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
        Schema::create('tipo_mandamiento', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_mandamiento', 100);
            $table->enum('estado_tipo_mandamiento', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->text('descripcion_tipo_mandamiento')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_mandamiento');
    }
};
