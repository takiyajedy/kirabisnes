import type { Directive } from 'vue';
import type { Auth } from '@/types/auth';
import type { TimeEntry } from '@/types/models';
import type { Appearance, AvailableLocale } from '@/types/ui';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: {
            <T>(pattern: string): Record<string, () => Promise<T>>;
            <T>(pattern: string, options: { eager: true }): Record<string, T>;
        };
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            /** The uploaded business logo, or null for the built-in mark. */
            logo: string | null;
            auth: Auth;
            locale: string;
            localeTag: string;
            availableLocales: AvailableLocale[];
            /** The one timer that can be running, for the top bar widget. */
            runningTimer: TimeEntry | null;
            appearance: Appearance;
            canRegister: boolean;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
