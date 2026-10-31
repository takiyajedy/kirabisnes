<?php

namespace App\Http\Requests\Time;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TimeEntryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'started_at' => ['required', 'date'],
            // Typed in as minutes: a whole working day is 480 of them, and a
            // week is about as long as one entry ever sensibly gets.
            'minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'note' => ['nullable', 'string', 'max:255'],
            'billable' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'billable' => $this->boolean('billable'),
            'minutes' => (int) $this->input('minutes', 0),
        ]);
    }
}
