import { createInertiaApp } from '@inertiajs/vue3';
import { i18nVue, reset as resetI18n } from 'laravel-vue-i18n';
import type { LanguageJsonFileInterface } from 'laravel-vue-i18n/interfaces/language-json-file';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'KiraBisnes';

// lang/ms.json, lang/en.json and the php_* files the Vite plugin generates
// from lang/{locale}/*.php.
const messages = import.meta.glob<LanguageJsonFileInterface>(
    '../../lang/*.json',
    { eager: true },
);

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            // Pages a client reaches by signed link, without an account.
            case name.startsWith('Public/'):
                return PublicLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app, { page }) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });

        // The i18n plugin keeps its loaded messages in module state, which a
        // server-rendering process holds across requests. Clearing it here —
        // withApp runs once per render — stops one request's language from
        // leaking into the next, and picks up newly added keys in dev.
        resetI18n();

        // Share Laravel's lang/*.json and lang/{locale}/*.php with Vue.
        // Eager, because the plugin resolves messages synchronously during
        // server rendering — a lazy import would paint raw keys first.
        app.use(i18nVue, {
            lang: page.props.locale,
            fallbackLang: 'en',
            resolve: (lang: string) =>
                messages[`../../lang/${lang}.json`]?.default ?? {},
        });
    },
    progress: {
        color: '#059669',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
