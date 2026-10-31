import { getActiveLanguage } from 'laravel-vue-i18n';

const TAGS: Record<string, string> = {
    ms: 'ms-MY',
    en: 'en-MY',
};

/**
 * The ICU tag for the active UI language, used by every Intl formatter.
 */
export function currentLocaleTag(): string {
    return TAGS[getActiveLanguage()] ?? TAGS.ms;
}
