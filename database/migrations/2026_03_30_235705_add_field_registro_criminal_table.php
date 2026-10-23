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
        //
        Schema::table('registro_criminal', function (Blueprint $table) {
            $table->string('telefono')->nullable();
            $table->string('estatura')->nullable();
            $table->string('peso')->nullable();
            $table->string('cud')->nullable();
            $table->text('caracteristicas_particulares')->nullable();
            $table->text('hijos')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('registro_criminal', function (Blueprint $table) {
            $table->dropColumn('telefono');
            $table->dropColumn('estatura');
            $table->dropColumn('peso');
            $table->dropColumn('cud');
            $table->dropColumn('caracteristicas_particulares');
            $table->dropColumn('hijos');
        });
    }
};
