<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { Eye, Heart, MessageSquare } from '@lucide/vue';

const props = defineProps({
    site: { type: Object, required: true },
});

const metrics = computed(() => {
    const unread = Number(props.site.unreadMessages ?? 0);

    return {
        today: Number(props.site.viewsToday ?? 0),
        week: Number(props.site.viewsWeek ?? 0),
        month: Number(props.site.viewsMonth ?? 0),
        total: Number(props.site.viewsTotal ?? 0),
        favorites: Number(props.site.favoritesCount ?? 0),
        messages: Math.max(Number(props.site.messagesCount ?? 0), unread),
        unread,
    };
});

const viewsHint = computed(() => `Сегодня: ${metrics.value.today} · Всего: ${metrics.value.total}`);
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
        <span class="inline-flex items-center gap-1" :title="viewsHint">
            <Eye class="size-3.5 shrink-0" aria-hidden="true" />
            <span>
                Просмотры:
                <strong class="font-semibold text-foreground">{{ metrics.week }}</strong> за 7 дней
                <span aria-hidden="true">·</span>
                <strong class="font-semibold text-foreground">{{ metrics.month }}</strong> за 30 дней
            </span>
        </span>

        <span class="inline-flex items-center gap-1">
            <Heart class="size-3.5 shrink-0" aria-hidden="true" />
            В избранном: <strong class="font-semibold text-foreground">{{ metrics.favorites }}</strong>
        </span>

        <RouterLink
            v-if="metrics.unread > 0"
            :to="{ name: 'cabinet-messages' }"
            class="inline-flex items-center gap-1 font-semibold text-primary hover:underline"
        >
            <MessageSquare class="size-3.5 shrink-0" aria-hidden="true" />
            Сообщения: {{ metrics.messages }}
            <span class="font-normal text-muted-foreground">· {{ metrics.unread }} новых</span>
        </RouterLink>

        <span v-else class="inline-flex items-center gap-1">
            <MessageSquare class="size-3.5 shrink-0" aria-hidden="true" />
            Сообщения: <strong class="font-semibold text-foreground">{{ metrics.messages }}</strong>
        </span>
    </div>
</template>
