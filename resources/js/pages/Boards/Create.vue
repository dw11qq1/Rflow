<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { store } from '@/routes/boards';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import Heading from '@/components/Heading.vue';

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'boards.breadcrumb', href: '/boards' },
            { title: 'boards.create.breadcrumb', href: '/boards/create' },
        ],
    },
});

const form = useForm({
    name: '',
});

function submit() {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="t('boards.create.head')" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading :title="t('boards.create.title')" :description="t('boards.create.description')" />

        <form @submit.prevent="submit" class="max-w-md space-y-6">
            <div class="grid gap-2">
                <Label for="name">{{ t('boards.create.name') }}</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    :placeholder="t('boards.create.namePlaceholder')"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="form.processing" type="submit">{{ t('boards.create.submit') }}</Button>
            </div>
        </form>
    </div>
</template>
