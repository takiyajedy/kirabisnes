<?php

namespace App\Http\Requests\Settings;

use App\Enums\Locale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'business_reg_no' => ['nullable', 'string', 'max:50'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:50'],
            'business_address' => ['nullable', 'string', 'max:1000'],
            'bank_details' => ['nullable', 'string', 'max:1000'],
            'invoice_footer_note' => ['nullable', 'array'],
            'invoice_footer_note.ms' => ['nullable', 'string', 'max:500'],
            'invoice_footer_note.en' => ['nullable', 'string', 'max:500'],
            'invoice_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9-]+$/'],
            'quotation_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9-]+$/'],
            'default_payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'default_document_locale' => ['required', 'string', Rule::enum(Locale::class)],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],

            // Where to tell a personal money tracker about an owner draw.
            // https only: the payload says what the owner pays themselves.
            'owner_draw_webhook_url' => ['nullable', 'url:https', 'max:500'],
            'owner_draw_webhook_secret' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'invoice_prefix' => mb_strtoupper((string) $this->input('invoice_prefix')),
            'quotation_prefix' => mb_strtoupper((string) $this->input('quotation_prefix')),
        ]);
    }
}
