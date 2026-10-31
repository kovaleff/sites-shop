/**
 * Настройки кабинета продавца и переписки.
 * Значения совпадают с правилами валидации на сервере (data-model.md §8)
 * и порогом пометки «требует продления» (spec.md FR-025).
 */
export const PAGE_SIZE = 25;

export const TITLE_MIN = 3;
export const TITLE_MAX = 255;
export const DESCRIPTION_MIN = 50;
export const DESCRIPTION_MAX = 5000;
export const MESSAGE_MAX = 2000;
export const MAX_AMOUNT = 9_999_999_999;

export const RENEWAL_DAYS = 7;

export const UNREAD_BADGE_MAX = 99;

export const STATUS_LABELS = Object.freeze({
    active: 'Активен',
    inactive: 'Неактивен',
    withdrawn: 'Снят с продажи',
    sold: 'Продан',
});

/**
 * Допустимые переходы статусов (FR-013). Сервер проверяет их повторно.
 */
export const ALLOWED_TRANSITIONS = Object.freeze({
    active: ['inactive', 'withdrawn', 'sold'],
    inactive: ['active', 'withdrawn', 'sold'],
    withdrawn: ['active', 'inactive', 'sold'],
    sold: [],
});

export const CABINET_ROUTES = Object.freeze({
    sites: '/cabinet/sites',
    siteCreate: '/cabinet/sites/new',
    hidden: '/cabinet/hidden',
    messages: '/cabinet/messages',
});