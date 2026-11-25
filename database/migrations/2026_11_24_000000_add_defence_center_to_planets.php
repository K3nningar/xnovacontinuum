<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planets', function (Blueprint $table) {
            $table->unsignedSmallInteger('defence_center_level')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('planets', function (Blueprint $table) {
            $table->dropColumn('defence_center_level');
        });
    }
};
