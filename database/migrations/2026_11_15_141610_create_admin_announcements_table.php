<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Defines database schema changes for 2026_11_15_141610_create_admin_announcements_table.
 */

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
        Schema::create('admin_announcements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->text('body');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('admin_announcement_recipients', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('announcement_id')->constrained('admin_announcements')->cascadeOnDelete();
            $table->primary(['user_id', 'announcement_id']);
            $table->index('announcement_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_announcement_recipients');
        Schema::dropIfExists('admin_announcements');
    }
};
