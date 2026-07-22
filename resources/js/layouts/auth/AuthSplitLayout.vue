<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useI18n } from 'vue-i18n';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const { t, te } = useI18n();

// title/description 可能是 i18n 键，也可能是普通文案；键存在则翻译。
const tr = (value?: string) => (value && te(value) ? t(value) : value);
</script>

<template>
    <div class="grid min-h-dvh lg:grid-cols-[1.05fr_1fr]">
        <!-- 左：品牌叙事（编辑式） -->
        <aside
            class="relative hidden flex-col justify-between border-r border-border bg-muted p-10 lg:flex"
        >
            <Link
                :href="home()"
                class="flex items-center gap-2 text-base font-semibold text-foreground"
            >
                <AppLogoIcon class="size-7" />
                Reflow
            </Link>

            <div class="max-w-md">
                <p
                    class="font-mono text-xs uppercase tracking-[0.22em] text-sky-500"
                >
                    {{ t('auth.brand.eyebrow') }}
                </p>
                <h2
                    class="mt-4 text-3xl font-semibold leading-tight tracking-tight text-foreground"
                >
                    {{ t('auth.brand.headline') }}
                </h2>
                <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                    {{ t('auth.brand.sub') }}
                </p>
            </div>

            <p class="text-xs text-muted-foreground">
                {{ t('auth.brand.copyright') }}
            </p>
        </aside>

        <!-- 右：表单（左对齐、留白） -->
        <main
            class="flex items-center justify-center bg-background px-6 py-12 lg:px-14"
        >
            <div class="w-full max-w-sm">
                <Link
                    :href="home()"
                    class="mb-8 flex items-center gap-2 text-base font-semibold text-foreground lg:hidden"
                >
                    <AppLogoIcon class="size-7" />
                    Reflow
                </Link>

                <div class="mb-8">
                    <h1
                        v-if="title"
                        class="text-2xl font-semibold tracking-tight text-foreground"
                    >
                        {{ tr(title) }}
                    </h1>
                    <p
                        v-if="description"
                        class="mt-1.5 text-sm text-muted-foreground"
                    >
                        {{ tr(description) }}
                    </p>
                </div>

                <slot />
            </div>
        </main>
    </div>
</template>
