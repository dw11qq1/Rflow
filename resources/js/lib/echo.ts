import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { ref } from 'vue';

declare global {
    interface Window {
        Pusher?: typeof Pusher;
        Echo?: Echo;
    }
}

let echoInstance: Echo | null = null;

/**
 * 全局 WebSocket 连接状态（'connecting' | 'connected' | 'disconnected'）。
 * 组件据此判断「已连接」绿灯与在线成员，而非仅依赖 presence 的 here 回调，
 * 这样连接抖动 / 重连时状态始终真实，不再「时有时无」。
 */
export const echoState = ref<'connecting' | 'connected' | 'disconnected'>('disconnected');

/**
 * 初始化 Laravel Echo（连接 Soketi / Pusher 协议 WebSocket 服务）。
 *
 * - 若未配置 VITE_SOKETI_KEY 或连接失败，优雅降级：仅打 warn，不阻断页面。
 * - 实时协同是「锦上添花」而非「硬性依赖」，因此任何异常都不应让看板页面崩溃。
 */
export function initializeEcho(): Echo | null {
    if (echoInstance) {
        return echoInstance;
    }

    if (typeof window === 'undefined') {
        return null;
    }

    const key = import.meta.env.VITE_SOKETI_KEY;

    if (!key) {
        console.warn('[Reflow] 未配置 VITE_SOKETI_KEY，实时协同已禁用。');
        return null;
    }

    try {
        // laravel-echo v2 需要全局 Pusher 实例
        window.Pusher = Pusher;

        echoInstance = new Echo({
            broadcaster: 'pusher',
            key,
            cluster: 'mt1',
            wsHost: import.meta.env.VITE_SOKETI_HOST || '127.0.0.1',
            wsPort: Number(import.meta.env.VITE_SOKETI_PORT || 6001),
            wssPort: Number(import.meta.env.VITE_SOKETI_PORT || 6001),
            forceTLS: (import.meta.env.VITE_SOKETI_SCHEME || 'http') === 'https',
            disableStats: true,
            enabledTransports: ['ws', 'wss'],
            // CSRF 头由 refreshCsrf() 在初始化与每次 Inertia 导航后动态从 meta 标签刷新（见下方），
            // 避免会话刷新后 /broadcasting/auth 拿到过期 token 返回 419 导致 presence 订阅失败。
        });

        // 连接异常（如 Soketi 未启动）时仅告警，不抛出，避免白屏
        const connector = echoInstance.connector as unknown as {
            pusher?: {
                bind: (e: string, cb: (err: unknown) => void) => void;
                connection?: {
                    bind: (e: string, cb: (states: { current?: string }) => void) => void;
                };
            };
        };
        connector.pusher?.bind('error', (err: unknown) => {
            console.warn(
                '[Reflow] 实时连接异常：',
                (err as { message?: string })?.message ?? err,
            );
        });

        // 真实连接状态 → echoState（绿灯 / 在线成员据此判断，而非仅依赖 here 回调）
        const conn = connector.pusher?.connection;
        conn?.bind('state_change', (states: { current?: string }) => {
            const s = states.current;
            echoState.value =
                s === 'connected'
                    ? 'connected'
                    : s === 'connecting' || s === 'unavailable'
                      ? 'connecting'
                      : 'disconnected';
        });
        conn?.bind('connected', () => {
            echoState.value = 'connected';
        });
        conn?.bind('disconnected', () => {
            echoState.value = 'disconnected';
        });

        // 初始化时刷新一次 CSRF 头（后续每次 Inertia 导航由 app.ts 调用 refreshCsrf 续刷）
        refreshCsrf();

        window.Echo = echoInstance;
    } catch (err) {
        console.warn('[Reflow] 实时协同初始化失败：', err);
        echoInstance = null;
        window.Echo = undefined;
    }

    return echoInstance;
}

/**
 * 从页面 meta[name="csrf-token"] 实时读取并刷新 Echo 鉴权头。
 * 登录 / 会话刷新后 token 会变，若不刷新 /broadcasting/auth 会返回 419，
 * 导致 presence 频道订阅失败、实时功能失效。
 */
export function refreshCsrf(): void {
    if (!echoInstance) {
        return;
    }
    const token =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const opts = echoInstance.options as {
        auth?: { headers?: Record<string, string> };
        userAuthentication?: { headers?: Record<string, string> };
    };
    if (opts.auth?.headers) {
        opts.auth.headers['X-CSRF-TOKEN'] = token;
    }
    if (opts.userAuthentication?.headers) {
        opts.userAuthentication.headers['X-CSRF-TOKEN'] = token;
    }
}

export function getEcho(): Echo | null {
    return echoInstance;
}
