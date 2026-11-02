/**
 * Единые настройки каталога. Значения вынесены из компонентов, чтобы будущий
 * API мог заменить их ответом эндпоинта фильтров без правок UI.
 */
export const PAGE_SIZE = 25;

export const PRICE_BOUNDS = Object.freeze({
    min: 0,
    max: 10_000_000,
    step: 50_000,
});

export const INCOME_BOUNDS = Object.freeze({
    min: 0,
    max: 1_000_000,
    step: 5_000,
});

export const DEFAULT_SORT = 'newest';

export const SORT_OPTIONS = Object.freeze([
    { value: 'newest', label: 'Сначала новые' },
    { value: 'price_asc', label: 'Цена: по возрастанию' },
    { value: 'price_desc', label: 'Цена: по убыванию' },
    { value: 'income_desc', label: 'Доход: по убыванию' },
    { value: 'income_asc', label: 'Доход: по возрастанию' },
]);

export const SEARCH_MIN_LENGTH = 2;

export const SEARCH_MAX_LENGTH = 100;

export const MOCK_LATENCY_MS = 250;
export const SLOW_LATENCY_MS = 2000;
export const MAX_PAGE_LINKS = 5;

export const DEFAULT_ZONES = Object.freeze(['ru', 'com', 'de']);
