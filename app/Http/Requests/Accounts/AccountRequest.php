<?php

namespace App\Http\Requests\Accounts;

use App\Enums\AccountType;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['required', 'integer', 'min:-99999999999', 'max:99999999999'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * The form posts ringgit; everything downstream works in sen.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'opening_balance' => Money::toSen($this->input('opening_balance')),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
    }
}
