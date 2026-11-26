<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedSmallInteger('officer_spy_research_bonus')->default(0);
            $table->unsignedSmallInteger('officer_commander_research_bonus')->default(0);
        });

        $adminLevels = [];
        DB::table('admin_audit_logs')->join('planets', 'planets.id', '=', 'admin_audit_logs.planet_id')
            ->where('admin_audit_logs.action', 'element_added')->orderBy('admin_audit_logs.id')
            ->select(['admin_audit_logs.id', 'admin_audit_logs.details', 'planets.user_id'])
            ->chunkById(500, function ($logs) use (&$adminLevels): void {
                foreach ($logs as $log) {
                    $details = json_decode($log->details, true);
                    $key = match ((string) ($details['key'] ?? '')) {
                        '610' => 'spy',
                        '611' => 'commander',
                        default => null,
                    };
                    if (($details['category'] ?? null) === 'officers' && $key !== null) {
                        $adminLevels[$log->user_id][$key] = ($adminLevels[$log->user_id][$key] ?? 0) + max(0, (int) ($details['amount'] ?? 0));
                    }
                }
            }, 'admin_audit_logs.id', 'id');

        DB::table('users')->where(fn ($query) => $query->where('officer_spy_level', '>', 0)->orWhere('officer_commander_level', '>', 0))
            ->orderBy('id')->chunkById(500, function ($users) use ($adminLevels): void {
                foreach ($users as $user) {
                    DB::table('users')->where('id', $user->id)->update([
                        'officer_spy_research_bonus' => min((int) $user->spy_technology_level, 5 * max(0, (int) $user->officer_spy_level - ($adminLevels[$user->id]['spy'] ?? 0))),
                        'officer_commander_research_bonus' => min((int) $user->computer_technology_level, 3 * max(0, (int) $user->officer_commander_level - ($adminLevels[$user->id]['commander'] ?? 0))),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['officer_spy_research_bonus', 'officer_commander_research_bonus']);
        });
    }
};
