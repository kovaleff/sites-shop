import { formatCount, formatDate, formatRub, formatRubMonthly } from '@/lib/format';

const NBSP = '\u00A0';

describe('formatRub', () => {
    it('форматирует нулевую цену', () => {
        expect(formatRub(0)).toBe(`0${NBSP}₽`);
    });

    it('трактует отрицательные значения как ноль', () => {
        expect(formatRub(-1500)).toBe(`0${NBSP}₽`);
    });

    it('трактует null, undefined и NaN как ноль', () => {
        expect(formatRub(null)).toBe(`0${NBSP}₽`);
        expect(formatRub(undefined)).toBe(`0${NBSP}₽`);
        expect(formatRub(Number.NaN)).toBe(`0${NBSP}₽`);
    });

    it('не использует компактную нотацию для больших сумм', () => {
        expect(formatRub(18_000_000)).toBe(`18${NBSP}000${NBSP}000${NBSP}₽`);
    });

    it('разделяет разряды неразрывными пробелами', () => {
        const formatted = formatRub(1_200_000);

        expect(formatted).toBe(`1${NBSP}200${NBSP}000${NBSP}₽`);
        expect(formatted).not.toContain(' ');
    });
});

describe('formatRubMonthly', () => {
    it('добавляет суффикс «₽/мес»', () => {
        expect(formatRubMonthly(85_000)).toBe(`85${NBSP}000${NBSP}₽/мес`);
    });

    it('трактует отрицательный доход как ноль', () => {
        expect(formatRubMonthly(-1)).toBe(`0${NBSP}₽/мес`);
    });
});

describe('formatCount', () => {
    it('форматирует количество без валюты', () => {
        expect(formatCount(60)).toBe('60');
        expect(formatCount(1240)).toBe(`1${NBSP}240`);
    });
});

describe('formatDate', () => {
    it('форматирует ISO-дату как ДД.ММ.ГГГГ', () => {
        expect(formatDate('2026-10-21T09:30:00Z')).toBe('21.10.2026');
    });

    it('возвращает пустую строку для некорректной даты', () => {
        expect(formatDate('not-a-date')).toBe('');
        expect(formatDate(null)).toBe('');
    });
});
