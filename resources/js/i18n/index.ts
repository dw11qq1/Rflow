import { createI18n } from 'vue-i18n';
import en from './en';
import zh from './zh';

export const SUPPORTED_LOCALES = ['zh', 'en'] as const;
export type LocaleCode = (typeof SUPPORTED_LOCALES)[number];

const STORAGE_KEY = 'locale';
const DEFAULT_LOCALE: LocaleCode = 'zh';

const readCookie = (name: string): string | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(
        new RegExp('(?:^|; )' + name + '=([^;]*)'),
    );

    return match ? decodeURIComponent(match[1]) : null;
};

const isSupported = (value: string | null): value is LocaleCode =>
    value !== null && (SUPPORTED_LOCALES as readonly string[]).includes(value);

export function resolveInitialLocale(): LocaleCode {
    if (typeof window === 'undefined') {
        return DEFAULT_LOCALE;
    }

    const stored = localStorage.getItem(STORAGE_KEY) ?? readCookie(STORAGE_KEY);

    if (isSupported(stored)) {
        return stored;
    }

    const browser = navigator.language?.toLowerCase() ?? '';

    return browser.startsWith('en') ? 'en' : 'zh';
}

export const i18n = createI18n({
    legacy: false,
    globalInjection: true,
    locale: resolveInitialLocale(),
    fallbackLocale: DEFAULT_LOCALE,
    // Breadcrumbs / titles pass i18n keys through t(); dynamic values such as
    // board names are not keys, so silence the "missing key" warnings for them.
    missingWarn: false,
    fallbackWarn: false,
    messages: { zh, en },
});
