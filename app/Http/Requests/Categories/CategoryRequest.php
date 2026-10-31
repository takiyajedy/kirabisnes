<?php

namespace App\Http\Requests\Categories;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Category|null $category */
        $category = $this->route('category');

        return [
            // Malay is required, English optional and falls back to Malay.
            'name_ms' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            // A system category keeps its type: transactions already rely on it.
            'type' => [
                $category?->is_system ? 'nullable' : 'required',
                Rule::enum(CategoryType::class),
            ],
            'default_fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'tax_deductible_default' => ['required', 'boolean'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tax_deductible_default' => $this->boolean('tax_deductible_default'),
        ]);
    }

    /**
     * The attributes to persist, with the translated name assembled.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $ms = $this->string('name_ms')->trim()->value();
        $en = $this->string('name_en')->trim()->value();

        return [
            'name' => ['ms' => $ms, 'en' => $en === '' ? $ms : $en],
            'default_fund_id' => $this->input('default_fund_id'),
            'tax_deductible_default' => $this->boolean('tax_deductible_default'),
            'color' => $this->string('color')->value(),
        ];
    }
}
