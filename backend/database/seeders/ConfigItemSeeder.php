<?php

namespace Database\Seeders;

use App\Modules\Config\Services\ConfigService;
use Illuminate\Database\Seeder;

class ConfigItemSeeder extends Seeder
{
    public function run(): void
    {
        app(ConfigService::class)->ensureDefaults();
    }
}
