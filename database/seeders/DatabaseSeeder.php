<?php

namespace Database\Seeders;

use App\Models\Universe;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
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
