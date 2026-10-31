import { currentLocaleTag } from '@/lib/locale';

type DateInput = string | number | Date | null | undefined;

const toDate = (value: DateInput): Date | null => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
};

/**
 * "30 Okt 2026" in ms, "30 Oct 2026" in en.
 */
export function formatDate(value: DateInput, locale?: string): string {
    const date = toDate(value);

    if (date === null) {
        return '';
    }

    return new Intl.DateTimeFormat(locale ?? currentLocaleTag(), {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

/**
 * The numeric form used in dense tables and inputs: 30/10/2026.
 */
export function formatDateShort(value: DateInput, locale?: string): string {
    const date = toDate(value);

    if (date === null) {
        return '';
    }

    return new Intl.DateTimeFormat(locale ?? currentLocaleTag(), {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
}

export function formatDateTime(value: DateInput, locale?: string): string {
    const date = toDate(value);

    if (date === null) {
        return '';
    }

    return new Intl.DateTimeFormat(locale ?? currentLocaleTag(), {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

/**
 * Month names for chart axes, in the active language.
 */
export function formatMonth(value: DateInput, locale?: string): string {
    const date = toDate(value);

    if (date === null) {
        return '';
    }

    return new Intl.DateTimeFormat(locale ?? currentLocaleTag(), {
        month: 'short',
    }).format(date);
}

export function formatNumber(
    value: number,
    options: Intl.NumberFormatOptions = {},
    locale?: string,
): string {
    return new Intl.NumberFormat(locale ?? currentLocaleTag(), options).format(
        value,
    );
}

/**
 * A signed percentage, for the "vs last month" comparisons on the dashboard.
 */
export function formatPercentChange(value: number, locale?: string): string {
    const formatted = new Intl.NumberFormat(locale ?? currentLocaleTag(), {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
        signDisplay: 'exceptZero',
    }).format(value);

    return `${formatted}%`;
}
