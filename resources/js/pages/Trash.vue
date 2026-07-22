<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Trash2, RotateCcw, FileText, Filter, LayoutGrid, Columns3, Square } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { restore as boardsRestore, force as boardsForce } from '@/routes/boards';
import { trash } from '@/routes';
import { Button } from '@/components/ui/button';

const { t } = useI18n();

type TrashType = 'board' | 'column' | 'card';

interface TrashItem {
    type: TrashType;
    id: number;
    name: string;
    slug?: string;
    deleted_at: string;
    cards_count?: number;
    board_id?: number;
    board_name?: string | null;
    column_name?: string | null;
}

const props = defineProps<{ items: TrashItem[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'trash.breadcrumb', href: trash().url }],
    },
});

const filters: { value: 'all' | TrashType; label: string }[] = [
    { value: 'all', label: t('trash.filterAll') },
    { value: 'board', label: t('trash.types.board') },
    { value: 'column', label: t('trash.types.column') },
    { value: 'card', label: t('trash.types.card') },
];

const filter = ref<'all' | TrashType>('all');

const filtered = computed(() =>
    filter.value === 'all' ? props.items : props.items.filter((i) => i.type === filter.value),
);

const typeBadge: Record<TrashType, { class: string; icon: unknown }> = {
    board: { class: 'bg-blue-100 text-blue-700', icon: LayoutGrid },
    column: { class: 'bg-violet-100 text-violet-700', icon: Columns3 },
    card: { class: 'bg-amber-100 text-amber-700', icon: Square },
};

function formatDate(d: string): string {
    return d ? d.slice(0, 10) : '';
}

function restoreUrl(item: TrashItem): string {
    return item.type === 'board'
        ? boardsRestore(item.slug as string).url
        : `/trash/${item.type}/${item.id}/restore`;
}

function forceUrl(item: TrashItem): string {
    return item.type === 'board'
        ? boardsForce(item.slug as string).url
        : `/trash/${item.type}/${item.id}/force`;
}
</script>

<template>
    <Head :title="t('trash.title')" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ t('trash.title') }}</h1>
            <p class="text-sm text-muted-foreground">{{ t('trash.description') }}</p>
        </div>

        <!-- 类型筛选 -->
        <div v-if="items.length > 0" class="flex flex-wrap items-center gap-2">
            <span class="mr-1 inline-flex items-center gap-1 text-xs text-muted-foreground">
                <Filter class="size-3.5" /> {{ t('trash.filterLabel') }}
            </span>
            <button
                v-for="f in filters"
                :key="f.value"
                type="button"
                class="rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                :class="
                    filter === f.value
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-sidebar-border bg-card text-muted-foreground hover:bg-accent'
                "
                @click="filter = f.value"
            >
                {{ f.label }}
            </button>
        </div>

        <div
            v-if="items.length === 0"
            class="rounded-xl border border-dashed p-12 text-center text-muted-foreground"
        >
            <Trash2 class="mx-auto mb-3 size-8 opacity-50" />
            {{ t('trash.empty') }}
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="item in filtered"
                :key="item.type + '-' + item.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sidebar-border bg-card p-4 shadow-sm"
            >
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium"
                            :class="typeBadge[item.type].class"
                        >
                            <component :is="typeBadge[item.type].icon" class="size-3" />
                            {{ t('trash.types.' + item.type) }}
                        </span>
                        <Link
                            v-if="item.type === 'board'"
                            :href="`/trash/boards/${item.slug}`"
                            class="truncate font-semibold text-primary hover:underline"
                        >
                            {{ item.name }}
                        </Link>
                        <h3 v-else class="truncate font-semibold">{{ item.name }}</h3>
                    </div>
                    <p class="mt-1 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                        <span class="inline-flex items-center gap-1">
                            <FileText class="size-3.5" /> {{ item.cards_count ?? 0 }}
                            {{ t('trash.cards') }}
                        </span>
                        <span v-if="item.board_name">{{ t('trash.fromBoard') }}：{{ item.board_name }}</span>
                        <span v-if="item.column_name">{{ t('trash.inColumn') }}：{{ item.column_name }}</span>
                        <span>{{ t('trash.deletedAt') }} {{ formatDate(item.deleted_at) }}</span>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link :href="restoreUrl(item)" method="post" as="button">
                        <Button variant="outline" size="sm">
                            <RotateCcw class="mr-1 size-4" /> {{ t('trash.restore') }}
                        </Button>
                    </Link>
                    <Link
                        :href="forceUrl(item)"
                        method="delete"
                        as="button"
                        :only="[]"
                        @click="(e) => { if (!confirm(t('trash.confirmForce'))) e.preventDefault(); }"
                    >
                        <Button variant="destructive" size="sm">
                            <Trash2 class="mr-1 size-4" /> {{ t('trash.force') }}
                        </Button>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
