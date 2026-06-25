export function emptyWhenZero(value) {
    const parsed = parseFloat(value ?? 0);

    if (!Number.isFinite(parsed) || parsed === 0) {
        return '';
    }

    return String(value);
}

export function normalizeOptionalNumeric(value) {
    return value === '' || value === null || value === undefined ? '0' : String(value);
}
