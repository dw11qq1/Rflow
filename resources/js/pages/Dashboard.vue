<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { LayoutGrid, Layers, FileText, Users, Plus, ArrowRight, BarChart3, Activity as ActivityIcon } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { index as boardsIndex, create as boardsCreate, show as boardsShow, retro as boardsRetro } from '@/routes/boards';
import { dashboard } from '@/routes';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';

const { t } = useI18n();

interface Member {
    id: number;
    name: string;
}
interface Board {
    id: number;
    name: string;
    slug: string;
    is_archived: boolean;
    cards_count: number;
    columns_count: number;
    members: Member[];
}
interface Stats {
    boards: number;
    cards: number;
    members: number;
}
interface RetroItem {
    slug: string;
    name: string;
    total: number;
    trend: { date: string; total: number }[];
}
interface ActivityItem {
    id: number;
    type: string;
    user: { id: number; name: string } | null;
    payload: { title?: string } | null;
    created_at: string;
    board: { id: number; slug: string; name: string } | null;
}

defineProps<{ boards: Board[]; stats: Stats; retro: RetroItem[]; activities: ActivityItem[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'dashboard.breadcrumb', href: dashboard().url }],
    },
});

function initials(name: string): string {
    return name.slice(0, 1).toUpperCase();
}

function typeLabel(type: string): string {
    const key = `retro.types.${type}`;
    return t(key) === key ? type : t(key);
}

function activityTitle(a: ActivityItem): string {
    return a.payload?.title ?? '';
}

function formatDateTime(d: string): string {
    return d ? d.replace('T', ' ').slice(0, 16) : '';
}

// 迷你趋势（SVG 折线）
function sparkPoints(trend: { total: number }[]): string {
    if (trend.length === 0) return '';
    const max = Math.max(1, ...trend.map((p) => p.total));
    const w = 100;
    const h = 30;
    return trend
        .map((p, i) => {
            const x = trend.length > 1 ? (i / (trend.length - 1)) * w : w / 2;
            const y = h - (p.total / max) * (h - 4) - 2;
            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
}
</script>

<template>
    <Head :title="t('dashboard.head')" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ t('dashboard.title') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('dashboard.subtitle') }}</p>
            </div>
            <Link :href="boardsCreate().url">
                <Button>
                    <Plus class="mr-1 size-4" /> {{ t('dashboard.newBoard') }}
                </Button>
            </Link>
        </div>

        <!-- 统计卡 -->
        <div class="grid auto-rows-min gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-sidebar-border bg-card p-5 shadow-sm">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <LayoutGrid class="size-4" /> {{ t('dashboard.stats.boards') }}
                </div>
                <p class="mt-2 text-3xl font-semibold">{{ stats.boards }}</p>
            </div>
            <div class="rounded-xl border border-sidebar-border bg-card p-5 shadow-sm">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <FileText class="size-4" /> {{ t('dashboard.stats.cards') }}
                </div>
                <p class="mt-2 text-3xl font-semibold">{{ stats.cards }}</p>
            </div>
            <div class="rounded-xl border border-sidebar-border bg-card p-5 shadow-sm">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <Users class="size-4" /> {{ t('dashboard.stats.members') }}
                </div>
                <p class="mt-2 text-3xl font-semibold">{{ stats.members }}</p>
            </div>
        </div>

        <!-- 最近看板 -->
        <section>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold">{{ t('dashboard.recent') }}</h2>
                <Link
                    :href="boardsIndex().url"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground transition hover:text-primary"
                >
                    {{ t('dashboard.viewAll') }} <ArrowRight class="size-3.5" />
                </Link>
            </div>

            <div
                v-if="boards.length === 0"
                class="rounded-xl border border-dashed p-12 text-center text-muted-foreground"
            >
                {{ t('dashboard.empty') }}
            </div>

            <div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="board in boards"
                    :key="board.id"
                    :href="boardsShow(board.slug).url"
                    class="group rounded-xl border border-sidebar-border bg-card p-5 shadow-sm transition hover:border-primary/50 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-semibold leading-tight group-hover:text-primary">{{ board.name }}</h3>
                        <span
                            v-if="board.is_archived"
                            class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                            >{{ t('dashboard.archived') }}</span
                        >
                    </div>

                    <div class="mt-4 flex items-center gap-4 text-sm text-muted-foreground">
                        <span class="inline-flex items-center gap-1">
                            <Layers class="size-4" /> {{ board.columns_count }} {{ t('dashboard.columns') }}
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <FileText class="size-4" /> {{ board.cards_count }} {{ t('dashboard.cards') }}
                        </span>
                    </div>

                    <div class="mt-4 flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 text-xs text-muted-foreground">
                            <Users class="size-3.5" /> {{ t('dashboard.members') }}
                        </span>
                        <div class="flex -space-x-2">
                            <Avatar
                                v-for="m in board.members.slice(0, 4)"
                                :key="m.id"
                                class="size-6 border-2 border-card"
                            >
                                <AvatarFallback class="text-[10px]">{{ initials(m.name) }}</AvatarFallback>
                            </Avatar>
                            <span
                                v-if="board.members.length > 4"
                                class="ml-1 inline-flex size-6 items-center justify-center rounded-full bg-muted text-[10px] text-muted-foreground"
                                >+{{ board.members.length - 4 }}</span
                            >
                        </div>
                    </div>
                </Link>
            </div>
        </section>

        <!-- 复盘概览 -->
        <section>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <BarChart3 class="size-4" /> {{ t('dashboard.retroTitle') }}
                </h2>
            </div>

            <div
                v-if="retro.length === 0"
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                {{ t('dashboard.retroEmpty') }}
            </div>

            <div v-else class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="item in retro"
                    :key="item.slug"
                    :href="boardsRetro(item.slug).url"
                    class="group rounded-xl border border-sidebar-border bg-card p-4 shadow-sm transition hover:border-primary/50 hover:shadow-md"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="truncate font-semibold leading-tight group-hover:text-primary">{{ item.name }}</h3>
                        <span class="shrink-0 text-xs text-muted-foreground">{{ item.total }} {{ t('dashboard.cards') }}</span>
                    </div>

                    <div class="mt-3 h-[30px] w-full">
                        <svg
                            v-if="item.trend.length"
                            viewBox="0 0 100 30"
                            preserveAspectRatio="none"
                            class="h-full w-full"
                        >
                            <polyline
                                :points="sparkPoints(item.trend)"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                class="text-primary"
                                vector-effect="non-scaling-stroke"
                            />
                        </svg>
                        <div v-else class="flex h-full items-end">
                            <div class="h-1.5 w-full rounded-full bg-muted"></div>
                        </div>
                    </div>

                    <p class="mt-2 text-xs text-muted-foreground">{{ t('dashboard.viewRetro') }} →</p>
                </Link>
            </div>
        </section>

        <!-- 全局动态 -->
        <section>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <ActivityIcon class="size-4" /> {{ t('dashboard.activityTitle') }}
                </h2>
            </div>

            <div
                v-if="activities.length === 0"
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                {{ t('dashboard.activityEmpty') }}
            </div>

            <ol v-else class="space-y-3 rounded-xl border border-sidebar-border bg-card p-4 shadow-sm">
                <li v-for="a in activities" :key="a.id" class="flex items-start gap-3 text-sm">
                    <span class="mt-0.5 inline-flex size-2 shrink-0 rounded-full bg-primary/70"></span>
                    <div class="min-w-0">
                        <p class="truncate">
                            <span class="font-medium">{{ a.user?.name ?? t('retro.system') }}</span>
                            <span class="text-muted-foreground"> · {{ typeLabel(a.type) }}</span>
                            <span v-if="activityTitle(a)" class="font-medium"> 「{{ activityTitle(a) }}」</span>
                            <span v-if="a.board" class="text-muted-foreground">
                                · <Link :href="boardsShow(a.board.slug).url" class="hover:text-primary hover:underline">{{ a.board.name }}</Link>
                            </span>
                        </p>
                        <p class="text-xs text-muted-foreground">{{ formatDateTime(a.created_at) }}</p>
                    </div>
                </li>
            </ol>
        </section>
    </div>
</template>
