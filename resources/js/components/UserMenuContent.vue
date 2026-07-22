<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Check, Languages, LogOut, Settings } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { useLocale } from '@/composables/useLocale';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { Locale, User } from '@/types';

type Props = {
    user: User;
};

const { t } = useI18n();
const { locale, setLocale } = useLocale();

const localeOptions = [
    { value: 'zh', labelKey: 'language.zh' },
    { value: 'en', labelKey: 'language.en' },
] as const;

const chooseLocale = (value: Locale) => {
    if (locale.value !== value) {
        setLocale(value);
    }
};

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                {{ t('userMenu.settings') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuLabel
        class="flex items-center gap-2 px-2 py-1.5 text-xs font-normal text-muted-foreground"
    >
        <Languages class="h-4 w-4" />
        {{ t('language.label') }}
    </DropdownMenuLabel>
    <DropdownMenuGroup>
        <DropdownMenuItem
            v-for="option in localeOptions"
            :key="option.value"
            class="cursor-pointer"
            @select="chooseLocale(option.value)"
        >
            <span class="flex-1">{{ t(option.labelKey) }}</span>
            <Check v-if="locale === option.value" class="h-4 w-4" />
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            {{ t('userMenu.logout') }}
        </Link>
    </DropdownMenuItem>
</template>
