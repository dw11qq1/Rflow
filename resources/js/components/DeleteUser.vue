<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const { t } = useI18n();

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <section class="space-y-4 border-t border-border pt-8">
        <Heading
            variant="small"
            :title="t('settings.deleteUser.title')"
            :description="t('settings.deleteUser.description')"
        />
        <div class="space-y-0.5 text-sm">
            <p class="font-medium text-foreground">
                {{ t('settings.deleteUser.warning') }}
            </p>
            <p class="text-muted-foreground">
                {{ t('settings.deleteUser.caution') }}
            </p>
        </div>
        <Dialog>
            <DialogTrigger as-child>
                <Button variant="destructive" data-test="delete-user-button">{{
                    t('settings.deleteUser.button')
                }}</Button>
            </DialogTrigger>
            <DialogContent>
                <Form
                    v-bind="ProfileController.destroy.form()"
                    reset-on-success
                    @error="() => passwordInput?.focus()"
                    :options="{
                        preserveScroll: true,
                    }"
                    class="space-y-6"
                    v-slot="{ errors, processing, reset, clearErrors }"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle>{{
                            t('settings.deleteUser.dialogTitle')
                        }}</DialogTitle>
                        <DialogDescription>
                            {{ t('settings.deleteUser.dialogDescription') }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="password" class="sr-only">{{
                            t('auth.passwordPlaceholder')
                        }}</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            ref="passwordInput"
                            :placeholder="t('settings.deleteUser.passwordPlaceholder')"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button
                                variant="secondary"
                                @click="
                                    () => {
                                        clearErrors();
                                        reset();
                                    }
                                "
                            >
                                {{ t('settings.deleteUser.cancel') }}
                            </Button>
                        </DialogClose>

                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                            data-test="confirm-delete-user-button"
                        >
                            {{ t('settings.deleteUser.button') }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </section>
</template>
