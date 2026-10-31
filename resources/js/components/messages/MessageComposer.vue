<script setup>
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { MESSAGE_MAX } from '@/lib/cabinetConfig';

const props = defineProps({
    disabled: { type: Boolean, default: false },
    sending: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Напишите сообщение…' },
});

const emit = defineEmits(['send']);

const text = ref('');
const error = ref('');

const remaining = computed(() => MESSAGE_MAX - text.value.length);
const canSend = computed(
    () => !props.disabled && !props.sending && text.value.trim().length > 0,
);

/**
 * @returns {void}
 */
function send() {
    const body = text.value.trim();

    if (body === '') {
        error.value = 'Введите сообщение.';

        return;
    }

    if (body.length > MESSAGE_MAX) {
        error.value = `Сообщение должно быть не длиннее ${MESSAGE_MAX} символов.`;

        return;
    }

    error.value = '';
    emit('send', body);
    text.value = '';
}

/**
 * Enter отправляет, Shift+Enter переносит строку.
 *
 * @param {KeyboardEvent} event
 * @returns {void}
 */
function onKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        send();
    }
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <Textarea
            v-model="text"
            :disabled="disabled"
            :placeholder="disabled ? 'Отправка недоступна' : placeholder"
            rows="3"
            :maxlength="MESSAGE_MAX"
            @keydown="onKeydown"
        />

        <div class="flex items-center justify-between gap-2">
            <span class="text-[11px] text-muted-foreground">{{ remaining }} символов осталось</span>

            <Button type="button" size="sm" :disabled="!canSend" @click="send">
                {{ sending ? 'Отправляем…' : 'Отправить' }}
            </Button>
        </div>

        <p v-if="error" class="text-xs text-destructive">{{ error }}</p>
    </div>
</template>