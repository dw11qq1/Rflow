<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, RotateCcw, Trash2, Calendar, User as UserIcon, MessageSquare, Users } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { trash } from '@/routes';

const { t } = useI18n();

interface Member {
    id: number;
    name: string;
    pivot: { role: string };
}

interface Card {
    id: number;
    title: string;
    description: string | null;
    due_date: string | null;
    assignee: { id: number; name: string } | null;
    comments: { id: number }[];
}

interface Column {
    id: number;
    name: string;
    cards: Card[];
}

interface Board {
    id: number;
    name: string;
    slug: string;
    columns: Column[];
    members: Member[];
}

const props = defineProps<{ board: Board; canManage: boolean }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'trash.breadcrumb', href: trash().url }],
    },
});

function formatDate(d: string | null): string {
    return d ? d.slice(0, 10) : '';
}
</script>

<template>
    <Head :title="`${t('trash.detail.title')} · ${board.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- 头部 -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <Link
                    :href="trash().url"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-4" /> {{ t('trash.detail.back') }}
                </Link>
                <h1 class="text-2xl font-semibold tracking-tight">{{ board.name }}</h1>
                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                    {{ t('trash.detail.inTrash') }}
                </span>
            </div>

            <div v-if="canManage" class="flex items-center gap-2">
                <Link :href="`/boards/${board.slug}/restore`" method="post" as="button">
                    <Button variant="outline" size="sm">
                        <RotateCcw class="mr-1 size-4" /> {{ t('trash.detail.restore') }}
                    </Button>
                </Link>
                <Link
                    :href="`/boards/${board.slug}/force`"
                    method="delete"
                    as="button"
                    :only="[]"
                    @click="(e) => { if (!confirm(t('trash.detail.confirmForce'))) e.preventDefault(); }"
                >
                    <Button variant="destructive" size="sm">
                        <Trash2 class="mr-1 size-4" /> {{ t('trash.detail.force') }}
                    </Button>
                </Link>
            </div>
        </div>

        <!-- 列与卡片（只读） -->
        <div v-if="board.columns.length" class="flex flex-1 gap-4 overflow-x-auto pb-4">
            <div
                v-for="column in board.columns"
                :key="column.id"
                class="flex w-72 shrink-0 flex-col rounded-xl border border-sidebar-border/70 bg-sidebar/30"
            >
                <div class="flex items-center justify-between gap-2 px-3 py-2">
                    <h2 class="text-sm font-semibold">{{ column.name }}</h2>
                    <span class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                        {{ column.cards.length }}
                    </span>
                </div>
                <div class="flex flex-1 flex-col gap-2 overflow-y-auto px-3 pb-2">
                    <div
                        v-for="card in column.cards"
                        :key="card.id"
                        class="rounded-lg border border-sidebar-border bg-card p-3 text-sm shadow-sm"
                    >
                        <p class="font-medium leading-snug">{{ card.title }}</p>
                        <div v-if="card.description" class="mt-1 line-clamp-2 text-xs text-muted-foreground">
                            {{ card.description }}
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <span v-if="card.due_date" class="inline-flex items-center gap-1">
                                <Calendar class="size-3.5" /> {{ formatDate(card.due_date) }}
                            </span>
                            <span v-if="card.assignee" class="inline-flex items-center gap-1">
                                <UserIcon class="size-3.5" /> {{ card.assignee.name }}
                            </span>
                            <span v-if="card.comments.length" class="inline-flex items-center gap-1">
                                <MessageSquare class="size-3.5" /> {{ card.comments.length }}
                            </span>
                        </div>
                    </div>
                    <p v-if="!column.cards.length" class="px-1 py-2 text-xs text-muted-foreground/70">
                        {{ t('trash.detail.noColumns') }}
                    </p>
                </div>
            </div>
        </div>
        <p v-else class="text-sm text-muted-foreground">{{ t('trash.detail.noColumns') }}</p>

        <!-- 成员 -->
        <div v-if="board.members.length" class="rounded-xl border border-sidebar-border bg-card p-4">
            <div class="mb-3 flex items-center gap-2 text-sm font-medium">
                <Users class="size-4" /> {{ t('board.members') }}
            </div>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="member in board.members"
                    :key="member.id"
                    class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-xs"
                >
                    {{ member.name }}
                    <span class="text-muted-foreground">· {{ t('members.role.' + member.pivot.role) }}</span>
                </span>
            </div>
        </div>
    </div>
</template>
