/**
 * Formats a date as "03 Jun, 2026" in Asia/Dhaka (matches purchase/sell lists).
 */
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
