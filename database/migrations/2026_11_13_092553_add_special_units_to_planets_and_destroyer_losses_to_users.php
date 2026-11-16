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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('planetary_destroyer_losses')->default(0);
        });

        Schema::table('planets', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_216')->default(0);
            $table->unsignedBigInteger('unit_217')->default(0);
            $table->unsignedBigInteger('unit_409')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('planetary_destroyer_losses');
        });

        Schema::table('planets', function (Blueprint $table) {
            $table->dropColumn(['unit_216', 'unit_217', 'unit_409']);
        });
    }
};
