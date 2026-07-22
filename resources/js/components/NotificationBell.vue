<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Bell, CheckCheck, CircleDot } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const { t } = useI18n();
const page = usePage();

interface NotifItem {
    id: string;
    kind: string;
    data: {
        by?: string;
        board_name?: string;
        card_title?: string;
        excerpt?: string;
        [k: string]: unknown;
    };
    created_at: string;
}

const notifications = computed<NotifItem[]>(
    () => ((page.props.notifications as { items?: NotifItem[] })?.items ?? []) as NotifItem[],
);
const unreadCount = computed(
    () => (page.props.notifications as { unread_count?: number })?.unread_count ?? 0,
);

function markAllRead() {
    router.post(
        '/notifications/read-all',
        {},
        { preserveScroll: true },
    );
}

function open(notif: NotifItem) {
    const slug = notif.data?.board_slug as string | undefined;
    // 标记已读后跳转到对应看板
    router.post(
        `/notifications/${notif.id}/read`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                if (slug) {
                    router.visit(`/boards/${slug}`);
                } else {
                    router.reload({ only: ['notifications'], preserveScroll: true });
                }
            },
        },
    );
}

function timeAgo(createdAt: string): string {
    const diff = Math.floor((Date.now() - new Date(createdAt).getTime()) / 1000);
    if (diff < 60) return t('notifications.justNow');
    if (diff < 3600) return `${Math.floor(diff / 60)}${t('notifications.min')}`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}${t('notifications.hour')}`;
    return `${Math.floor(diff / 86400)}${t('notifications.day')}`;
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button
                variant="ghost"
                size="icon"
                class="group relative h-9 w-9 cursor-pointer"
                :aria-label="t('notifications.title')"
            >
                <Bell class="size-5 opacity-80 group-hover:opacity-100" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute -right-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold leading-4 text-white"
                >
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80 p-0">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <span class="text-sm font-semibold">{{ t('notifications.title') }}</span>
                <button
                    v-if="unreadCount > 0"
                    class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                    @click="markAllRead"
                >
                    <CheckCheck class="size-3.5" /> {{ t('notifications.markAll') }}
                </button>
            </div>

            <div class="max-h-96 overflow-y-auto">
                <p
                    v-if="notifications.length === 0"
                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    {{ t('notifications.empty') }}
                </p>

                <button
                    v-for="n in notifications"
                    :key="n.id"
                    class="flex w-full items-start gap-2 border-b px-4 py-3 text-left text-sm transition hover:bg-accent"
                    @click="open(n)"
                >
                    <CircleDot
                        class="mt-1 size-3.5 shrink-0 text-primary"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="leading-snug text-foreground">
                            <template v-if="n.kind === 'card.assigned'">
                                <b>{{ n.data.by }}</b> {{ t('notifications.assignedYou') }}
                                <b>“{{ n.data.card_title }}”</b>
                                {{ t('notifications.inBoard') }} <b>{{ n.data.board_name }}</b>
                            </template>
                            <template v-else-if="n.kind === 'comment.added'">
                                <b>{{ n.data.by }}</b> {{ t('notifications.commentedOn') }}
                                <b>“{{ n.data.card_title }}”</b>
                                {{ t('notifications.inBoard') }} <b>{{ n.data.board_name }}</b>
                            </template>
                            <template v-else-if="n.kind === 'comment.mentioned'">
                                <b>{{ n.data.by }}</b> {{ t('notifications.mentionedYou') }}
                                {{ t('notifications.inBoard') }} <b>{{ n.data.board_name }}</b>
                            </template>
                            <template v-else-if="n.kind === 'board.member_added'">
                                <b>{{ n.data.by }}</b> {{ t('notifications.addedYouTo') }}
                                <b>{{ n.data.board_name }}</b>
                            </template>
                            <template v-else>{{ n.kind }}</template>
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ timeAgo(n.created_at) }}</p>
                    </div>
                </button>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
