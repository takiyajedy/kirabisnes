<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\FundResource;
use App\Models\Account;
use App\Models\Category;
use App\Models\Fund;

/**
 * Everything the transaction form needs to render.
 *
 * The form is opened from the top bar, the transactions page and the owner
 * pay page, so the lists it offers are gathered here rather than copied into
 * each controller.
 */
final class TransactionFormOptions
{
    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return [
            'types' => TransactionType::options(),
            'accounts' => AccountResource::collection(
                Account::query()->active()->ordered()->get(),
            )->resolve(),
            'categories' => CategoryResource::collection(
                Category::query()->ordered()->get(),
            )->resolve(),
            'funds' => FundResource::collection(
                Fund::query()->active()->ordered()->get(),
            )->resolve(),
            'defaultDocumentLocale' => BusinessProfile::all()['default_document_locale'],
        ];
    }
}
