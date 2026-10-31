<?php

namespace App\Enums;

/**
 * The numbered documents. The value is also the key in `document_sequences`
 * and the settings key holding the prefix.
 */
enum DocumentType: string
{
    case Quotation = 'quotation';
    case Invoice = 'invoice';

    public function label(): string
    {
        return match ($this) {
            self::Quotation => __('quotation.one'),
            self::Invoice => __('invoice.one'),
        };
    }

    /**
     * The settings key holding the prefix for this document's numbers.
     */
    public function prefixSetting(): string
    {
        return match ($this) {
            self::Quotation => 'quotation_prefix',
            self::Invoice => 'invoice_prefix',
        };
    }
}
