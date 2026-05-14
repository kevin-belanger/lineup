<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\ApplicationSettings;
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
        Setting::query()->firstOrCreate(
            ['key' => ApplicationSettings::APP_NAME_KEY],
            ['value' => ApplicationSettings::DEFAULT_APP_NAME],
        );
        Setting::query()->firstOrCreate(
            ['key' => ApplicationSettings::AUTO_CANCEL_REQUESTS_ENABLED_KEY],
            ['value' => '0'],
        );
        Setting::query()->firstOrCreate(
            ['key' => ApplicationSettings::AUTO_CANCEL_REQUESTS_TIME_KEY],
            ['value' => ApplicationSettings::DEFAULT_AUTO_CANCEL_REQUESTS_TIME],
        );
        Setting::query()->firstOrCreate(
            ['key' => ApplicationSettings::PRIORITY_REQUEST_DEFAULT_MESSAGE_KEY],
            ['value' => ApplicationSettings::DEFAULT_PRIORITY_REQUEST_MESSAGE],
        );
        Setting::query()->firstOrCreate(
            ['key' => ApplicationSettings::TIMEZONE_KEY],
            ['value' => ApplicationSettings::DEFAULT_TIMEZONE],
        );

        $this->call(AdminUserSeeder::class);

        if (filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
