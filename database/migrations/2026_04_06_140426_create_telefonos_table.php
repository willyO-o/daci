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
        Schema::create('telefono', function (Blueprint $table) {
            $table->id();
            $table->string('numero_celular');
            $table->string('persona_caso')->nullable();
            $table->string('caso')->nullable();
            $table->string('empresa')->nullable();
            $table->json('imeis_asociados')->nullable();
            $table->string('respuesta_requerimiento')->nullable();
            $table->foreignId('persona_id')->nullable()->constrained('persona')->onDelete('restrict');
            $table->string('informacion')->nullable();
            $table->string('callapp')->nullable();
            $table->string('truecall')->nullable();
            $table->string('uninet')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telefono');
    }
};
