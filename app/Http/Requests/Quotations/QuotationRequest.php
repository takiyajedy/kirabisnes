<?php

namespace App\Http\Requests\Quotations;

use App\Concerns\DocumentRequestRules;
use App\Enums\QuotationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuotationRequest extends FormRequest
{
    use DocumentRequestRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->documentRules(),
            'issue_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:issue_date'],
            'status' => ['required', Rule::enum(QuotationStatus::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->convertDocumentMoney();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->documentAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'valid_until.after_or_equal' => __('quotation.error.valid_before_issue'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return $this->documentAttributesToStore();
    }
}
