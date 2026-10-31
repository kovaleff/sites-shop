<script setup>
import { RouterLink } from 'vue-router';

import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/format';

defineProps({
    items: { type: Array, default: () => [] },
    activeId: { type: Number, default: null },
});
</script>

<template>
    <ul class="flex flex-col divide-y divide-border rounded-xl border border-border">
        <li v-for="conversation in items" :key="conversation.id">
            <RouterLink
                :to="{ name: 'cabinet-conversation', params: { id: conversation.id } }"
                class="flex items-start gap-3 p-3 transition-colors hover:bg-accent"
                :class="conversation.id === activeId ? 'bg-primary/10' : ''"
            >
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="truncate text-sm font-semibold text-foreground">
                            {{ conversation.counterparty?.displayName || 'Пользователь' }}
                        </span>
                        <Badge variant="outline" class="text-[10px]">
                            {{ conversation.role === 'buyer' ? 'Покупатель' : 'Продавец' }}
                        </Badge>
                        <span
                            v-if="conversation.unreadCount > 0"
                            class="inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] font-bold text-primary-foreground"
                        >
                            {{ conversation.unreadCount }}
                        </span>
                    </div>

                    <p class="mt-0.5 truncate text-xs text-muted-foreground">
                        {{ conversation.site?.title }} · {{ conversation.site?.domain }}
                    </p>

                    <p class="mt-1 truncate text-xs text-muted-foreground">
                        {{ conversation.lastMessage?.body || 'Нет сообщений' }}
                    </p>
                </div>

                <span class="shrink-0 text-[11px] text-muted-foreground">
                    {{ conversation.lastMessageAt ? formatDate(conversation.lastMessageAt) : '' }}
                </span>
            </RouterLink>
        </li>
    </ul>
</template>