<script setup>
import 'vue-sonner/style.css';

import { Toaster } from '@/components/ui/sonner';
import SiteHeader from '@/components/layout/SiteHeader.vue';
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <SiteHeader />
        <RouterView />
        <Toaster theme="dark" position="top-center" :rich-colors="true" />
    </div>
</template>
