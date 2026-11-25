<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MySqlLockingTest extends TestCase
{
    public function test_innodb_row_lock_blocks_another_connection_until_commit(): void
    {
        $first = DB::connection();
        if ($first->getDriverName() !== 'mysql') {
            $this->markTestSkipped('This test requires MySQL and InnoDB.');
        }

        $table = 'lock_probe_'.bin2hex(random_bytes(8));
        config(['database.connections.lock_probe' => $first->getConfig()]);
        $second = DB::connection('lock_probe');

        Schema::create($table, function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedInteger('value');
        });

        try {
            $first->table($table)->insert(['id' => 1, 'value' => 0]);
            $second->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $first->beginTransaction();
            $first->table($table)->where('id', 1)->lockForUpdate()->first();
            $first->table($table)->where('id', 1)->increment('value');
            $second->beginTransaction();

            try {
                $second->table($table)->where('id', 1)->increment('value');
                $this->fail('The second connection should time out while the row is locked.');
            } catch (QueryException $exception) {
                $this->assertSame(1205, $exception->errorInfo[1]);
            }

            $second->rollBack();
            $first->commit();
            $second->table($table)->where('id', 1)->increment('value');
            $this->assertSame(2, (int) $first->table($table)->where('id', 1)->value('value'));
        } finally {
            while ($second->transactionLevel() > 0) {
                $second->rollBack();
            }
            while ($first->transactionLevel() > 0) {
                $first->rollBack();
            }
            Schema::dropIfExists($table);
            DB::purge('lock_probe');
        }
    }
}
