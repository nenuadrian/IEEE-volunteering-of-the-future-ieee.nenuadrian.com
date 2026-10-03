<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Core reference data always; the backdated demo dataset unless
     * SEED_DEMO_DATA=false (remove it later with `php artisan demo:purge`).
     */
    public function run(): void
    {
        $this->call([
            CoreSeeder::class,
            EmailTemplateSeeder::class,
        ]);

        if (filter_var(env('SEED_DEMO_DATA', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
