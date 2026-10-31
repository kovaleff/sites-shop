<script setup>
import { RouterLink } from 'vue-router';
import { FolderOpen, MessageSquare, Archive } from '@lucide/vue';

const items = [
    { name: 'cabinet-sites', label: 'Мои объявления', icon: FolderOpen },
    { name: 'cabinet-messages', label: 'Сообщения', icon: MessageSquare },
    { name: 'cabinet-hidden', label: 'Скрытые', icon: Archive },
];
</script>

<template>
    <nav class="flex flex-wrap gap-1 border-b border-border pb-2" aria-label="Разделы кабинета">
        <RouterLink
            v-for="item in items"
            :key="item.name"
            :to="{ name: item.name }"
            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
            :class="
                $route.name === item.name || ($route.name === 'cabinet-conversation' && item.name === 'cabinet-messages')
                    ? 'bg-primary/15 text-primary'
                    : 'text-muted-foreground hover:bg-accent hover:text-foreground'
            "
        >
            <component :is="item.icon" class="size-4" aria-hidden="true" />
            {{ item.label }}
        </RouterLink>
    </nav>
</template>