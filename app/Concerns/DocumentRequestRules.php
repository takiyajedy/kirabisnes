<?php

namespace App\Concerns;

use App\Enums\Locale;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The parts a quotation request and an invoice request share: the client, the
 * document language, the discount and the line items.
 */
trait DocumentRequestRules
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function documentRules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            // A project belonging to someone else would put this document on
            // the wrong client's books.
            'project_id' => [
                'nullable', 'integer',
                Rule::exists('projects', 'id')->where('client_id', $this->input('client_id')),
            ],
            'locale' => ['required', Rule::enum(Locale::class)],
            'discount' => ['required', 'integer', 'min:0', 'max:99999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'items.*.unit_price' => ['required', 'integer', 'min:0', 'max:99999999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function documentAttributes(): array
    {
        return [
            'items.*.description' => __('document.item.description'),
            'items.*.quantity' => __('document.item.quantity'),
            'items.*.unit_price' => __('document.item.unit_price'),
        ];
    }

    /**
     * The form posts ringgit for every amount, including inside the line
     * items (PLAN.md 3.1).
     */
    protected function convertDocumentMoney(): void
    {
        $items = $this->input('items', []);

        $this->merge([
            'discount' => Money::toSen($this->input('discount')),
            'items' => array_values(array_map(
                fn (array $item) => [
                    ...$item,
                    'unit_price' => Money::toSen($item['unit_price'] ?? 0),
                ],
                array_filter(is_array($items) ? $items : [], 'is_array'),
            )),
        ]);
    }

    /**
     * Only the fields a document stores, with the items kept separate for the
     * action that writes them.
     *
     * @return array<string, mixed>
     */
    protected function documentAttributesToStore(): array
    {
        return $this->safe()->only([
            'client_id', 'project_id', 'locale', 'discount', 'notes', 'terms',
            'status', 'issue_date', 'valid_until', 'due_date', 'items',
        ]);
    }
}
