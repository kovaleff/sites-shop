import { postJson } from '@/services/http';

/**
 * Причины жалобы. Значения совпадают с App\Enums\ReportReason.
 *
 * @type {Array<{ value: string, label: string }>}
 */
export const REPORT_REASONS = [
    { value: 'spam', label: 'Спам или реклама' },
    { value: 'fraud', label: 'Мошенничество' },
    { value: 'wrong_info', label: 'Недостоверные данные' },
    { value: 'forbidden', label: 'Запрещённый контент' },
    { value: 'other', label: 'Другое' },
];

/**
 * Жалоба на объявление из каталога.
 *
 * @param {number} siteId
 * @param {{ reason: string, comment?: string }} payload
 * @returns {Promise<{ data: { id: number, status: string } }>}
 */
export function reportSite(siteId, { reason, comment = '' }) {
    return postJson(`/api/v1/sites/${siteId}/reports`, {
        reason,
        comment: comment.trim() === '' ? null : comment.trim(),
    });
}
