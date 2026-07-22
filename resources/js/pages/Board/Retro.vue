<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ArrowLeft, BarChart3, ListTodo, CheckCircle2, Loader } from '@lucide/vue';
import { index as boardsIndex, show as boardsShow } from '@/routes/boards';
import { Button } from '@/components/ui/button';

interface Board {
    id: number;
    name: string;
    slug: string;
}
interface Metrics {
    total: number;
    done: number;
    wip: number;
}
interface PerColumn {
    name: string;
    count: number;
}
interface Activity {
    id: number;
    type: string;
    user: { id: number; name: string } | null;
    payload: Record<string, unknown> | null;
    created_at: string;
}
interface Snapshot {
    date: string;
    snapshot: { total: number; per_column: Record<string, number> };
}

const props = defineProps<{
    board: Board;
    metrics: Metrics;
    perColumn: PerColumn[];
    activities: Activity[];
    snapshots: Snapshot[];
}>();

const { t } = useI18n();

defineOptions({
    layout: (page: any) => ({
        breadcrumbs: [
            { title: 'retro.breadcrumbBoards', href: boardsIndex().url },
            { title: page.board.name, href: boardsShow(page.board.slug).url },
            { title: 'retro.breadcrumbRetro', href: boardsShow(page.board.slug).url + '/retro' },
        ],
    }),
});

function typeLabel(type: string): string {
    const key = `retro.types.${type}`;
    return t(key) === key ? type : t(key);
}

function activityTitle(a: Activity): string {
    const p = a.payload as { title?: string } | null;
    return p?.title ?? '';
}

// 最新快照各列分布（堆叠条）
const latestSnapshot = computed(() => props.snapshots.at(-1) ?? null);
const latestPerColumn = computed(() => {
    const snap = latestSnapshot.value;
    if (!snap) return props.perColumn;
    return Object.entries(snap.snapshot.per_column).map(([name, count]) => ({ name, count: count as number }));
});
const maxColumnCount = computed(() =>
    Math.max(1, ...latestPerColumn.value.map((c) => c.count)),
);
const barColors = ['bg-sky-500', 'bg-amber-500', 'bg-emerald-500', 'bg-violet-500', 'bg-rose-500'];

// 快照趋势（按日期的总卡片数柱状）
const trend = computed(() =>
    [...props.snapshots]
        .sort((a, b) => a.date.localeCompare(b.date))
        .map((s) => ({ date: s.date.slice(5), total: s.snapshot.total })),
);
const maxTrend = computed(() => Math.max(1, ...trend.value.map((t) => t.total)));

function formatDateTime(d: string): string {
    return d ? d.replace('T', ' ').slice(0, 16) : '';
}
</script>

<template>
    <Head :title="`${board.name} · ${t('retro.headSuffix')}`" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-y-auto p-4 md:p-6">
        <!-- 头部 -->
        <header class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <Link :href="boardsShow(board.slug).url">
                    <Button variant="ghost" size="icon" class="size-8">
                        <ArrowLeft class="size-4" />
                    </Button>
                </Link>
                <div>
                    <h1 class="text-lg font-semibold">{{ board.name }} · {{ t('retro.titleSuffix') }}</h1>
                    <p class="text-sm text-muted-foreground">{{ t('retro.subtitle') }}</p>
                </div>
            </div>
            <Link :href="boardsShow(board.slug).url">
                <Button variant="outline">
                    <ListTodo class="mr-1 size-4" /> {{ t('retro.backToBoard') }}
                </Button>
            </Link>
        </header>

        <!-- 指标卡 -->
        <div class="grid auto-rows-min gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-sidebar-border bg-card p-4 shadow-sm">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <ListTodo class="size-4" /> {{ t('retro.total') }}
                </div>
                <p class="mt-2 text-3xl font-semibold">{{ metrics.total }}</p>
            </div>
            <div class="rounded-xl border border-sidebar-border bg-card p-4 shadow-sm">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <CheckCircle2 class="size-4" /> {{ t('retro.done') }}
                </div>
                <p class="mt-2 text-3xl font-semibold text-emerald-600">{{ metrics.done }}</p>
            </div>
            <div class="rounded-xl border border-sidebar-border bg-card p-4 shadow-sm">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <Loader class="size-4" /> {{ t('retro.wip') }}
                </div>
                <p class="mt-2 text-3xl font-semibold text-amber-600">{{ metrics.wip }}</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- 各列分布 -->
            <section class="rounded-xl border border-sidebar-border bg-card p-4 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold">
                    <BarChart3 class="size-4" /> {{ t('retro.perColumn') }}
                </h2>
                <div class="space-y-3">
                    <div v-for="(col, i) in latestPerColumn" :key="col.name">
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span>{{ col.name }}</span>
                            <span class="text-muted-foreground">{{ col.count }}</span>
                        </div>
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="barColors[i % barColors.length]"
                                :style="{ width: (col.count / maxColumnCount) * 100 + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 快照趋势 -->
            <section class="rounded-xl border border-sidebar-border bg-card p-4 shadow-sm">
                <h2 class="mb-1 text-sm font-semibold">{{ t('retro.trendTitle') }}</h2>
                <p class="mb-4 text-xs text-muted-foreground">{{ t('retro.trendDesc') }}</p>
                <div v-if="trend.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                    {{ t('retro.noData') }}
                </div>
                <div v-else-if="trend.length === 1" class="flex h-40 flex-col items-center justify-center gap-2 text-center">
                    <div class="flex items-end gap-1">
                        <span class="text-3xl font-semibold">{{ trend[0].total }}</span>
                        <span class="mb-1 text-sm text-muted-foreground">{{ t('retro.cardsUnit') }}</span>
                    </div>
                    <p class="max-w-xs text-xs text-muted-foreground">
                        {{ t('retro.singleDay', { date: trend[0].date }) }}
                    </p>
                </div>
                <div v-else class="flex h-40 items-end gap-3">
                    <div v-for="t in trend" :key="t.date" class="flex flex-1 flex-col items-center justify-end gap-1">
                        <span class="text-xs font-medium">{{ t.total }}</span>
                        <div
                            class="w-full rounded-t bg-primary/80"
                            :style="{ height: (t.total / maxTrend) * 100 + '%' }"
                        ></div>
                        <span class="text-[11px] text-muted-foreground">{{ t.date }}</span>
                    </div>
                </div>
            </section>
        </div>

        <!-- 活动流 -->
        <section class="rounded-xl border border-sidebar-border bg-card p-4 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold">{{ t('retro.activityStream') }}</h2>
            <ol v-if="activities.length" class="space-y-3">
                <li v-for="a in activities" :key="a.id" class="flex items-start gap-3 text-sm">
                    <span class="mt-0.5 inline-flex size-2 shrink-0 rounded-full bg-primary/70"></span>
                    <div>
                        <p>
                            <span class="font-medium">{{ a.user?.name ?? t('retro.system') }}</span>
                            <span class="text-muted-foreground"> · {{ typeLabel(a.type) }}</span>
                            <span v-if="activityTitle(a)" class="font-medium"> 「{{ activityTitle(a) }}」</span>
                        </p>
                        <p class="text-xs text-muted-foreground">{{ formatDateTime(a.created_at) }}</p>
                    </div>
                </li>
            </ol>
            <p v-else class="py-6 text-center text-sm text-muted-foreground">{{ t('retro.noActivity') }}</p>
        </section>
    </div>
</template>
