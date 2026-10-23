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
        Schema::create('delito', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_delito');
            $table->enum('estado_delito', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->text('descripcion_delito')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delito');
    }
};
