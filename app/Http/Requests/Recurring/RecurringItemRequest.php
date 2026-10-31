<?php

namespace App\Http\Requests\Recurring;

use App\Enums\CategoryType;
use App\Enums\RecurringInterval;
use App\Enums\RecurringKind;
use App\Services\RecurringScheduleService;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecurringItemRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $kind = $this->enum('kind', RecurringKind::class);

        return [
            'kind' => ['required', Rule::enum(RecurringKind::class)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'integer', 'min:1', 'max:99999999999'],
            'interval' => ['required', Rule::enum(RecurringInterval::class)],
            'next_date' => ['required', 'date'],

            // A client charge has to name the client it is for.
            'client_id' => [
                $kind === RecurringKind::ClientBilling ? 'required' : 'nullable',
                'integer', 'exists:clients,id',
            ],
            'project_id' => [
                'nullable', 'integer',
                Rule::exists('projects', 'id')->when(
                    $this->filled('client_id'),
                    fn ($rule) => $rule->where('client_id', $this->input('client_id')),
                ),
            ],
            // A cost of your own is filed under an expense category and paid
            // from an account.
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where(
                    'type',
                    CategoryType::Expense->value,
                ),
            ],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],

            'auto_generate' => ['required', 'boolean'],
            'remind_days_before' => ['required', 'integer', 'min:0', 'max:180'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => Money::toSen($this->input('amount')),
            // Only a client charge can draft its own invoice.
            'auto_generate' => $this->boolean('auto_generate')
                && $this->input('kind') === RecurringKind::ClientBilling->value,
            'is_active' => $this->boolean('is_active'),
            'remind_days_before' => (int) $this->input('remind_days_before', 14),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => __('recurring.error.client_required'),
        ];
    }

    /**
     * The anchor day comes from the date the owner chose, so a charge on the
     * 31st keeps coming back to the 31st (PLAN.md phase 4).
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $validated = $this->validated();

        return [
            ...$validated,
            'anchor_day' => RecurringScheduleService::anchorFor($validated['next_date']),
        ];
    }
}
