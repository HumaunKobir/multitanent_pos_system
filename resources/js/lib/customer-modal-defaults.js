export function customerModalDefaultsFromSearch(query) {
    const trimmed = query.trim();
    const normalized = trimmed.replace(/[\s\-+]/g, '');

    if (normalized.length > 0 && /^\d+$/.test(normalized)) {
        return { name: '', phone: normalized, email: '', address: '' };
    }

    return { name: trimmed, phone: '', email: '', address: '' };
}
