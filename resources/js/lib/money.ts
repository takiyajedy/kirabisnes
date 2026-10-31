import { currentLocaleTag } from '@/lib/locale';

/**
 * All money crossing the wire is an integer number of sen.
 * RM 12.50 => 1250. Never use floats for money.
 */
export function formatMoney(sen: number, locale?: string): string {
    return new Intl.NumberFormat(locale ?? currentLocaleTag(), {
        style: 'currency',
        currency: 'MYR',
    }).format(sen / 100);
}

/**
 * Like formatMoney, but without the currency symbol — for table columns that
 * already carry an "RM" header.
 */
export function formatMoneyPlain(sen: number, locale?: string): string {
    return new Intl.NumberFormat(locale ?? currentLocaleTag(), {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(sen / 100);
}

/**
 * Signed amount, for movements that can go either way.
 */
export function formatMoneySigned(sen: number, locale?: string): string {
    const formatted = formatMoney(Math.abs(sen), locale);

    return sen < 0 ? `-${formatted}` : formatted;
}

/**
 * Parse what the user typed in ringgit into sen.
 */
export function toSen(input: string | number | null | undefined): number {
    if (input === null || input === undefined || input === '') {
        return 0;
    }

    if (typeof input === 'number') {
        return Math.round(input * 100);
    }

    const cleaned = input.replace(/[^0-9.-]/g, '');

    if (cleaned === '' || cleaned === '-' || cleaned === '.') {
        return 0;
    }

    return Math.round(Number.parseFloat(cleaned) * 100);
}

/**
 * Turn sen back into the ringgit string a money input expects.
 */
export function toRinggit(sen: number): string {
    return (sen / 100).toFixed(2);
}

/**
 * Money leaves the browser in ringgit: the FormRequest turns it into sen
 * (PLAN.md section 3.1). Forms keep sen, because that is what MoneyInput
 * binds and what formatMoney reads, so the named fields are converted on
 * the way out — pass this to Inertia's `form.transform()`.
 */
export function asRinggit<T extends Record<string, unknown>>(
    data: T,
    ...fields: (keyof T & string)[]
): T {
    const out: Record<string, unknown> = { ...data };

    for (const field of fields) {
        const sen = out[field];

        if (typeof sen === 'number') {
            out[field] = toRinggit(sen);
        }
    }

    return out as T;
}
