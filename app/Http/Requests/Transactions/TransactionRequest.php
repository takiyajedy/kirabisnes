<?php

namespace App\Http\Requests\Transactions;

use App\Enums\TransactionType;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->enum('type', TransactionType::class);

        return [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            // A transfer needs a second, different account.
            'to_account_id' => [
                $type === TransactionType::Transfer ? 'required' : 'nullable',
                'integer',
                'exists:accounts,id',
                'different:account_id',
            ],
            'amount' => ['required', 'integer', 'min:1', 'max:99999999999'],
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->when(
                    $type?->categoryType() !== null,
                    fn ($rule) => $rule->where('type', $type->categoryType()->value),
                ),
            ],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'tax_deductible' => ['required', 'boolean'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'remove_receipt' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The form posts ringgit; everything downstream works in sen.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => Money::toSen($this->input('amount')),
            'tax_deductible' => $this->boolean('tax_deductible'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_account_id.different' => __('transaction.error.same_account'),
        ];
    }

    /**
     * The attributes to persist. The receipt file is handled separately.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return $this->safe()->except(['receipt', 'remove_receipt']);
    }
}
