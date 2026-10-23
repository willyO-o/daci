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
        Schema::create('auditar_consultas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->string('rol_usuario', 50);
            $table->string('modulo', 100);
            $table->json('criterios_consulta');
            $table->integer('cantidad_resultados');
            $table->json('ids_accedidos')->nullable();
            $table->string('ip_usuario', 100);
            $table->string('user_agent', 255);
            $table->string('identificador',200)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
            $table->index('identificador');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditar_consultas');
    }
};
