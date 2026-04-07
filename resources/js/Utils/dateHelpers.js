/**
 * Centralized date formatting utilities for pt-BR locale.
 *
 * Usage:
 *   import { formatDate, formatDateTime, formatDateShort } from '@/Utils/dateHelpers';
 *   formatDateTime('2026-03-24T20:17:21.000000Z') // "24/03/2026 20:17:21"
 */

/**
 * Parse a value into a Date object. Returns null for falsy/invalid values.
 */
function toDate(value) {
    if (!value) return null;
    const d = new Date(value);
    return isNaN(d.getTime()) ? null : d;
}

/**
 * Format as "dd/mm/aaaa" (date only).
 */
export function formatDate(value) {
    const d = toDate(value);
    if (!d) return '-';
    return d.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

/**
 * Format as "dd/mm/aaaa hh:mm:ss" (full datetime).
 */
export function formatDateTime(value) {
    const d = toDate(value);
    if (!d) return '-';
    return d.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

/**
 * Format as "dd/mm/aaaa hh:mm" (datetime without seconds).
 */
export function formatDateTimeShort(value) {
    const d = toDate(value);
    if (!d) return '-';
    return d.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Format as "dd/mm/yy" (short date).
 */
export function formatDateShort(value) {
    const d = toDate(value);
    if (!d) return '-';
    return d.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
    });
}

/**
 * Format as relative time ("há 5 min", "há 2h", "há 3 dias").
 */
export function formatTimeAgo(value) {
    const d = toDate(value);
    if (!d) return '-';

    const now = new Date();
    const diffMs = now - d;
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHours = Math.floor(diffMin / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffSec < 60) return 'agora';
    if (diffMin < 60) return `há ${diffMin} min`;
    if (diffHours < 24) return `há ${diffHours}h`;
    if (diffDays < 30) return `há ${diffDays}d`;
    return formatDate(value);
}
