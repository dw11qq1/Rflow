<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import LanguageTabs from '@/components/LanguageTabs.vue';
import { edit } from '@/routes/appearance';

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'settings.appearance.breadcrumb',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="t('settings.appearance.head')" />

    <h1 class="sr-only">{{ t('settings.appearance.head') }}</h1>

    <section class="space-y-6 border-t border-border pt-8">
        <Heading
            variant="small"
            :title="t('settings.appearance.title')"
            :description="t('settings.appearance.description')"
        />
        <AppearanceTabs />
    </section>

    <section class="space-y-6 border-t border-border pt-8">
        <Heading
            variant="small"
            :title="t('language.title')"
            :description="t('language.description')"
        />
        <LanguageTabs />
    </section>
</template>
