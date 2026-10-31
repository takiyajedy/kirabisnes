<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case OwnerDraw = 'owner_draw';
    case CapitalInjection = 'capital_injection';

    public function label(): string
    {
        return match ($this) {
            self::Income => __('transaction.type.income'),
            self::Expense => __('transaction.type.expense'),
            self::Transfer => __('transaction.type.transfer'),
            self::OwnerDraw => __('transaction.type.owner_draw'),
            self::CapitalInjection => __('transaction.type.capital_injection'),
        };
    }

    /**
     * Does this type add money to the account it names?
     */
    public function increasesAccount(): bool
    {
        return match ($this) {
            self::Income, self::CapitalInjection => true,
            self::Expense, self::OwnerDraw, self::Transfer => false,
        };
    }

    /**
     * Is this type recorded against a second account?
     */
    public function isTransfer(): bool
    {
        return $this === self::Transfer;
    }

    /**
     * Does this type spend from a fund rather than fill the funds?
     */
    public function spendsFromFund(): bool
    {
        return match ($this) {
            self::Expense, self::OwnerDraw => true,
            default => false,
        };
    }

    /**
     * May this type carry a category?
     */
    public function hasCategory(): bool
    {
        return match ($this) {
            self::Income, self::Expense => true,
            default => false,
        };
    }

    /**
     * The category type a transaction of this type can be filed under.
     */
    public function categoryType(): ?CategoryType
    {
        return match ($this) {
            self::Income => CategoryType::Income,
            self::Expense => CategoryType::Expense,
            default => null,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
