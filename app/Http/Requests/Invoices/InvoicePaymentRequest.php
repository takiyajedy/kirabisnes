<?php

namespace App\Http\Requests\Invoices;

use App\Enums\CategoryType;
use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoicePaymentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            // Never more than is owed: an overpayment is a credit, and this
            // phase has nowhere to keep one.
            'amount' => [
                'required', 'integer', 'min:1',
                'max:'.max(1, $this->invoice()->outstanding()),
            ],
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where('type', CategoryType::Income->value),
            ],
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
            'amount.max' => __('invoice.error.payment_too_large', [
                'outstanding' => Money::format($this->invoice()->outstanding()),
            ]),
        ];
    }

    private function invoice(): Invoice
    {
        /** @var Invoice $invoice */
        $invoice = $this->route('invoice');

        return $invoice;
    }
}
