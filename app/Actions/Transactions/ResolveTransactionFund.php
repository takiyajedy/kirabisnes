<?php

namespace App\Actions\Transactions;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Fund;

/**
 * Works out which fund a transaction touches, so the choice is stored on the
 * row and visible in the UI rather than hidden in the allocation service.
 *
 *   expense           -> the fund chosen, else the category default, else operating
 *   owner_draw        -> owner_pay
 *   capital_injection -> the fund chosen, else capital
 *   income / transfer -> none
 */
class ResolveTransactionFund
{
    public function handle(
        TransactionType $type,
        ?int $fundId,
        ?int $categoryId,
    ): ?int {
        return match ($type) {
            TransactionType::Expense => $fundId
                ?? $this->categoryDefault($categoryId)
                ?? $this->bySlug('operating'),
            TransactionType::OwnerDraw => $this->bySlug('owner_pay'),
            TransactionType::CapitalInjection => $fundId ?? $this->bySlug('capital'),
            TransactionType::Income, TransactionType::Transfer => null,
        };
    }

    private function categoryDefault(?int $categoryId): ?int
    {
        if ($categoryId === null) {
            return null;
        }

        return Category::query()->whereKey($categoryId)->value('default_fund_id');
    }

    private function bySlug(string $slug): ?int
    {
        return Fund::query()->where('slug', $slug)->value('id');
    }
}
