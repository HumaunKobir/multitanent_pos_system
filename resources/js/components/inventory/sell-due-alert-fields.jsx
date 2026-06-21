import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useEffect, useState } from 'react';

export function SellDueAlertFields({ customerId, walkInCustomerId, dueAmount, form }) {
    const [activeAlert, setActiveAlert] = useState(null);
    const [loadingAlert, setLoadingAlert] = useState(false);
    const showDueDateField = dueAmount > 0;
    const canManageAlert =
        showDueDateField &&
        customerId &&
        String(customerId) !== String(walkInCustomerId ?? '');

    useEffect(() => {
        if (!showDueDateField) {
            setActiveAlert(null);
            form.setData((d) => ({ ...d, due_given_date: '', due_alert_action: '' }));
            return;
        }

        if (!canManageAlert) {
            setActiveAlert(null);
            form.setData((d) => ({ ...d, due_alert_action: '' }));
            return;
        }

        let cancelled = false;

        async function fetchActiveAlert() {
            setLoadingAlert(true);
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
                    setActiveAlert(payload.active ?? null);
                    if (payload.active) {
                        form.setData((d) => ({ ...d, due_alert_action: d.due_alert_action || 'merge' }));
                    } else {
                        form.setData((d) => ({ ...d, due_alert_action: '' }));
                    }
                }
            } catch {
                if (!cancelled) {
                    setActiveAlert(null);
                }
            } finally {
                if (!cancelled) {
                    setLoadingAlert(false);
                }
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
                    Due Given Date
                </Label>
                <Input
                    type="date"
                    value={form.data.due_given_date}
                    onChange={(e) => form.setData('due_given_date', e.target.value)}
                    className="h-7 text-[11px] lg:h-8 lg:text-xs"
                    aria-invalid={!!form.errors.due_given_date}
                />
                {form.errors.due_given_date && (
                    <p className="mt-0.5 text-[10px] text-destructive">{form.errors.due_given_date}</p>
                )}
            </div>

            {canManageAlert && loadingAlert && (
                <p className="text-[10px] text-muted-foreground">Checking existing due alerts…</p>
            )}

            {canManageAlert && activeAlert && form.data.due_given_date && (
                <div className="space-y-1 border-t border-amber-200/80 pt-1.5">
                    <p className="text-[10px] font-medium text-amber-900">
                        Active alert found ({formatBdDate(activeAlert.due_given_date)}). How should this due be handled?
                    </p>
                    <label className="flex cursor-pointer items-start gap-2 text-[10px] text-amber-950 lg:text-[11px]">
                        <input
                            type="radio"
                            name="due_alert_action"
                            value="merge"
                            checked={form.data.due_alert_action === 'merge'}
                            onChange={() => form.setData('due_alert_action', 'merge')}
                            className="mt-0.5"
                        />
                        <span>Merge with existing alert (update due date)</span>
                    </label>
                    <label className="flex cursor-pointer items-start gap-2 text-[10px] text-amber-950 lg:text-[11px]">
                        <input
                            type="radio"
                            name="due_alert_action"
                            value="separate"
                            checked={form.data.due_alert_action === 'separate'}
                            onChange={() => form.setData('due_alert_action', 'separate')}
                            className="mt-0.5"
                        />
                        <span>Create a separate due alert</span>
                    </label>
                    {form.errors.due_alert_action && (
                        <p className="text-[10px] text-destructive">{form.errors.due_alert_action}</p>
                    )}
                </div>
            )}
        </div>
    );
}
