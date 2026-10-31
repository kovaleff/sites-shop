<script setup>
import CabinetNav from '@/components/cabinet/CabinetNav.vue';
</script>

<template>
    <div class="mx-auto flex w-full max-w-[1440px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
        <CabinetNav />

        <main class="flex flex-col gap-4">
            <slot />
        </main>
    </div>
</template>