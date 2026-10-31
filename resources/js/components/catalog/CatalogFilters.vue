<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import CategoriesField from '@/components/catalog/CategoriesField.vue';
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
    categories: { type: Array, default: () => [] },
    categoriesLoading: { type: Boolean, default: false },
    activeCount: { type: Number, default: 0 },
});

const emit = defineEmits(['apply', 'reset']);

const AUTO_APPLY_DELAY_MS = 250;

/**
 * @param {object} filters
 * @returns {object}
 */
function cloneFilters(filters) {
    return {
        ...createDefaultFilters(),
        ...filters,
        zones: [...(filters.zones ?? [])],
        categories: [...(filters.categories ?? [])],
    };
}

/**
 * @param {object} left
 * @param {object} right
 * @returns {boolean}
 */
function sameFilters(left, right) {
    return (
        left.priceMin === right.priceMin &&
        left.priceMax === right.priceMax &&
        left.incomeMin === right.incomeMin &&
        left.incomeMax === right.incomeMax &&
        left.verifiedOnly === right.verifiedOnly &&
        [...left.zones].sort().join(',') === [...right.zones].sort().join(',') &&
        [...left.categories].sort().join(',') === [...right.categories].sort().join(',')
    );
}

const draft = ref(cloneFilters(props.applied));

let applyTimer = null;

/**
 * @returns {void}
 */
function scheduleApply() {
    if (applyTimer !== null) {
        clearTimeout(applyTimer);
    }

    applyTimer = setTimeout(() => {
        applyTimer = null;
        emit('apply', cloneFilters(draft.value));
    }, AUTO_APPLY_DELAY_MS);
}

watch(
    () => props.applied,
    (next) => {
        draft.value = cloneFilters(next);
    },
    { deep: true },
);

watch(
    draft,
    () => {
        if (sameFilters(draft.value, props.applied)) {
            return;
        }

        scheduleApply();
    },
    { deep: true },
);

onBeforeUnmount(() => {
    if (applyTimer !== null) {
        clearTimeout(applyTimer);
    }
});

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

const selectedCategories = computed({
    get: () => draft.value.categories,
    set: (categories) => {
        draft.value.categories = categories;
    },
});

const verifiedOnly = computed({
    get: () => draft.value.verifiedOnly,
    set: (value) => {
        draft.value.verifiedOnly = value;
    },
});

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

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
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

            <CategoriesField
                v-model="selectedCategories"
                :categories="categories"
                :loading="categoriesLoading"
            />

            <IncomeRangeField
                v-model="incomeRange"
                :min="INCOME_BOUNDS.min"
                :max="INCOME_BOUNDS.max"
                :step="INCOME_BOUNDS.step"
                label="Доход, ₽/мес"
            />

            <VerifiedField v-model="verifiedOnly" class="sm:col-span-2 lg:col-span-2 xl:col-span-1" />
        </div>
    </section>
</template>
