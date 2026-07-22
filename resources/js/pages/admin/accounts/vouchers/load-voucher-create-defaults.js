import { route } from '@/lib/route';

/**
 * Load a fresh auto voucher number / transaction reference for create forms.
 *
 * @param {'journal'|'income'|'expense'|'contra'} typeSlug
 * @param {{ voucher_no?: string, transaction_reference?: string, date?: string }} fallback
 */
export async function loadVoucherCreateDefaults(typeSlug, fallback = {}) {
    try {
        const response = await fetch(route('accounts.vouchers.next-number', { query: { type: typeSlug } }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return fallback;
        }

        const data = await response.json();

        return {
            voucher_no: data.voucher_no ?? fallback.voucher_no ?? '',
            transaction_reference: data.transaction_reference ?? fallback.transaction_reference ?? '',
            date: fallback.date ?? '',
        };
    } catch {
        return fallback;
    }
}
