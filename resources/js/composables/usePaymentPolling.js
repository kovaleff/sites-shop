import { onBeforeUnmount, onMounted, ref } from 'vue';

import { fetchPayment } from '@/services/paymentService';

const FINAL_STATUSES = new Set(['paid', 'failed', 'cancelled', 'requires_review']);

/**
 * Polling статуса оплаты без полной перезагрузки страницы.
 *
 * Опрашивает раз в 4 секунды, делает паузу при скрытой вкладке, возобновляется
 * на `visibilitychange`, замедляется при сетевых ошибках и останавливается на
 * финальном статусе (ui-contract.md §3.3).
 *
 * @param {{ intervalMs?: number, maxDurationMs?: number, onFinal?: (payment: object) => void }} [options]
 */
export function usePaymentPolling({ intervalMs = 4000, maxDurationMs = 17 * 60 * 1000, onFinal } = {}) {
    const payment = ref(null);
    const isPolling = ref(false);
    const error = ref(null);

    let timer = null;
    let startedAt = 0;
    let failures = 0;
    let currentId = null;
    let stopped = true;

    function clearTimer() {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    }

    function nextDelay() {
        if (failures === 0) {
            return intervalMs;
        }

        return Math.min(intervalMs * 2 ** Math.min(failures, 3), 15000);
    }

    function schedule() {
        clearTimer();

        if (stopped || currentId === null) {
            return;
        }

        if (Date.now() - startedAt > maxDurationMs) {
            stop();

            return;
        }

        timer = setTimeout(tick, nextDelay());
    }

    async function tick() {
        if (stopped || currentId === null) {
            return;
        }

        if (typeof document !== 'undefined' && document.hidden) {
            schedule();

            return;
        }

        try {
            const payload = await fetchPayment(currentId);
            failures = 0;
            error.value = null;
            payment.value = payload?.data ?? null;

            if (payment.value && FINAL_STATUSES.has(payment.value.status)) {
                stop();
                onFinal?.(payment.value);

                return;
            }
        } catch (requestError) {
            failures += 1;
            error.value = requestError?.message ?? null;
        }

        schedule();
    }

    /**
     * @param {number} paymentId
     * @returns {void}
     */
    function start(paymentId) {
        currentId = paymentId;
        startedAt = Date.now();
        failures = 0;
        stopped = false;
        isPolling.value = true;
        schedule();
    }

    /**
     * @returns {void}
     */
    function stop() {
        stopped = true;
        isPolling.value = false;
        clearTimer();
    }

    /**
     * @returns {Promise<object | null>}
     */
    async function checkNow() {
        if (currentId === null) {
            return payment.value;
        }

        try {
            const payload = await fetchPayment(currentId);
            payment.value = payload?.data ?? null;

            if (payment.value && FINAL_STATUSES.has(payment.value.status)) {
                stop();
                onFinal?.(payment.value);
            }

            return payment.value;
        } catch (requestError) {
            error.value = requestError?.message ?? null;

            return payment.value;
        }
    }

    function handleVisibility() {
        if (typeof document !== 'undefined' && !document.hidden && !stopped) {
            clearTimer();
            schedule();
        }
    }

    onMounted(() => {
        if (typeof document !== 'undefined') {
            document.addEventListener('visibilitychange', handleVisibility);
        }
    });

    onBeforeUnmount(() => {
        stop();

        if (typeof document !== 'undefined') {
            document.removeEventListener('visibilitychange', handleVisibility);
        }
    });

    return { payment, isPolling, error, start, stop, checkNow };
}
