<?php

namespace App\Http\Requests\Funds;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FundPercentageRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'funds' => ['required', 'array', 'min:1'],
            'funds.*.id' => ['required', 'integer', 'exists:funds,id'],
            'funds.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'funds.*.is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * The active percentages must add up to exactly 100 (PLAN.md section 1).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $total = $this->activeShareTotal();

                if ($total !== 10_000) {
                    $validator->errors()->add('funds', __('fund.error.total_not_100', [
                        'total' => number_format($total / 100, 2),
                    ]));
                }
            },
        ];
    }

    /**
     * The sum of the active shares, in basis points to avoid float drift.
     */
    private function activeShareTotal(): int
    {
        $total = 0;

        foreach ((array) $this->input('funds', []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (! filter_var($row['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $total += (int) round(((float) ($row['percentage'] ?? 0)) * 100);
        }

        return $total;
    }
}
