<script setup>
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

defineProps({
    message: { type: String, default: 'Не удалось загрузить каталог' },
});

defineEmits(['retry', 'reset']);
</script>

<template>
    <Alert variant="destructive" class="border-destructive/40 bg-destructive/10">
        <AlertTitle>Не удалось загрузить каталог</AlertTitle>
        <AlertDescription class="flex flex-col items-start gap-3">
            <span>{{ message }}</span>
            <span class="flex flex-wrap gap-2">
                <Button size="sm" @click="$emit('retry')">Повторить</Button>
                <Button size="sm" variant="outline" @click="$emit('reset')">Сбросить все</Button>
            </span>
        </AlertDescription>
    </Alert>
</template>
