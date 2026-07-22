import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import { initializeLocale } from '@/composables/useLocale';
import { i18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { initializeEcho, refreshCsrf } from '@/lib/echo';

const appName = import.meta.env.VITE_APP_NAME || 'Reflow';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.use(i18n);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will set the interface language on page load...
initializeLocale();

// This will listen for flash toast data from the server...
initializeFlashToast();

// This will connect to the Soketi / Pusher WebSocket service for realtime collaboration.
// Degrades gracefully if the service is not running.
initializeEcho();

// 登录 / 会话刷新后 CSRF token 会变，每次导航结束刷新一次，
// 避免 presence 频道鉴权（/broadcasting/auth）拿到过期 token 返回 419。
router.on('finish', () => refreshCsrf());

// 204 is an expected "no content" success response (comments, card moves, etc.).
// Inertia treats any non-Inertia response (no X-Inertia header) as an httpException
// and pops a full-screen white `inertia-error-dialog` that swallows all clicks.
// Suppress that dialog for 204, but keep real 5xx errors visible.
router.on('httpException', (event) => {
    if (event.detail.response?.status === 204) {
        event.preventDefault();
    }
});
