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
        Schema::table('persona', function (Blueprint $table) {
            $table->text('datos_segip')->nullable();
            $table->string('responsable', 200)->nullable();
            $table->string('estado_investigacion', 200)->nullable();
            $table->text('url_documento')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('persona', function (Blueprint $table) {
            $table->dropColumn('datos_segip');
            $table->dropColumn('responsable');
            $table->dropColumn('estado_investigacion');
            $table->dropColumn('url_documento');
        });
    }
};
