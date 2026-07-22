import type { Ref } from 'vue';
import { ref } from 'vue';
import type { Locale } from '@/types';
import { i18n, resolveInitialLocale } from '@/i18n';

export type { Locale };

export type UseLocaleReturn = {
    locale: Ref<Locale>;
    setLocale: (value: Locale) => void;
};

const STORAGE_KEY = 'locale';

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const applyLocale = (value: Locale) => {
    i18n.global.locale.value = value;

    if (typeof document !== 'undefined') {
        document.documentElement.setAttribute('lang', value);
    }
};

const locale = ref<Locale>(resolveInitialLocale());

export function initializeLocale(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const initial = resolveInitialLocale();
    locale.value = initial;
    applyLocale(initial);
}

export function useLocale(): UseLocaleReturn {
    function setLocale(value: Locale) {
        locale.value = value;

        // Store in localStorage for client-side persistence...
        localStorage.setItem(STORAGE_KEY, value);

        // Store in cookie for SSR...
        setCookie(STORAGE_KEY, value);

        applyLocale(value);
    }

    return {
        locale,
        setLocale,
    };
}
