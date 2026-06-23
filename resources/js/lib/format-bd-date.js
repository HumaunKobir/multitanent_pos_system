/**
 * Formats a date as "03 Jun, 2026" in Asia/Dhaka (matches purchase/sell lists).
 */
export const BD_TIMEZONE = 'Asia/Dhaka';

export function formatBdDate(date) {
    if (!date) {
        return '—';
    }

    let normalized = date;
    if (typeof date !== 'string') {
        if (date instanceof Date) {
            normalized = date.toISOString().slice(0, 10);
        } else {
            normalized = String(date);
        }
    }

    if (normalized.includes('T')) {
        normalized = normalized.slice(0, 10);
    }

    const parts = normalized.split('-');
    if (parts.length !== 3) {
        return normalized;
    }

    const [y, m, d] = parts.map((p) => Number(p));
    if (!y || !m || !d) {
        return normalized;
    }

    const utcMidnight = new Date(Date.UTC(y, m - 1, d));
    const dtf = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Dhaka',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
    const dateParts = dtf.formatToParts(utcMidnight);
    const day = dateParts.find((p) => p.type === 'day')?.value;
    const month = dateParts.find((p) => p.type === 'month')?.value;
    const year = dateParts.find((p) => p.type === 'year')?.value;

    return day && month && year ? `${day} ${month}, ${year}` : dtf.format(utcMidnight);
}

/**
 * Formats a datetime as "03:45 PM" in Asia/Dhaka.
 */
export function formatBdTime(value) {
    if (!value) {
        return '—';
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
        timeZone: BD_TIMEZONE,
    });
}

/**
 * Formats a datetime as "23 Jun 2026 03:45 PM" in Asia/Dhaka.
 */
export function formatBdDateTime(value) {
    if (!value) {
        return '—';
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    const datePart = date
        .toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            timeZone: BD_TIMEZONE,
        })
        .replace(',', '');

    return `${datePart} ${formatBdTime(date)}`;
}
