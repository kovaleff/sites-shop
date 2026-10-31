<script setup>
import { formatDate } from '@/lib/format';

defineProps({
    messages: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="flex flex-col gap-2" role="log" aria-label="История сообщений">
        <p v-if="messages.length === 0" class="text-sm text-muted-foreground">
            Сообщений пока нет.
        </p>

        <div
            v-for="message in messages"
            :key="message.id"
            class="flex flex-col gap-1"
            :class="message.isMine ? 'items-end' : 'items-start'"
        >
            <div
                class="max-w-[85%] rounded-2xl px-3 py-2 text-sm whitespace-pre-line"
                :class="
                    message.isMine
                        ? 'rounded-br-sm bg-primary text-primary-foreground'
                        : 'rounded-bl-sm bg-secondary text-secondary-foreground'
                "
            >
                {{ message.body }}
            </div>

            <span class="text-[11px] text-muted-foreground">
                {{ formatDate(message.createdAt) }}
            </span>
        </div>
    </div>
</template>