<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Seeds application data with DatabaseSeeder.
 */

namespace Database\Seeders;

use App\Models\Universe;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Ensures a single active default universe exists without overwriting any existing configuration.
     */
    public function run(): void
    {
        Universe::firstOrCreate(
            ['slug' => 'andromeda'],
            [
                'name' => 'Andromède',
                'status' => 'active',
                'galaxies' => 9,
                'systems_per_galaxy' => 499,
                'economy_speed' => 1,
                'fleet_speed' => 1,
            ],
        );
    }
}
