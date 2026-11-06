<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatRub, formatRubMonthly } from '@/lib/format';
import { fetchPricing } from '@/services/paymentService';
import { useSessionStore } from '@/stores/session';

const session = useSessionStore();

const pricing = ref(null);
const status = ref('loading');
const error = ref('');

const isLoading = computed(() => status.value === 'loading');
const isError = computed(() => status.value === 'error');

onMounted(load);

/**
 * @returns {Promise<void>}
 */
async function load() {
    status.value = 'loading';
    error.value = '';

    try {
        pricing.value = await fetchPricing();
        status.value = 'success';
    } catch (requestError) {
        status.value = 'error';
        error.value = requestError?.message ?? 'Не удалось загрузить тарифы';
    }
}

/**
 * @param {number} months
 * @returns {string}
 */
function monthsLabel(months) {
    const forms = ['месяц', 'месяца', 'месяцев'];
    const absolute = Math.abs(months) % 100;
    const last = absolute % 10;

    if (absolute > 10 && absolute < 20) {
        return `${months} ${forms[2]}`;
    }

    if (last === 1) {
        return `${months} ${forms[0]}`;
    }

    if (last >= 2 && last <= 4) {
        return `${months} ${forms[1]}`;
    }

    return `${months} ${forms[2]}`;
}
</script>

<template>
    <section class="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-10">
        <header class="flex flex-col gap-2">
            <h1 class="text-3xl font-extrabold text-foreground">Тарифы</h1>
            <p class="text-sm text-muted-foreground">
                Размещение объявления на площадке. Оплата — через СБП по QR-коду в личном кабинете.
            </p>
        </header>

        <template v-if="isLoading">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Skeleton v-for="index in 4" :key="index" class="h-40 w-full rounded-xl" />
            </div>
        </template>

        <div
            v-else-if="isError"
            class="flex flex-col items-start gap-2 rounded-xl border border-border p-6"
        >
            <p class="text-sm text-destructive">{{ error }}</p>
            <Button variant="outline" size="sm" type="button" @click="load">Повторить</Button>
        </div>

        <template v-else-if="pricing">
            <div
                v-if="!pricing.isActive"
                class="rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm text-warning"
            >
                Оплата временно недоступна. Мы обновляем тариф — попробуйте позже.
            </div>

            <p class="text-sm text-muted-foreground">
                Базовая цена: <span class="font-semibold text-foreground">{{ formatRubMonthly(pricing.basePrice) }}</span>
                за один домен.
            </p>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Card
                    v-for="period in pricing.periods"
                    :key="period.months"
                    class="flex flex-col gap-2 border-border bg-card p-4"
                >
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-base font-bold text-foreground">{{ monthsLabel(period.months) }}</span>
                        <span
                            v-if="period.discountPercent > 0"
                            class="rounded-full border border-emerald-400/40 bg-emerald-400/10 px-2 py-0.5 text-xs font-semibold text-emerald-600"
                        >
                            −{{ period.discountPercent }}%
                        </span>
                    </div>

                    <span class="text-2xl font-extrabold tabular-nums text-primary">
                        {{ formatRub(period.total) }}
                    </span>

                    <span class="text-xs text-muted-foreground">
                        {{ formatRubMonthly(period.perMonth) }}
                    </span>
                </Card>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button v-if="session.isAuthenticated" as-child>
                    <RouterLink :to="{ name: 'cabinet-sites' }">Перейти в кабинет</RouterLink>
                </Button>
                <Button v-else as-child>
                    <RouterLink :to="{ name: 'catalog' }">Войти</RouterLink>
                </Button>
            </div>

            <p class="text-xs text-muted-foreground">
                Оплата подписки означает акцепт
                <a href="/offer" class="text-primary underline underline-offset-2">публичной оферты</a>.
                Срок возврата и порядок оплаты описаны в оферте.
            </p>
        </template>
    </section>
</template>
