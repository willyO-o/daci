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
            $table->unsignedBigInteger('id_delito')->nullable()->change();
            $table->unsignedBigInteger('id_juzgado')->nullable()->change();
            $table->unsignedBigInteger('id_tipo_mandamiento')->nullable()->change();
            $table->string('estado', 200)->nullable()->change();


        });

        Schema::table('persona', function (Blueprint $table) {
             $table->string('ci', 250)->nullable()->change();

        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        // Schema::table('mandamiento', function (Blueprint $table) {
        //     $table->unsignedBigInteger('id_delito')->nullable(false)->change();
        // });
    }
};
