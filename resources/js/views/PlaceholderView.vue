<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

import { Button } from '@/components/ui/button';

const props = defineProps({
    title: { type: String, required: true },
    message: { type: String, default: 'Раздел в разработке. Скоро здесь появится подробная информация.' },
});

const route = useRoute();

const catalogLink = computed(() => ({ name: 'catalog', query: route.query }));
</script>

<template>
    <main class="mx-auto flex w-full max-w-[1440px] flex-col items-center px-4 py-20 text-center sm:px-6 lg:px-8">
        <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ title }}</h1>
        <p class="mt-4 max-w-xl text-sm text-muted-foreground sm:text-base">
            {{ message }}
        </p>

        <Button as-child class="mt-8">
            <RouterLink :to="catalogLink">Вернуться в каталог</RouterLink>
        </Button>
    </main>
</template>
