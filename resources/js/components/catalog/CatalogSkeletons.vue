<script setup>
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

defineProps({
    count: { type: Number, default: 6 },
});
</script>

<template>
    <div class="grid grid-cols-1 gap-3" aria-hidden="true">
        <Card
            v-for="index in count"
            :key="index"
            class="grid min-h-[190px] grid-cols-[48px_minmax(0,1fr)] gap-3 border-border bg-card p-4 lg:min-h-[96px] lg:grid-cols-[48px_minmax(0,1fr)_repeat(4,minmax(80px,100px))_auto] lg:items-center"
        >
            <Skeleton class="size-11 rounded-lg" />

            <div class="flex flex-col gap-2">
                <Skeleton class="h-4 w-3/4" />
                <Skeleton class="h-3 w-1/2" />
                <Skeleton class="h-3 w-2/3" />
            </div>

            <Skeleton class="col-span-2 h-4 w-24 lg:col-span-1" />
            <Skeleton class="col-span-2 h-4 w-24 lg:col-span-1" />
            <Skeleton class="col-span-2 h-5 w-20 lg:col-span-1" />
            <Skeleton class="col-span-2 h-4 w-12 lg:col-span-1" />
            <Skeleton class="col-span-2 h-9 w-full lg:col-span-1 lg:w-24" />
        </Card>
    </div>
</template>
