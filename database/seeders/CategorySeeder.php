<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Fund;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Default income and expense categories for a solo freelance IT business.
     */
    public function run(): void
    {
        $operating = Fund::query()->where('slug', 'operating')->value('id');
        $capital = Fund::query()->where('slug', 'capital')->value('id');

        // [ms, en, default fund id, tax deductible by default]
        $income = [
            ['Projek Web', 'Web Project'],
            ['Projek Sistem', 'System Project'],
            ['Penyelenggaraan', 'Maintenance'],
            ['Hosting & Domain (jual semula)', 'Hosting & Domain (resale)'],
            ['Konsultasi', 'Consulting'],
            ['Lain-lain', 'Others'],
        ];

        $expense = [
            ['Hosting & Server', 'Hosting & Server', $operating, true],
            ['Domain', 'Domain', $operating, true],
            ['Perisian & SaaS', 'Software & SaaS', $operating, true],
            ['Perkakasan', 'Hardware', $capital, true],
            ['Internet & Telefon', 'Internet & Phone', $operating, true],
            ['Minyak / Tol / Parking', 'Fuel / Toll / Parking', $operating, true],
            ['Subkontrak', 'Subcontract', $operating, true],
            ['Kursus & Buku', 'Courses & Books', $capital, true],
            ['Caj Bank / Gateway', 'Bank & Gateway Fees', $operating, true],
            ['Iklan & Pemasaran', 'Ads & Marketing', $operating, true],
            ['Lain-lain', 'Others', $operating, false],
        ];

        foreach ($income as [$ms, $en]) {
            $this->upsert($ms, $en, CategoryType::Income, null, false);
        }

        foreach ($expense as [$ms, $en, $fundId, $deductible]) {
            $this->upsert($ms, $en, CategoryType::Expense, $fundId, $deductible);
        }
    }

    private function upsert(
        string $ms,
        string $en,
        CategoryType $type,
        ?int $defaultFundId,
        bool $taxDeductible,
    ): void {
        $existing = Category::query()
            ->where('type', $type)
            ->whereJsonContains('name->en', $en)
            ->first();

        $attributes = [
            'name' => ['ms' => $ms, 'en' => $en],
            'type' => $type,
            'default_fund_id' => $defaultFundId,
            'tax_deductible_default' => $taxDeductible,
            'is_system' => true,
        ];

        if ($existing !== null) {
            $existing->update($attributes);

            return;
        }

        Category::query()->create($attributes);
    }
}
