import { formatBdDate } from '@/lib/format-bd-date';

function PaymentLine({ label, accountLabel, amount, meta }) {
    return (
        <div className="flex items-start justify-between gap-3 border-b border-border/60 py-1.5 last:border-0">
            <div className="min-w-0">
                <p className="font-medium text-foreground">{label}</p>
                {accountLabel && <p className="text-xs text-muted-foreground">{accountLabel}</p>}
                {meta && <p className="text-[10px] text-muted-foreground">{meta}</p>}
            </div>
            <span className="shrink-0 font-medium tabular-nums">৳{parseFloat(amount ?? 0).toFixed(2)}</span>
        </div>
    );
}

export function DocumentPaymentBreakdown({
    title = 'Payment Breakdown',
    directPayment = null,
    directPaymentLabel = 'Direct Payment',
    partyPayments = [],
    partyPaymentLabel = 'Party Payment',
}) {
    const hasDirect = directPayment && parseFloat(directPayment.amount ?? 0) > 0;
    const hasParty = partyPayments.length > 0;

    if (!hasDirect && !hasParty) {
        return null;
    }

    return (
        <div className="mx-auto mt-3 max-w-4xl border border-blue-200 bg-white p-3 shadow-sm">
            <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-blue-950">{title}</h3>
            <div className="space-y-0 text-sm">
                {hasDirect && (
                    <PaymentLine
                        label={directPaymentLabel}
                        accountLabel={directPayment.payment_account_label}
                        amount={directPayment.amount}
                    />
                )}
                {partyPayments.map((line, index) => (
                    <PaymentLine
                        key={`${line.voucher}-${index}`}
                        label={`${partyPaymentLabel} — ${line.voucher}`}
                        accountLabel={line.payment_account_label}
                        amount={line.amount}
                        meta={line.date ? formatBdDate(line.date) : null}
                    />
                ))}
            </div>
        </div>
    );
}

export function AllocationSummaryTable({ title, rows, documentLabel, amountLabel = 'This Payment' }) {
    if (!rows?.length) {
        return null;
    }

    return (
        <div>
            <p className="mb-2 text-sm font-medium">{title}</p>
            <div className="overflow-hidden rounded-md border">
                <table className="w-full text-xs">
                    <thead className="bg-muted/50 text-left">
                        <tr>
                            <th className="px-3 py-2 font-medium">{documentLabel}</th>
                            <th className="px-3 py-2 text-right font-medium">Net</th>
                            <th className="px-3 py-2 text-right font-medium">Paid</th>
                            <th className="px-3 py-2 text-right font-medium">Due</th>
                            <th className="px-3 py-2 text-right font-medium">{amountLabel}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((allocation) => (
                            <tr key={allocation.id} className="border-t">
                                <td className="px-3 py-2 font-mono">
                                    {allocation.document?.invoice_number ?? '—'}
                                    {allocation.type === 'exchange' && (
                                        <div className="font-sans text-[10px] text-amber-700 dark:text-amber-400">
                                            Exchange overpayment
                                        </div>
                                    )}
                                </td>
                                <td className="px-3 py-2 text-right tabular-nums">
                                    ৳{parseFloat(allocation.document?.net_amount ?? 0).toFixed(2)}
                                </td>
                                <td className="px-3 py-2 text-right tabular-nums">
                                    ৳{parseFloat(allocation.document?.paid_amount ?? 0).toFixed(2)}
                                </td>
                                <td className="px-3 py-2 text-right tabular-nums text-destructive">
                                    ৳{parseFloat(allocation.document?.due_amount ?? 0).toFixed(2)}
                                </td>
                                <td className="px-3 py-2 text-right font-medium tabular-nums text-emerald-700">
                                    ৳{parseFloat(allocation.amount).toFixed(2)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
