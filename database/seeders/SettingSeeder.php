<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\BusinessProfile;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (BusinessProfile::defaults() as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
