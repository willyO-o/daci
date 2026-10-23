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
        //convertir tipo indice para campo estado para mejor busqueda
        Schema::table('mandamiento', function (Blueprint $table) {
            $table->index('estado');
            $table->string('ejecutado_por', 200)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
