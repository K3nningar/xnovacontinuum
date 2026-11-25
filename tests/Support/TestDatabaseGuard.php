<?php

namespace Tests\Support;

use Illuminate\Support\ConfigurationUrlParser;
use RuntimeException;

class TestDatabaseGuard
{
    public static function check(array $configuration): void
    {
        $configuration = (new ConfigurationUrlParser)->parseConfiguration($configuration);

        if (in_array($configuration['driver'] ?? null, ['mysql', 'mariadb'], true)
            && ! preg_match('/\A[a-zA-Z0-9_]+_test(?:_[0-9]+)?\z/', (string) ($configuration['database'] ?? ''))) {
            throw new RuntimeException('MySQL tests require a dedicated database ending in _test (or _test_N for parallel testing).');
        }
    }
}
