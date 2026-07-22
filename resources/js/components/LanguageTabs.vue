<script setup lang="ts">
import { Languages } from '@lucide/vue';
import { useLocale } from '@/composables/useLocale';
import type { Locale } from '@/types';

const { locale, setLocale } = useLocale();

const tabs = [
    { value: 'zh', label: '简体中文' },
    { value: 'en', label: 'English' },
] as const;

function choose(value: Locale) {
    if (locale.value === value) {
        return;
    }
    setLocale(value);
}
</script>

<template>
    <div
        class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800"
    >
        <button
            v-for="{ value, label } in tabs"
            :key="value"
            @click="choose(value)"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                locale === value
                    ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
            ]"
        >
            <Languages class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
