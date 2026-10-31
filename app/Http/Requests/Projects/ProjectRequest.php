<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectStatus;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'contract_amount' => ['required', 'integer', 'min:0', 'max:99999999999'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'internal_hourly_rate' => ['nullable', 'integer', 'min:0', 'max:99999999999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'contract_amount' => Money::toSen($this->input('contract_amount')),
            'internal_hourly_rate' => $this->filled('internal_hourly_rate')
                ? Money::toSen($this->input('internal_hourly_rate'))
                : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => __('project.error.end_before_start'),
        ];
    }
}
