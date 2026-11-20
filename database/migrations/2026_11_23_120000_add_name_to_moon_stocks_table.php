<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moon_stocks', function (Blueprint $table): void {
            $table->string('name', 80)->nullable()->after('orbit');
        });
    }

    public function down(): void
    {
        Schema::table('moon_stocks', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }
};
