<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleets', function (Blueprint $table): void {
            $table->unsignedSmallInteger('target_orbit')->nullable();
            $table->unsignedSmallInteger('origin_orbit')->nullable();
        });

        Schema::table('moon_stocks', function (Blueprint $table): void {
            $table->unsignedSmallInteger('moon_base_level')->default(0);
            $table->unsignedSmallInteger('phalanx_level')->default(0);
            $table->unsignedSmallInteger('jump_gate_level')->default(0);
            $table->unsignedBigInteger('max_fields')->default(1);
            $table->unsignedBigInteger('used_fields')->default(0);
            $table->json('unit_stock')->nullable();
            $table->timestamp('jump_gate_used_at')->nullable();
            $table->timestamp('resources_updated_at')->useCurrent();

        });

        Schema::create('moon_building_queues', function (Blueprint $table) {
			$table->id();
			$table->foreignId('planet_id')->constrained()->cascadeOnDelete();
			$table->unsignedSmallInteger('orbit');
			$table->string('building_key', 32);
			$table->unsignedSmallInteger('building_code');
			$table->unsignedSmallInteger('target_level');
			$table->unsignedTinyInteger('position');
			$table->unsignedBigInteger('cost_metal')->default(0);
			$table->unsignedBigInteger('cost_crystal')->default(0);
			$table->unsignedBigInteger('cost_deuterium')->default(0);

			// dateTime : aucun défaut implicite
			$table->dateTime('starts_at');
			$table->dateTime('completes_at');

			$table->timestamps();

			$table->index('completes_at');
		});
    }

    public function down(): void
    {
        Schema::dropIfExists('moon_building_queues');

        Schema::table('fleets', function (Blueprint $table): void {
            $table->dropColumn(['target_orbit', 'origin_orbit']);
        });

        Schema::table('moon_stocks', function (Blueprint $table): void {
            $table->dropColumn(['moon_base_level', 'phalanx_level', 'jump_gate_level', 'max_fields', 'used_fields', 'unit_stock', 'jump_gate_used_at', 'resources_updated_at']);
        });
    }
};
