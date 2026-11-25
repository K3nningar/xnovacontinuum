<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseGuard;

class TestDatabaseGuardTest extends TestCase
{
    #[TestWith([['driver' => 'sqlite', 'database' => ':memory:']])]
    #[TestWith([['driver' => 'mysql', 'database' => 'game_test']])]
    #[TestWith([['driver' => 'mariadb', 'database' => 'game_test_1']])]
    #[TestWith([['driver' => 'mysql', 'database' => 'game', 'url' => 'mysql://localhost/game_test']])]
    public function test_it_allows_safe_connections(array $configuration): void
    {
        TestDatabaseGuard::check($configuration);

        $this->assertTrue(true);
    }

    #[TestWith([['driver' => 'mysql', 'database' => 'game']])]
    #[TestWith([['driver' => 'mysql', 'database' => 'game_test_backup']])]
    #[TestWith([['driver' => 'mysql', 'database' => 'game_test', 'url' => 'mysql://localhost/game']])]
    #[TestWith([['driver' => 'mysql', 'database' => 'game_test', 'url' => 'mysql://localhost/game_test?database=game']])]
    public function test_it_rejects_unsafe_mysql_connections(array $configuration): void
    {
        $this->expectException(RuntimeException::class);

        TestDatabaseGuard::check($configuration);
    }
}
