<?php

namespace App\Http\Requests\Import;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BankImportRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'statement' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'has_headers' => ['required', 'boolean'],
            'date_format' => ['required', 'string', 'in:auto,dmy,mdy,ymd'],

            // Column positions, which the picker fills in from the file.
            'date_column' => ['required', 'integer', 'min:0', 'max:200'],
            'description_column' => ['required', 'integer', 'min:0', 'max:200'],
            'amount_column' => ['required', 'integer', 'min:0', 'max:200'],
            // Set only when the statement keeps debit and credit apart.
            'credit_column' => ['nullable', 'integer', 'min:0', 'max:200'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'has_headers' => $this->boolean('has_headers'),
            'credit_column' => $this->filled('credit_column')
                ? (int) $this->input('credit_column')
                : null,
        ]);
    }

    /**
     * @return array{date: int, description: int, amount: int, credit: ?int}
     */
    public function mapping(): array
    {
        $validated = $this->validated();

        return [
            'date' => (int) $validated['date_column'],
            'description' => (int) $validated['description_column'],
            'amount' => (int) $validated['amount_column'],
            'credit' => $validated['credit_column'] ?? null,
        ];
    }
}
