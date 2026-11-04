<script setup>
import { computed } from 'vue';
import { Pencil, Archive, ArchiveRestore, CreditCard, History } from '@lucide/vue';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import ModerationBadge from '@/components/cabinet/ModerationBadge.vue';
import SiteMetrics from '@/components/cabinet/SiteMetrics.vue';
import StatusBadge from '@/components/cabinet/StatusBadge.vue';
import StatusMenu from '@/components/cabinet/StatusMenu.vue';
import SubscriptionBlock from '@/components/cabinet/SubscriptionBlock.vue';
import VerificationBlock from '@/components/cabinet/VerificationBlock.vue';
import { formatRub, formatRubMonthly } from '@/lib/format';

const props = defineProps({
    site: { type: Object, required: true },
    mode: { type: String, default: 'visible' },
});

const emit = defineEmits(['edit', 'change', 'hide', 'restore', 'pay', 'history']);

const isSold = computed(() => props.site.status === 'sold');

const needsModeration = computed(() => props.site.moderationStatus !== 'approved');

const latestPayment = computed(() => props.site.latestPayment ?? null);

const paymentAction = computed(() => {
    if (isSold.value || !props.site.canRenew) {
        return null;
    }

    switch (latestPayment.value?.status) {
        case 'requires_review':
            return { key: 'review', label: 'Платёж на проверке', variant: 'ghost' };
        case 'pending':
            return { key: 'continue', label: 'Продолжить оплату', variant: 'default' };
        case 'failed':
        case 'cancelled':
            return { key: 'retry', label: 'Повторить оплату', variant: 'outline' };
        default:
            return { key: 'pay', label: 'Продлить', variant: 'default' };
    }
});
</script>

<template>
    <Card class="flex flex-col gap-3 border-border bg-card p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-base font-bold text-foreground">{{ site.title }}</h3>
                    <StatusBadge :status="site.status" :label="site.statusLabel" />
                    <ModerationBadge
                        v-if="needsModeration"
                        :status="site.moderationStatus"
                        :label="site.moderationStatusLabel"
                    />
                </div>

                <p class="mt-1 text-sm text-muted-foreground">
                    {{ site.domain }}
                    <span v-if="site.domainZone" class="text-muted-foreground">· .{{ site.domainZone }}</span>
                </p>
            </div>

            <div class="flex flex-col items-end gap-0.5">
                <span class="text-sm font-bold tabular-nums text-primary">{{ formatRub(site.price) }}</span>
                <span class="text-xs text-muted-foreground">{{ formatRubMonthly(site.monthlyRevenue) }}</span>
            </div>
        </div>

        <p
            v-if="site.moderationStatus === 'rejected' && site.moderationNote"
            class="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-xs text-destructive"
        >
            {{ site.moderationNote }}
        </p>

        <p
            v-if="site.unpublishReason"
            class="rounded-lg border border-warning/40 bg-warning/10 px-3 py-2 text-xs text-warning"
        >
            {{ site.unpublishReason }}
        </p>

        <div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
            <span v-if="site.hasTrademark">Товарный знак есть</span>
            <span v-else>Без товарного знака</span>
        </div>

        <SiteMetrics :site="site" />

        <SubscriptionBlock
            :paid-until="site.paidUntil"
            :needs-renewal="site.needsRenewal"
            :is-publicly-visible="site.isPubliclyVisible"
        />

        <VerificationBlock :filename="site.verificationFilename" :verified="site.verified" />

        <div class="flex flex-wrap items-center gap-2">
            <template v-if="mode === 'visible'">
                <Button
                    v-if="!isSold"
                    type="button"
                    variant="secondary"
                    size="sm"
                    @click="emit('edit', site)"
                >
                    <Pencil class="size-3.5" aria-hidden="true" />
                    Редактировать
                </Button>

                <Button
                    v-if="paymentAction"
                    type="button"
                    :variant="paymentAction.variant"
                    size="sm"
                    @click="paymentAction.key === 'review' ? emit('history', site) : emit('pay', site)"
                >
                    <CreditCard class="size-3.5" aria-hidden="true" />
                    {{ paymentAction.label }}
                </Button>

                <Button
                    v-if="site.latestPayment"
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="emit('history', site)"
                >
                    <History class="size-3.5" aria-hidden="true" />
                    История
                </Button>

                <StatusMenu :status="site.status" @change="emit('change', $event)" />

                <Button
                    v-if="isSold"
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="emit('hide', site)"
                >
                    <Archive class="size-3.5" aria-hidden="true" />
                    Скрыть
                </Button>
            </template>

            <Button
                v-else
                type="button"
                variant="secondary"
                size="sm"
                @click="emit('restore', site)"
            >
                <ArchiveRestore class="size-3.5" aria-hidden="true" />
                Вернуть в список
            </Button>
        </div>
    </Card>
</template>