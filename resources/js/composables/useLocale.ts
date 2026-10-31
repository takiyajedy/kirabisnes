import { router, usePage } from '@inertiajs/vue3';
import { loadLanguageAsync } from 'laravel-vue-i18n';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import type { AvailableLocale } from '@/types';

export type UseLocaleReturn = {
    locale: ComputedRef<string>;
    localeTag: ComputedRef<string>;
    availableLocales: ComputedRef<AvailableLocale[]>;
    updateLocale: (value: string) => Promise<void>;
};

export function useLocale(): UseLocaleReturn {
    const page = usePage();

    const locale = computed(() => page.props.locale);
    const localeTag = computed(() => page.props.localeTag);
    const availableLocales = computed(() => page.props.availableLocales);

    async function updateLocale(value: string): Promise<void> {
        if (value === locale.value) {
            return;
        }

        // Swap the messages in place, so there is no full page reload...
        await loadLanguageAsync(value);

        router.patch(
            '/settings/locale',
            { locale: value },
            { preserveScroll: true, preserveState: false },
        );
    }

    return { locale, localeTag, availableLocales, updateLocale };
}
