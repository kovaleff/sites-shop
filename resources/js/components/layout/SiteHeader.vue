<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Menu } from '@lucide/vue';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useSessionStore } from '@/stores/session';

const route = useRoute();
const router = useRouter();
const session = useSessionStore();

const menuItems = [
    { key: 'catalog', label: 'Каталог' },
    { key: 'how-it-works', label: 'Как это работает' },
    { key: 'pricing', label: 'Тарифы' },
];

/**
 * Логотип лежит в public/, поэтому путь указывается как есть:
 * привязка к атрибуту не даёт Vite пытаться импортировать файл как модуль.
 */
const logoUrl = '/images/logo/sites-shop_logo_128.png';

const isAuthenticated = computed(() => session.isAuthenticated);
const user = computed(() => session.user);

const initials = computed(() => {
    const name = user.value?.displayName?.trim() || 'П';

    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
});

const unreadLabel = computed(() => {
    const count = session.unreadMessages ?? 0;

    if (count <= 0) {
        return null;
    }

    return count > 99 ? '99+' : String(count);
});

/**
 * @param {{ key: string }} item
 * @returns {import('vue-router').RouteLocationRaw}
 */
function targetFor(item) {
    return {
        name: item.key,
        query: route.meta.navKey ? route.query : undefined,
    };
}

/**
 * @param {string} key
 * @returns {boolean}
 */
function isActive(key) {
    return route.meta.navKey === key;
}

/**
 * @param {'vk' | 'yandex'} provider
 * @returns {Promise<void>}
 */
async function loginWith(provider) {
    const result = provider === 'vk' ? await session.loginWithVk() : await session.loginWithYandex();

    if (!result.ok && result.error) {
        toast.error(result.error);
    }
}

/**
 * @returns {void}
 */
function openCabinet() {
    router.push({ name: 'cabinet-sites' });
}

/**
 * @returns {Promise<void>}
 */
async function logout() {
    await session.logout();
}
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-border bg-background/90 backdrop-blur">
        <div class="mx-auto flex h-16 w-full max-w-[1440px] items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-2">
                <Sheet>
                    <SheetTrigger as-child>
                        <Button variant="ghost" size="icon" class="md:hidden" aria-label="Открыть меню">
                            <Menu class="size-5" />
                        </Button>
                    </SheetTrigger>

                    <SheetContent side="left" class="w-72 bg-background">
                        <SheetHeader>
                            <SheetTitle>Меню</SheetTitle>
                            <SheetDescription class="sr-only">Навигация по сайту</SheetDescription>
                        </SheetHeader>

                        <nav class="flex flex-col gap-1 px-4" aria-label="Мобильное меню">
                            <RouterLink
                                v-for="item in menuItems"
                                :key="item.key"
                                :to="targetFor(item)"
                                class="rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                                :class="isActive(item.key) ? 'bg-primary/15 text-primary' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                            >
                                {{ item.label }}
                            </RouterLink>
                        </nav>
                    </SheetContent>
                </Sheet>

                <RouterLink :to="{ name: 'catalog', query: route.meta.navKey ? route.query : undefined }" class="flex min-w-0 items-center gap-2.5">
                    <img
                        :src="logoUrl"
                        alt="Логотип Sites-shop"
                        width="36"
                        height="36"
                        class="size-9 shrink-0 object-contain"
                    />
                    <span class="min-w-0">
                        <span class="block truncate text-[17px] leading-tight font-bold tracking-tight">
                            Sites-<span class="text-primary">shop</span>
                        </span>
                        <span class="hidden text-[10px] tracking-[0.14em] text-muted-foreground uppercase xl:block">
                            Маркетплейс готовых сайтов
                        </span>
                    </span>
                </RouterLink>
            </div>

            <nav class="hidden items-center gap-1 md:flex" aria-label="Основное меню">
                <RouterLink
                    v-for="item in menuItems"
                    :key="item.key"
                    :to="targetFor(item)"
                    class="rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                    :class="isActive(item.key) ? 'bg-primary/15 text-primary' : 'text-muted-foreground hover:text-foreground'"
                >
                    {{ item.label }}
                </RouterLink>
            </nav>

            <div class="flex shrink-0 items-center gap-2">
                <template v-if="!isAuthenticated">
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 gap-2 border-input bg-secondary/60"
                        :disabled="session.isLoggingIn"
                        aria-label="Войти через VK ID"
                        @click="loginWith('vk')"
                    >
                        <svg class="size-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M0 11.52C0 6.08942 0 3.37413 1.68707 1.68707C3.37413 0 6.08942 0 11.52 0H12.48C17.9106 0 20.6259 0 22.3129 1.68707C24 3.37413 24 6.08942 24 11.52V12.48C24 17.9106 24 20.6259 22.3129 22.3129C20.6259 24 17.9106 24 12.48 24H11.52C6.08942 24 3.37413 24 1.68707 22.3129C0 20.6259 0 17.9106 0 12.48V11.52Z" fill="#0077FF" />
                            <path d="M12.819 17.5C7.40402 17.5 4.11871 13.743 3.98999 7.5H6.73254C6.81804 12.086 8.9045 14.0293 10.5039 14.4289V7.5H13.1322V11.4577C14.6749 11.2867 16.2897 9.48655 16.8324 7.5H19.4194C19.0054 9.94284 17.2474 11.743 16.0054 12.4856C17.2484 13.086 19.2484 14.6575 20.0198 17.5H17.1764C16.576 15.5999 15.1053 14.1282 13.1331 13.9284V17.5H12.819Z" fill="white" />
                        </svg>
                        <span class="hidden xl:inline">Войти через VK ID</span>
                    </Button>

                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 gap-2 border-input bg-secondary/60"
                        :disabled="session.isLoggingIn"
                        aria-label="Войти через Яндекс ID"
                        @click="loginWith('yandex')"
                    >
                        <svg class="size-5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                            <rect width="40" height="40" fill="#f8604a" rx="20" />
                            <path fill="#fff" d="M22.776 31.5h4.178v-24h-6.078c-6.112 0-9.324 3.142-9.324 7.77 0 3.695 1.762 5.87 4.904 8.115L11 31.5h4.524l6.077-9.082-2.106-1.416c-2.555-1.726-3.799-3.073-3.799-5.974 0-2.555 1.796-4.282 5.215-4.282h1.864V31.5Z" />
                        </svg>
                        <span class="hidden xl:inline">Войти через Яндекс ID</span>
                    </Button>
                </template>

                <DropdownMenu v-else>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="relative rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                            :aria-label="unreadLabel ? `Меню профиля, непрочитанных сообщений: ${unreadLabel}` : 'Меню профиля'"
                        >
                            <Avatar class="size-9 border border-border">
                                <AvatarImage v-if="user?.avatarUrl" :src="user.avatarUrl" :alt="user.displayName" />
                                <AvatarFallback class="bg-primary/15 text-sm font-bold text-primary">
                                    {{ initials }}
                                </AvatarFallback>
                            </Avatar>

                            <span
                                v-if="unreadLabel"
                                class="absolute -top-1 -right-1 inline-flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-4 font-bold text-white"
                                aria-hidden="true"
                            >
                                {{ unreadLabel }}
                            </span>
                        </button>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent align="end" class="w-52">
                        <DropdownMenuLabel>{{ user?.displayName }}</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @select="openCabinet">Личный кабинет</DropdownMenuItem>
                        <DropdownMenuItem variant="destructive" @select="logout">Выйти</DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </header>
</template>
