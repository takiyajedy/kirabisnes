<?php

namespace App\Http\Requests\Invoices;

use App\Concerns\DocumentRequestRules;
use App\Enums\InvoiceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
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
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            // Only the statuses the owner sets by hand; the rest follow from
            // the payments and the overdue command.
            'status' => ['required', Rule::enum(InvoiceStatus::class)->only([
                InvoiceStatus::Draft, InvoiceStatus::Sent,
            ])],
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
            'due_date.after_or_equal' => __('invoice.error.due_before_issue'),
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
