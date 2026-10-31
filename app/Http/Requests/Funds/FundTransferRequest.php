<?php

namespace App\Http\Requests\Funds;

use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FundTransferRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_fund_id' => ['required', 'integer', 'exists:funds,id'],
            'to_fund_id' => [
                'required', 'integer', 'exists:funds,id', 'different:from_fund_id',
            ],
            'amount' => ['required', 'integer', 'min:1', 'max:99999999999'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['amount' => Money::toSen($this->input('amount'))]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_fund_id.different' => __('fund.error.same_fund'),
        ];
    }
}
