<script setup>
import { computed, ref } from 'vue';
import { ChevronDown } from '@lucide/vue';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ALLOWED_TRANSITIONS, STATUS_LABELS } from '@/lib/cabinetConfig';

const props = defineProps({
    status: { type: String, required: true },
});

const emit = defineEmits(['change']);

const isConfirmOpen = ref(false);

const options = computed(() =>
    (ALLOWED_TRANSITIONS[props.status] ?? []).map((value) => ({
        value,
        label: STATUS_LABELS[value],
    })),
);

/**
 * @param {string} status
 * @returns {void}
 */
function choose(status) {
    if (status === 'sold') {
        isConfirmOpen.value = true;

        return;
    }

    emit('change', status);
}

/**
 * @returns {void}
 */
function confirmSold() {
    isConfirmOpen.value = false;
    emit('change', 'sold');
}
</script>

<template>
    <div class="flex items-center gap-2">
        <DropdownMenu v-if="options.length > 0">
            <DropdownMenuTrigger as-child>
                <Button variant="outline" size="sm">
                    Сменить статус
                    <ChevronDown class="size-3.5" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end">
                <DropdownMenuItem
                    v-for="option in options"
                    :key="option.value"
                    @select="choose(option.value)"
                >
                    {{ option.label }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <AlertDialog v-model:open="isConfirmOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Отметить объявление проданным?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Статус «Продан» конечный: вернуть объявление в продажу или отредактировать его
                        будет нельзя.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel>Отмена</AlertDialogCancel>
                    <AlertDialogAction @click="confirmSold">Отметить проданным</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>