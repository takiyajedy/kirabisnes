<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The business details printed on invoices, quotations and reports.
 * Stored one row per key in `settings`.
 */
final class BusinessProfile
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'business_name' => '',
            'business_reg_no' => '',
            'business_email' => '',
            'business_phone' => '',
            'business_address' => '',
            'logo_path' => null,
            'bank_details' => '',
            'invoice_footer_note' => ['ms' => '', 'en' => ''],
            'invoice_prefix' => 'INV',
            'quotation_prefix' => 'QUO',
            'default_payment_terms_days' => 14,
            'default_document_locale' => 'ms',
            // Where to tell a personal money tracker about an owner draw.
            'owner_draw_webhook_url' => null,
            'owner_draw_webhook_secret' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return Setting::many(self::defaults());
    }
}
