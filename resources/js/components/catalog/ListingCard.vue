<script setup>
import { computed } from 'vue';
import { Globe } from '@lucide/vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatRub, formatRubMonthly } from '@/lib/format';

const props = defineProps({
    listing: { type: Object, required: true },
});

defineEmits(['contact']);

const initial = computed(() => (props.listing.domain?.charAt(0) || '?').toUpperCase());

const hasDescription = computed(
    () => typeof props.listing.description === 'string' && props.listing.description.trim().length > 0,
);

const zoneLabel = computed(() =>
    props.listing.domainZone ? `.${props.listing.domainZone}` : 'зона не определена',
);
</script>

<template>
    <Card
        class="grid grid-cols-[48px_minmax(0,1fr)] gap-3 border-border bg-card p-4 transition-colors hover:border-primary/40 hover:bg-secondary lg:grid-cols-[48px_minmax(0,1fr)_auto_auto_auto_auto_auto] lg:items-center lg:gap-4"
    >
        <div
            class="flex size-11 items-center justify-center overflow-hidden rounded-lg border border-border bg-secondary text-lg font-extrabold text-primary"
        >
            <img
                v-if="listing.faviconUrl"
                :src="listing.faviconUrl"
                alt=""
                class="size-full object-cover"
                loading="lazy"
            />
            <span v-else aria-hidden="true">{{ initial }}</span>
        </div>

        <div class="min-w-0">
            <h3 class="line-clamp-2 text-[15px] leading-snug font-bold text-foreground">
                {{ listing.title }}
            </h3>

            <div class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                <Globe class="size-3.5 shrink-0" aria-hidden="true" />
                <span class="truncate" :title="listing.domain">{{ listing.domain }}</span>
                <span aria-hidden="true">·</span>
                <span class="shrink-0">{{ zoneLabel }}</span>
            </div>

            <p class="mt-1 line-clamp-2 min-h-[2.6em] text-xs text-muted-foreground">
                {{ hasDescription ? listing.description : '\u00A0' }}
            </p>
        </div>

        <div class="col-span-2 flex flex-col gap-0.5 lg:col-span-1 lg:min-w-[96px]">
            <span class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                Цена
            </span>
            <span class="text-sm font-bold tabular-nums text-primary">
                {{ formatRub(listing.price) }}
            </span>
        </div>

        <div class="col-span-2 flex flex-col gap-0.5 lg:col-span-1 lg:min-w-[110px]">
            <span class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                Доход
            </span>
            <span class="text-sm font-bold tabular-nums text-verified">
                {{ formatRubMonthly(listing.monthlyIncome) }}
            </span>
        </div>

        <div class="col-span-2 flex flex-col gap-1 lg:col-span-1">
            <span class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                Статус
            </span>
            <Badge
                variant="outline"
                class="w-fit border-verified/40 bg-verified/10 text-verified"
            >
                <span class="size-1.5 rounded-full bg-verified" aria-hidden="true" />
                Активен
            </Badge>
        </div>

        <div class="col-span-2 flex flex-col gap-0.5 lg:col-span-1">
            <span class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                Проверен
            </span>
            <span class="text-sm font-semibold" :class="listing.isVerified ? 'text-verified' : 'text-muted-foreground'">
                {{ listing.isVerified ? 'Да' : 'Нет' }}
            </span>
        </div>

        <Button class="col-span-2 w-full lg:col-span-1 lg:w-auto" size="sm" @click="$emit('contact', listing)">
            Связаться
        </Button>
    </Card>
</template>
