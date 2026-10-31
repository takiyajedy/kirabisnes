<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        Account::query()->firstOrCreate(
            ['name' => 'Tunai'],
            [
                'type' => AccountType::Cash,
                'opening_balance' => 0,
                'is_active' => true,
                'sort_order' => 0,
            ],
        );
    }
}
