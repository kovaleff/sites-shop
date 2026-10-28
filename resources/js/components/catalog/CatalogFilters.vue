<script setup>
import { computed, ref, watch } from 'vue';

import DomainZoneField from '@/components/catalog/DomainZoneField.vue';
import IncomeRangeField from '@/components/catalog/IncomeRangeField.vue';
import PriceRangeField from '@/components/catalog/PriceRangeField.vue';
import VerifiedField from '@/components/catalog/VerifiedField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { INCOME_BOUNDS, PRICE_BOUNDS } from '@/lib/catalogConfig';
import { createDefaultFilters } from '@/lib/catalogFilters';

const props = defineProps({
    applied: { type: Object, required: true },
    zones: { type: Array, default: () => [] },
    zonesLoading: { type: Boolean, default: false },
    activeCount: { type: Number, default: 0 },
});

const emit = defineEmits(['apply', 'reset']);

/**
 * @param {object} filters
 * @returns {object}
 */
function cloneFilters(filters) {
    return {
        ...createDefaultFilters(),
        ...filters,
        zones: [...(filters.zones ?? [])],
    };
}

const draft = ref(cloneFilters(props.applied));

watch(
    () => props.applied,
    (next) => {
        draft.value = cloneFilters(next);
    },
    { deep: true },
);

const priceRange = computed({
    get: () => [draft.value.priceMin, draft.value.priceMax],
    set: ([priceMin, priceMax]) => {
        draft.value.priceMin = priceMin;
        draft.value.priceMax = priceMax;
    },
});

const incomeRange = computed({
    get: () => [draft.value.incomeMin, draft.value.incomeMax],
    set: ([incomeMin, incomeMax]) => {
        draft.value.incomeMin = incomeMin;
        draft.value.incomeMax = incomeMax;
    },
});

const selectedZones = computed({
    get: () => draft.value.zones,
    set: (zones) => {
        draft.value.zones = zones;
    },
});

const verifiedOnly = computed({
    get: () => draft.value.verifiedOnly,
    set: (value) => {
        draft.value.verifiedOnly = value;
    },
});

/**
 * @returns {void}
 */
function apply() {
    emit('apply', cloneFilters(draft.value));
}
</script>

<template>
    <section class="rounded-xl border border-border bg-card p-4 sm:p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-bold tracking-wider text-muted-foreground uppercase">
                    Фильтры
                </h2>
                <Badge v-if="activeCount > 0" class="bg-primary text-primary-foreground">
                    {{ activeCount }} активных
                </Badge>
            </div>

            <Button
                variant="link"
                size="sm"
                class="h-auto p-0 text-xs text-muted-foreground hover:text-primary"
                :disabled="activeCount === 0"
                @click="emit('reset')"
            >
                Сбросить все
            </Button>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <PriceRangeField
                v-model="priceRange"
                :min="PRICE_BOUNDS.min"
                :max="PRICE_BOUNDS.max"
                :step="PRICE_BOUNDS.step"
                label="Цена, ₽"
            />

            <DomainZoneField
                v-model="selectedZones"
                :zones="zones"
                :loading="zonesLoading"
            />

            <IncomeRangeField
                v-model="incomeRange"
                :min="INCOME_BOUNDS.min"
                :max="INCOME_BOUNDS.max"
                :step="INCOME_BOUNDS.step"
                label="Доход, ₽/мес"
            />

            <div class="flex flex-col justify-end gap-2">
                <VerifiedField v-model="verifiedOnly" />

                <Button class="h-9 w-full" @click="apply">Применить</Button>
            </div>
        </div>
    </section>
</template>
