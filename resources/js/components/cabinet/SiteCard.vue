<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { MessageSquare, Pencil, Archive, ArchiveRestore } from '@lucide/vue';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import StatusBadge from '@/components/cabinet/StatusBadge.vue';
import StatusMenu from '@/components/cabinet/StatusMenu.vue';
import SubscriptionBlock from '@/components/cabinet/SubscriptionBlock.vue';
import VerificationBlock from '@/components/cabinet/VerificationBlock.vue';
import { formatRub, formatRubMonthly } from '@/lib/format';

const props = defineProps({
    site: { type: Object, required: true },
    mode: { type: String, default: 'visible' },
});

const emit = defineEmits(['edit', 'change', 'hide', 'restore']);

const isSold = computed(() => props.site.status === 'sold');
</script>

<template>
    <Card class="flex flex-col gap-3 border-border bg-card p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-base font-bold text-foreground">{{ site.title }}</h3>
                    <StatusBadge :status="site.status" :label="site.statusLabel" />
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

        <div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
            <span v-if="site.hasTrademark">Товарный знак есть</span>
            <span v-else>Без товарного знака</span>

            <RouterLink
                v-if="site.unreadMessages > 0"
                :to="{ name: 'cabinet-messages' }"
                class="inline-flex items-center gap-1 font-semibold text-primary hover:underline"
            >
                <MessageSquare class="size-3.5" aria-hidden="true" />
                Сообщения: {{ site.unreadMessages }}
            </RouterLink>
        </div>

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