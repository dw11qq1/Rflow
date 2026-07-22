<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { LayoutTemplate, Plus, ArrowRight } from '@lucide/vue';
import { templates } from '@/routes';
import { store as boardsStore } from '@/routes/boards';
import { Button } from '@/components/ui/button';

const { t } = useI18n();

interface Template {
    key: string;
    name: string;
    description: string;
    columns: string[];
}

defineProps<{ templates: Template[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'templates.breadcrumb', href: templates().url }],
    },
});

function useTemplate(tpl: Template): void {
    router.post(boardsStore().url, { name: tpl.name, columns: tpl.columns });
}
</script>

<template>
    <Head :title="t('templates.title')" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ t('templates.title') }}</h1>
            <p class="text-sm text-muted-foreground">{{ t('templates.description') }}</p>
        </div>

        <div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="tpl in templates"
                :key="tpl.key"
                class="flex flex-col rounded-xl border border-sidebar-border bg-card p-5 shadow-sm"
            >
                <div class="flex items-center gap-2">
                    <LayoutTemplate class="size-5 text-primary" />
                    <h3 class="font-semibold">{{ tpl.name }}</h3>
                </div>
                <p class="mt-2 flex-1 text-sm text-muted-foreground">{{ tpl.description }}</p>

                <div class="mt-4 flex flex-wrap gap-1.5">
                    <span
                        v-for="col in tpl.columns"
                        :key="col"
                        class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                        >{{ col }}</span
                    >
                </div>

                <Button class="mt-4 w-full" @click="useTemplate(tpl)">
                    <Plus class="mr-1 size-4" /> {{ t('templates.use') }}
                </Button>
            </div>
        </div>

        <p class="flex items-center gap-1 text-xs text-muted-foreground">
            {{ t('templates.hint') }} <ArrowRight class="size-3" />
        </p>
    </div>
</template>
