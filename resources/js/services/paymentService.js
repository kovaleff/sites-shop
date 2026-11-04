import { fetchJson, postJson } from '@/services/http';

/**
 * Понятные продавцу тексты бизнес-ошибок оплаты (ui-contract.md §9).
 */
export const PAYMENT_ERROR_MESSAGES = {
    sold: 'Проданное объявление нельзя оплатить.',
    under_review: 'Предыдущий платёж ещё проверяется. Дождитесь решения администратора.',
    tariff_inactive: 'Оплата временно недоступна. Попробуйте позже.',
    domain_conflict:
        'Домен уже занят другим активным или неактивным объявлением. Измените домен или обратитесь в поддержку.',
    receipt_email_required: 'Укажите email для чека.',
    invalid_period: 'Выберите срок из доступных.',
    invalid_amount: 'Итоговая сумма должна быть больше нуля. Проверьте настройки тарифа.',
    expired: 'Срок действия QR-кода истёк. Создайте новую попытку.',
    provider_unavailable: 'Не удалось получить QR-код. Проверьте соединение и повторите попытку.',
    not_payable: 'Платёж больше нельзя продолжить. Создайте новую попытку.',
};

/**
 * @typedef {object} PricingPeriod
 * @property {number} months
 * @property {number} discountPercent
 * @property {number} total
 * @property {number} perMonth
 */

/**
 * @returns {Promise<{ basePrice: number, isActive: boolean, periods: PricingPeriod[] }>}
 */
export function fetchPricing() {
    return fetchJson('/api/v1/pricing');
}

/**
 * @param {number} siteId
 * @returns {Promise<object>}
 */
export function fetchPaymentOptions(siteId) {
    return fetchJson(`/api/v1/my/sites/${siteId}/payment-options`);
}

/**
 * @param {number} siteId
 * @returns {Promise<{ data: object[] }>}
 */
export function fetchPaymentHistory(siteId) {
    return fetchJson(`/api/v1/my/sites/${siteId}/payments`);
}

/**
 * @param {number} siteId
 * @param {{ periodMonths: number, receiptEmail: string }} payload
 * @returns {Promise<{ data: object, robokassa: object }>}
 */
export function createPayment(siteId, payload) {
    return postJson(`/api/v1/my/sites/${siteId}/payments`, payload);
}

/**
 * @param {number} paymentId
 * @returns {Promise<{ data: object }>}
 */
export function fetchPayment(paymentId) {
    return fetchJson(`/api/v1/my/payments/${paymentId}`);
}

/**
 * @param {number} paymentId
 * @returns {Promise<{ data: object, robokassa: object }>}
 */
export function startPayment(paymentId) {
    return postJson(`/api/v1/my/payments/${paymentId}/start`);
}

/**
 * Текст ошибки оплаты: известный код важнее серверного сообщения.
 *
 * @param {unknown} error
 * @returns {string}
 */
export function paymentErrorMessage(error) {
    const code = error?.code;

    if (typeof code === 'string' && PAYMENT_ERROR_MESSAGES[code]) {
        return PAYMENT_ERROR_MESSAGES[code];
    }

    return error?.message ?? 'Не удалось выполнить оплату. Попробуйте ещё раз.';
}

const loadedScripts = new Map();

/**
 * Динамически подключает клиент Robokassa только при открытии окна оплаты.
 *
 * @param {string} src
 * @returns {Promise<void>}
 */
export function loadRobokassaScript(src) {
    if (typeof window === 'undefined') {
        return Promise.resolve();
    }

    if (window.Robokassa?.pay?.startOp) {
        return Promise.resolve();
    }

    if (loadedScripts.has(src)) {
        return loadedScripts.get(src);
    }

    const promise = new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[data-robokassa="${src}"]`);

        if (existing) {
            existing.addEventListener('load', () => resolve());
            existing.addEventListener('error', () => reject(new Error('provider_unavailable')));

            return;
        }

        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.dataset.robokassa = src;
        script.addEventListener('load', () => resolve());
        script.addEventListener('error', () => {
            loadedScripts.delete(src);
            reject(new Error('provider_unavailable'));
        });

        document.head.appendChild(script);
    });

    loadedScripts.set(src, promise);

    return promise;
}

/**
 * Открывает платёжную операцию СБП и рисует QR в контейнере.
 *
 * @param {{ scriptUrl: string, merchantLogin: string, outSum: string, invId: number, receipt: string, signature: string, email: string, isTest?: boolean, qrContainerId: string, qrContainerSize?: number, onPaymentLink?: (link: string) => void }} options
 * @returns {Promise<string | null>} ссылка на оплату, если провайдер её вернул
 */
export async function startRobokassaPayment(options) {
    await loadRobokassaScript(options.scriptUrl);

    const startOp = typeof window !== 'undefined' ? window.Robokassa?.pay?.startOp : null;

    if (typeof startOp !== 'function') {
        throw new Error('provider_unavailable');
    }

    let paymentLink = null;

    startOp({
        merchantLogin: options.merchantLogin,
        outSum: options.outSum,
        invId: options.invId,
        receipt: options.receipt,
        signature: options.signature,
        email: options.email,
        paymentMethod: 'SBP',
        qrContainerId: options.qrContainerId,
        qrContainerSize: options.qrContainerSize ?? 320,
        ...(options.isTest ? { isTest: 1 } : {}),
        onpaymentlink: (link) => {
            paymentLink = link;
            options.onPaymentLink?.(link);
        },
    });

    return paymentLink;
}
