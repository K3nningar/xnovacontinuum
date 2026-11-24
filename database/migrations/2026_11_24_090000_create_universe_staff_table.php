<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('universe_staff', function (Blueprint $table): void {
            // commander_id est un UUID dans le schéma d’identités du dépôt.
            $table->uuid('commander_id');
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->timestamps();
            $table->primary(['commander_id', 'universe_id']);
            $table->index(['universe_id', 'level']);
        });

        DB::table('users')->whereNotNull('universe_id')->whereBetween('admin_level', [1, 2])
            ->orderBy('id')->get(['commander_id', 'universe_id', 'admin_level'])->each(function (object $profile): void {
                DB::table('universe_staff')->updateOrInsert(
                    ['commander_id' => $profile->commander_id, 'universe_id' => $profile->universe_id],
                    ['level' => $profile->admin_level, 'created_at' => now(), 'updated_at' => now()],
                );
            });

        DB::table('users')->whereBetween('admin_level', [1, 2])->update(['admin_level' => 0]);
        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->foreignId('universe_id')->nullable()->after('planet_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('universe_id');
        });
        Schema::dropIfExists('universe_staff');
    }
};
