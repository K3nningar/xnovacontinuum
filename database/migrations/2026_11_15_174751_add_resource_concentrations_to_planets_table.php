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
        Schema::table('planets', function (Blueprint $table) {
            $table->unsignedSmallInteger('metal_concentration')->default(100);
            $table->unsignedSmallInteger('crystal_concentration')->default(100);
            $table->unsignedSmallInteger('deuterium_concentration')->default(100);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planets', function (Blueprint $table) {
            $table->dropColumn(['metal_concentration', 'crystal_concentration', 'deuterium_concentration']);
        });
    }
};
