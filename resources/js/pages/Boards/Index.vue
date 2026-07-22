<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Plus, Users, Layers, FileText } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { index as boardsIndex, create as boardsCreate, show as boardsShow } from '@/routes/boards';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import type { Auth } from '@/types';

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

defineProps<{ boards: Board[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'boards.breadcrumb', href: boardsIndex().url }],
    },
});

const page = usePage();
const auth = page.props.auth as Auth;

function initials(name: string): string {
    return name.slice(0, 1).toUpperCase();
}
</script>

<template>
    <Head :title="t('boards.head')" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ t('boards.title') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('boards.subtitle') }}</p>
            </div>
            <Link :href="boardsCreate().url">
                <Button>
                    <Plus class="mr-1 size-4" /> {{ t('boards.newBoard') }}
                </Button>
            </Link>
        </div>

        <div v-if="boards.length === 0" class="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
            {{ t('boards.empty') }}
        </div>

        <div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="board in boards"
                :key="board.id"
                :href="boardsShow(board.slug).url"
                class="group rounded-xl border border-sidebar-border bg-card p-5 shadow-sm transition hover:border-primary/50 hover:shadow-md"
            >
                <div class="flex items-start justify-between gap-2">
                    <h2 class="font-semibold leading-tight group-hover:text-primary">{{ board.name }}</h2>
                    <span
                        v-if="board.is_archived"
                        class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                        >{{ t('boards.archived') }}</span
                    >
                </div>

                <div class="mt-4 flex items-center gap-4 text-sm text-muted-foreground">
                    <span class="inline-flex items-center gap-1">
                        <Layers class="size-4" /> {{ board.columns_count }} {{ t('boards.columns') }}
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <FileText class="size-4" /> {{ board.cards_count }} {{ t('boards.cards') }}
                    </span>
                </div>

                <div class="mt-4 flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 text-xs text-muted-foreground">
                        <Users class="size-3.5" /> {{ t('boards.members') }}
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
    </div>
</template>
