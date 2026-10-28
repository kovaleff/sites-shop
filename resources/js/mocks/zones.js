/**
 * Начальный набор зон на случай ошибки загрузки списка зон.
 * В обычном режиме зоны выводятся из публичных лотов через fetchZones().
 */
export const fallbackZones = Object.freeze([
    Object.freeze({ value: 'ru', label: '.ru' }),
    Object.freeze({ value: 'com', label: '.com' }),
    Object.freeze({ value: 'de', label: '.de' }),
]);
