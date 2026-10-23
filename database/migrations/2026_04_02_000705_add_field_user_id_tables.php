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
            $table->foreignId('user_id');
            $table->text('padre',200)->nullable();
            $table->text('madre',200)->nullable();
            $table->text('grupo_sanguineo',200)->nullable();

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
