<?php

namespace Database\Seeders;

use App\Enums\Appearance;
use App\Enums\Locale;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@kirabisnes.test'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'locale' => Locale::Malay,
                'appearance' => Appearance::System,
            ],
        );

        $this->call([
            SettingSeeder::class,
            FundSeeder::class,
            CategorySeeder::class,
            AccountSeeder::class,
        ]);
    }
}
