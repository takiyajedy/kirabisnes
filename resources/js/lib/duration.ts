/**
 * Minutes as people say them: "2j 30m" in Malay, "2h 30m" in English.
 *
 * Hours and minutes rather than a decimal, because that is how a working day
 * is spoken about. The reports use decimal hours, where the figure has to
 * multiply by a rate.
 */
import { trans } from 'laravel-vue-i18n';

export function formatDuration(minutes: number): string {
    const safe = Math.max(0, Math.round(minutes));
    const hours = Math.floor(safe / 60);
    const rest = safe % 60;

    if (hours === 0) {
        return `${rest}${trans('time.minute_short')}`;
    }

    if (rest === 0) {
        return `${hours}${trans('time.hour_short')}`;
    }

    return `${hours}${trans('time.hour_short')} ${rest}${trans('time.minute_short')}`;
}

/**
 * Decimal hours, for a figure that has to be multiplied by a rate.
 */
export function formatHours(minutes: number): string {
    return (Math.max(0, minutes) / 60).toFixed(1);
}
