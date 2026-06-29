/**
 * Formats a date as "03 Jun, 2026" in Asia/Dhaka (matches purchase/sell lists).
 */
export const BD_TIMEZONE = 'Asia/Dhaka';

const BD_DATE_FORMATTER = new Intl.DateTimeFormat('en-GB', {
    timeZone: BD_TIMEZONE,
    day: '2-digit',
    month: 'short',
    year: 'numeric',
});

function formatDateInBdTimezone(date) {
    const dateParts = BD_DATE_FORMATTER.formatToParts(date);
    const day = dateParts.find((p) => p.type === 'day')?.value;
    const month = dateParts.find((p) => p.type === 'month')?.value;
    const year = dateParts.find((p) => p.type === 'year')?.value;

    return day && month && year ? `${day} ${month}, ${year}` : BD_DATE_FORMATTER.format(date);
}

export function formatBdDate(date) {
    if (!date) {
        return '—';
    }

    if (typeof date === 'string') {
        const trimmed = date.trim();

        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            const [y, m, d] = trimmed.split('-').map((p) => Number(p));

            if (!y || !m || !d) {
                return trimmed;
            }

            return formatDateInBdTimezone(new Date(Date.UTC(y, m - 1, d)));
        }

        const parsed = new Date(trimmed);

        if (!Number.isNaN(parsed.getTime())) {
            return formatDateInBdTimezone(parsed);
        }

        return trimmed;
    }

    if (date instanceof Date) {
        if (Number.isNaN(date.getTime())) {
            return '—';
        }

        return formatDateInBdTimezone(date);
    }

    return String(date);
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
