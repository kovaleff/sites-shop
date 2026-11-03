<script setup>
import { computed, ref, watch } from 'vue';
import { Star } from '@lucide/vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { submitReview } from '@/services/reviewService';

const RATING_LABELS = Object.freeze({
    1: 'Ужасно',
    2: 'Плохо',
    3: 'Нормально',
    4: 'Хорошо',
    5: 'Отлично',
});

const COMMENT_MAX = 1000;

const props = defineProps({
    open: { type: Boolean, default: false },
    listing: { type: Object, default: null },
});

const emit = defineEmits(['update:open', 'submitted']);

const rating = ref(5);
const comment = ref('');
const error = ref('');
const sending = ref(false);

const ratingLabel = computed(() => RATING_LABELS[rating.value] ?? '');
const canSubmit = computed(
    () => !sending.value && rating.value >= 1 && rating.value <= 5,
);

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        rating.value = 5;
        comment.value = '';
        error.value = '';
    },
);

/**
 * @returns {Promise<void>}
 */
async function submit() {
    if (!props.listing) {
        return;
    }

    error.value = '';
    sending.value = true;

    try {
        await submitReview(props.listing.id, {
            rating: rating.value,
            comment: comment.value.trim() === '' ? null : comment.value.trim(),
        });

        emit('update:open', false);
        emit('submitted');
        toast.success('Спасибо! Отзыв отправлен на проверку.');
    } catch (requestError) {
        error.value =
            requestError?.data?.errors?.review?.[0]
            ?? requestError?.data?.errors?.comment?.[0]
            ?? requestError?.data?.errors?.rating?.[0]
            ?? requestError?.message
            ?? 'Не удалось отправить отзыв';
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Отзыв о сделке</DialogTitle>
                <DialogDescription v-if="listing">
                    {{ listing.title }} · {{ listing.domain }}
                </DialogDescription>
            </DialogHeader>

            <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
                <div class="flex flex-col gap-2">
                    <span class="text-sm font-semibold text-foreground">Оценка</span>

                    <div class="flex flex-wrap items-center gap-1.5">
                        <button
                            v-for="value in 5"
                            :key="value"
                            type="button"
                            class="rounded-md p-1 transition-transform hover:scale-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :aria-label="`Оценка ${value} из 5`"
                            :aria-pressed="rating === value"
                            @click="rating = value"
                        >
                            <Star
                                class="size-7"
                                :class="
                                    value <= rating
                                        ? 'fill-amber-400 text-amber-400'
                                        : 'text-muted-foreground/40'
                                "
                                aria-hidden="true"
                            />
                        </button>

                        <span class="ml-1 text-sm font-medium text-muted-foreground">
                            {{ ratingLabel }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="review-comment" class="text-sm font-semibold text-foreground">
                        Комментарий <span class="font-normal text-muted-foreground">(необязательно)</span>
                    </label>

                    <Textarea
                        id="review-comment"
                        v-model="comment"
                        rows="4"
                        :maxlength="COMMENT_MAX"
                        placeholder="Как прошла сделка: связь с продавцом, передача домена, соответствие описанию."
                    />

                    <span class="text-[11px] text-muted-foreground">
                        {{ comment.length }} / {{ COMMENT_MAX }} символов
                    </span>
                </div>

                <p class="text-xs text-muted-foreground">
                    Отзыв появится в профиле продавца после проверки модератором.
                </p>

                <p v-if="error" class="text-xs text-destructive">{{ error }}</p>

                <DialogFooter class="flex-row justify-end">
                    <Button type="button" variant="ghost" @click="emit('update:open', false)">
                        Отмена
                    </Button>
                    <Button type="submit" :disabled="!canSubmit">
                        {{ sending ? 'Отправляем…' : 'Отправить отзыв' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
