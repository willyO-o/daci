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
        Schema::create('multimedia', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 50);
            $table->string('ruta', 255);
            $table->string('nombre_archivo', 255);
            $table->foreignId('id_mandamiento')->nullable()->constrained('mandamiento')->onDelete('cascade');
            $table->foreignId('id_persona')->nullable()->constrained('persona')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('multimedia');
    }
};
