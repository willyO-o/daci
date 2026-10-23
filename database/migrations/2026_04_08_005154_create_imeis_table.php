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
        Schema::create('imei', function (Blueprint $table) {
            $table->id();
            $table->string('imei')->unique();
            $table->text('caracteristicas')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        //eliminar la columna imeis_asociados de la tabla telefono
        Schema::table('telefono', function (Blueprint $table) {
            $table->dropColumn('imeis_asociados');
        });

        Schema::create('imei_telefono', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('imei_id');
            $table->unsignedBigInteger('telefono_id');
            $table->timestamps();

            $table->foreign('imei_id')->references('id')->on('imei')->onDelete('cascade');
            $table->foreign('telefono_id')->references('id')->on('telefono')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imei');
        Schema::dropIfExists('imei_telefono');
        Schema::table('telefono', function (Blueprint $table) {
            $table->json('imeis_asociados')->nullable();
        });
    }
};
