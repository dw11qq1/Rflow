import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { getEcho, echoState } from '@/lib/echo';

export interface OnlineMember {
    id: number;
    name: string;
}

/**
 * 看板实时协同组合式函数。
 *
 * 订阅 presence 频道 `board.{slug}`（laravel-echo 的 join() 会自动补 `presence-` 前缀，
 * 因此此处传无前缀的 `board.{slug}`，最终订阅频道为 `presence-board.{slug}`）：
 *  - 维护在线成员列表（here / joining / leaving）
 *  - 监听 `board.updated` 事件（listen('.board.updated') 经 EventFormatter 去点后
 *    实际绑定裸名 `board.updated`，与后端 broadcastAs() 返回值一致），增量刷新 `board`
 *
 * 若 Echo 未初始化（Soketi 未启动 / 未配置），则静默返回空状态，不影响页面。
 *
 * ⚠️ 组件复用问题修复：
 * Inertia 客户端导航时，从一个看板进入另一个看板（如 /boards/a → /boards/b），
 * 页面组件同为 `Board/Show`，Vue 会**复用同一组件实例**，导致 `onMounted` 不再重跑，
 * presence 频道因此不会重新加入、旧频道也不会退出，onlineMembers 残留上次数据
 * （表现为「再次进入列表消失 / 显示错误成员」）。
 * 这里用 `watch(() => getSlug())` 监听看板切换（注意：必须传入 **getter** 而非值，
 * 因为 Inertia 复用组件时 `props.board.slug` 是响应式的、但 `setup` 初始解构出来的是
 * 固定字符串；若直接传值，watch 永远 watch 一个常量、永不触发，就是「再次进入时不刷新」
 * 的真正根因）。在 slug 变化时退出旧频道、重新加入新频道，并在每次（重新）订阅前
 * 重置 onlineMembers / connected，确保每次进入都重新获取且只保留真正在线的成员。
 */
export function useBoardRealtime(getSlug: () => string) {
    const onlineMembers = ref<OnlineMember[]>([]);
    const connected = ref(false);

    let reloadTimer: ReturnType<typeof setTimeout> | null = null;
    let currentSlug = '';

    // 强制清除 pusher-js 内部可能残留的「已退订但未移除」的频道对象。
    // 根因：pusher-js 的 Channel.unsubscribe() 只把 subscribed=false 并发送
    // pusher:unsubscribe，但不会从 pusher.channels 映射里删除该频道对象。
    // 于是重新 join 同名频道时，pusher-js 直接复用这个陈旧对象、不再重新发送
    // pusher:subscribe，服务端也就不会重发 pusher:subscription_succeeded，
    // 导致 here 回调不触发 —— 表现正是「导航回看板后在线成员 / 已连接消失，刷新才恢复」。
    // 删掉残留对象可强制 pusher-js 新建频道、重新订阅。
    function removeStaleChannel(echo: Echo, slug: string): void {
        const pusher = (
            echo.connector as unknown as {
                pusher?: { channels?: { channels?: Record<string, unknown> } };
            }
        )?.pusher;
        const map = pusher?.channels?.channels;
        const name = `presence-board.${slug}`;
        if (map && Object.prototype.hasOwnProperty.call(map, name)) {
            delete map[name];
        }
    }

    // 退出当前订阅的 presence 频道（存在才退）
    function unsubscribe(): void {
        const echo = getEcho();
        if (echo && currentSlug) {
            echo.leave(`board.${currentSlug}`);
            removeStaleChannel(echo, currentSlug);
        }
        currentSlug = '';
    }

    // 防抖刷新看板（供 board.updated 使用）
    function onUpdated(): void {
        if (reloadTimer) {
            clearTimeout(reloadTimer);
        }
        reloadTimer = setTimeout(() => {
            router.reload({
                only: ['board'],
                preserveScroll: true,
                preserveState: true,
            });
        }, 300);
    }

    // 订阅指定看板的 presence 频道（幂等：先退订旧的，再订阅新的）
    function subscribe(nextSlug: string): void {
        if (!nextSlug) {
            return;
        }
        const echo = getEcho();
        if (!echo) {
            // Echo 尚未初始化（极端时序回归）：400ms 后重试一次，
            // 避免「watch 只触发一次 + 此刻 Echo 为空」导致永久连不上
            setTimeout(() => subscribe(nextSlug), 400);
            return;
        }

        // 关键：每次（重新）订阅前先清空，避免「组件复用 / 切看板」残留旧数据
        // （残留会表现为：显示旧看板的在线成员，或包含本不该在线的成员）
        onlineMembers.value = [];
        connected.value = false;

        // 退出上一个看板频道（处理 Board/Show A → B 复用实例）
        unsubscribe();

        currentSlug = nextSlug;
        const channelName = `board.${nextSlug}`;

        // 双保险：重新订阅前清掉同名频道的残留对象，确保 pusher-js 新建频道并重发 subscription_succeeded
        removeStaleChannel(echo, nextSlug);

        const presence = echo
            .join(channelName)
            .here((members: OnlineMember[]) => {
                onlineMembers.value = members;
                connected.value = true;
            })
            .joining((member: OnlineMember) => {
                if (!onlineMembers.value.some((m) => m.id === member.id)) {
                    onlineMembers.value = [...onlineMembers.value, member];
                }
            })
            .leaving((member: OnlineMember) => {
                onlineMembers.value = onlineMembers.value.filter(
                    (m) => m.id !== member.id,
                );
            })
            .error((err: unknown) => {
                console.warn('[Reflow] 实时频道错误：', err);
                connected.value = false;
            });

        presence.listen('.board.updated', onUpdated);
    }

    // 用 immediate watch 替代 onMounted：
    //  - immediate:true 让首次进入（含组件被 Inertia 复用时 props 已就绪）也立即订阅
    //  - 复用实例切看板时，getter 返回的 slug 变化会自动触发退订旧频道 + 订阅新频道
    watch(
        getSlug,
        (next) => {
            subscribe(next);
        },
        { immediate: true },
    );

    // 真实连接状态变化时：
    //  - 重连成功 → 强制重新订阅当前频道，让 here 重发、成员列表恢复
    //    （否则断线后即使 socket 恢复，成员列表也会卡在空状态，表现为「时有时无」）
    //  - 断开 → 关闭「已连接」绿灯（成员列表保留，重连后 here 会刷新，避免闪空）
    watch(echoState, (s) => {
        if (s === 'connected') {
            if (currentSlug) {
                subscribe(currentSlug);
            }
        } else {
            connected.value = false;
        }
    });

    onBeforeUnmount(() => {
        unsubscribe();
        if (reloadTimer) {
            clearTimeout(reloadTimer);
        }
    });

    return { onlineMembers, connected };
}
