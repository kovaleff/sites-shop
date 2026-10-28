const numberFormatter = new Intl.NumberFormat('ru-RU', {
    maximumFractionDigits: 0,
});

/**
 * @param {unknown} value
 * @returns {number}
 */
function toSafeAmount(value) {
    const amount = typeof value === 'number' ? value : Number(value);

    if (!Number.isFinite(amount) || amount <= 0) {
        return 0;
    }

    return Math.trunc(amount);
}

/**
 * @param {unknown} value
 * @returns {string}
 */
function formatNumber(value) {
    return numberFormatter.format(toSafeAmount(value)).replace(/\s/g, '\u00A0');
}

/**
 * @param {unknown} value
 * @returns {string}
 */
export function formatRub(value) {
    return `${formatNumber(value)}\u00A0₽`;
}

/**
 * @param {unknown} value
 * @returns {string}
 */
export function formatRubMonthly(value) {
    return `${formatNumber(value)}\u00A0₽/мес`;
}

/**
 * @param {unknown} value
 * @returns {string}
 */
export function formatCount(value) {
    return formatNumber(value);
}

/**
 * @param {string | null | undefined} iso
 * @returns {string}
 */
export function formatDate(iso) {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const day = String(date.getUTCDate()).padStart(2, '0');
    const month = String(date.getUTCMonth() + 1).padStart(2, '0');
    const year = date.getUTCFullYear();

    return `${day}.${month}.${year}`;
}
