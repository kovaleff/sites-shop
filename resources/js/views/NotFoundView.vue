<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

import { Button } from '@/components/ui/button';

const route = useRoute();

const catalogLink = computed(() => ({ name: 'catalog', query: route.query }));
</script>

<template>
    <main class="mx-auto flex w-full max-w-[1440px] flex-col items-center px-4 py-20 text-center sm:px-6 lg:px-8">
        <p class="text-sm font-semibold tracking-wider text-primary uppercase">404</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">Страница не найдена</h1>
        <p class="mt-4 max-w-xl text-sm text-muted-foreground sm:text-base">
            Возможно, страница удалена или адрес введён с ошибкой.
        </p>

        <Button as-child class="mt-8">
            <RouterLink :to="catalogLink">Вернуться в каталог</RouterLink>
        </Button>
    </main>
</template>
