<?php

namespace Database\Seeders;

use App\Models\Fund;
use Illuminate\Database\Seeder;

class FundSeeder extends Seeder
{
    /**
     * The five default funds every income is split across.
     * Percentages must add up to 100.
     */
    public function run(): void
    {
        $funds = [
            ['operating', 'Operasi', 'Operating', 30, '#0284c7'],
            ['owner_pay', 'Gaji Sendiri', 'Owner Pay', 40, '#059669'],
            ['tax', 'Cukai', 'Tax', 10, '#d97706'],
            ['emergency', 'Kecemasan', 'Emergency', 10, '#e11d48'],
            ['capital', 'Modal', 'Capital', 10, '#7c3aed'],
        ];

        foreach ($funds as $index => [$slug, $ms, $en, $percentage, $color]) {
            Fund::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => ['ms' => $ms, 'en' => $en],
                    'percentage' => $percentage,
                    'color' => $color,
                    'sort_order' => $index,
                    'is_active' => true,
                    'is_system' => true,
                ],
            );
        }
    }
}
