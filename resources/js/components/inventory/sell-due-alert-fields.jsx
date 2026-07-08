import { route } from '@/lib/route';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useEffect } from 'react';

export function SellDueAlertFields({ customerId, walkInCustomerId, dueAmount, form }) {
    const showDueDateField = dueAmount > 0;
    const canManageAlert =
        showDueDateField &&
        customerId &&
        String(customerId) !== String(walkInCustomerId ?? '');

    useEffect(() => {
        if (!showDueDateField) {
            form.setData((d) => ({ ...d, due_given_date: '', due_alert_action: '' }));
            return;
        }

        if (!canManageAlert) {
            form.setData((d) => ({ ...d, due_alert_action: '' }));
            return;
        }

        let cancelled = false;

        async function fetchActiveAlert() {
            try {
                const res = await fetch(route('api.customers.due-alert', { customer: customerId }), {
                    credentials: 'include',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok || cancelled) {
                    return;
                }
                const payload = await res.json();
                if (!cancelled) {
                    if (payload.active) {
                        form.setData((d) => ({
                            ...d,
                            due_alert_action: d.due_alert_action || 'merge',
                            due_given_date: d.due_given_date || payload.active.due_given_date || '',
                        }));
                    } else {
                        form.setData((d) => ({ ...d, due_alert_action: '' }));
                    }
                }
            } catch {
                // Ignore fetch errors; the sale can still be saved without an alert action.
            }
        }

        fetchActiveAlert();

        return () => {
            cancelled = true;
        };
    }, [customerId, showDueDateField]);

    if (!showDueDateField) {
        return null;
    }

    return (
        <div className="space-y-1.5 border border-amber-200 bg-amber-50/70 px-1.5 py-1.5 lg:px-2 lg:py-2">
            <div>
                <Label className="mb-0.5 block text-[9px] text-muted-foreground lg:text-[10px]">
                    Due Given Date <span className="text-destructive">*</span>
                </Label>
                <Input
                    type="date"
                    value={form.data.due_given_date}
                    onChange={(e) => form.setData('due_given_date', e.target.value)}
                    className="h-7 text-[11px] lg:h-8 lg:text-xs"
                    aria-invalid={!!form.errors.due_given_date}
                    required
                />
                {form.errors.due_given_date && (
                    <p className="mt-0.5 text-[10px] text-destructive">{form.errors.due_given_date}</p>
                )}
            </div>
        </div>
    );
}
