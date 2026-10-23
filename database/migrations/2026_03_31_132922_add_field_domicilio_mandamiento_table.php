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
        Schema::table('mandamiento', function (Blueprint $table) {
            $table->text('domicilio')->nullable()->after('id_persona');
            $table->text('vehiculos')->nullable()->after('domicilio');
            $table->string('telefono', 150)->nullable()->after('vehiculos');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('mandamiento', function (Blueprint $table) {
            $table->dropColumn(['domicilio', 'vehiculos', 'telefono']);
        });
    }
};
