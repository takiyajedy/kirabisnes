import { router } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

const APPEARANCES: Appearance[] = ['light', 'dark', 'system'];

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        document.documentElement.classList.toggle('dark', prefersDark());
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getCookie = (name: string): string | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : null;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const isAppearance = (value: unknown): value is Appearance =>
    typeof value === 'string' && APPEARANCES.includes(value as Appearance);

/**
 * The cookie is written by the server from users.appearance, so it wins over
 * localStorage: that keeps the choice in sync across devices.
 */
const getStoredAppearance = (): Appearance | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    const fromCookie = getCookie('appearance');

    if (isAppearance(fromCookie)) {
        return fromCookie;
    }

    const fromStorage = localStorage.getItem('appearance');

    return isAppearance(fromStorage) ? fromStorage : null;
};

const prefersDark = (): boolean => mediaQuery()?.matches ?? false;

const handleSystemThemeChange = () => {
    updateTheme(getStoredAppearance() ?? 'system');
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const stored = getStoredAppearance() ?? 'system';

    appearance.value = stored;
    localStorage.setItem('appearance', stored);
    updateTheme(stored);

    // Follow the OS while "system" is selected...
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

const appearance = ref<Appearance>('system');

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        appearance.value = getStoredAppearance() ?? 'system';
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (appearance.value === 'system') {
            return prefersDark() ? 'dark' : 'light';
        }

        return appearance.value;
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        // Avoids a flash of the wrong theme on the next full page load...
        localStorage.setItem('appearance', value);
        setCookie('appearance', value);

        updateTheme(value);

        // Persist to users.appearance so the choice follows the user...
        router.patch(
            '/settings/appearance',
            { appearance: value },
            { preserveScroll: true, preserveState: true },
        );
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
