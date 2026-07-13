import { formatBdDate } from '@/lib/format-bd-date';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/** Rows are keyed by `key` when present — a sale and an exchange can share a raw id. */
export function allocationRowKey(row) {
    return row.key ?? row.id;
}

export function buildAllocations(idField, rows, amountsById) {
    return rows
        .map((row) => ({
            [idField]: row.id,
            amount: parseFloat(amountsById[allocationRowKey(row)] ?? 0) || 0,
        }))
        .filter((row) => row.amount > 0);
}

/**
 * Customer collections settle either a due sale or an exchange overpayment, so the
 * id field depends on the row's type rather than being fixed.
 */
export function buildCustomerAllocations(rows, amountsById) {
    return rows
        .map((row) => ({
            sell_id: row.type === 'exchange' ? null : row.id,
            product_exchange_id: row.type === 'exchange' ? row.id : null,
            amount: parseFloat(amountsById[allocationRowKey(row)] ?? 0) || 0,
        }))
        .filter((row) => row.amount > 0);
}

export function allocationTotal(amountsById) {
    return Object.values(amountsById).reduce((sum, value) => sum + (parseFloat(value) || 0), 0);
}

export function allocationAmountsFromApiDocuments(documents = []) {
    return Object.fromEntries(
        documents
            .filter((doc) => parseFloat(doc.allocated_amount) > 0)
            .map((doc) => [allocationRowKey(doc), String(doc.allocated_amount)]),
    );
}

export function PaymentAllocationTable({
    documents = [],
    idField,
    amountsById,
    onAmountChange,
    onPayFull,
    loading = false,
    emptyMessage = 'No due invoices found.',
}) {
    if (loading) {
        return <p className="rounded-md border px-3 py-4 text-center text-xs text-muted-foreground">Loading due invoices…</p>;
    }

    if (documents.length === 0) {
        return <p className="rounded-md border border-dashed px-3 py-4 text-center text-xs text-muted-foreground">{emptyMessage}</p>;
    }

    return (
        <div className="overflow-hidden rounded-md border">
            <table className="w-full text-xs">
                <thead className="bg-muted/50 text-left">
                    <tr>
                        <th className="px-2 py-2 font-medium">Invoice</th>
                        <th className="px-2 py-2 font-medium">Date</th>
                        <th className="px-2 py-2 text-right font-medium">Due</th>
                        <th className="px-2 py-2 text-right font-medium">Pay</th>
                    </tr>
                </thead>
                <tbody>
                    {documents.map((doc) => {
                        const rowKey = allocationRowKey(doc);

                        return (
                            <tr key={rowKey} className="border-t">
                                <td className="px-2 py-2 font-mono font-medium">
                                    <div>{doc.invoice_number}</div>
                                    {doc.purchase_type_label && (
                                        <div className="font-sans text-[10px] font-normal text-muted-foreground">{doc.purchase_type_label}</div>
                                    )}
                                    {doc.document_label && (
                                        <div className="font-sans text-[10px] font-normal text-amber-700 dark:text-amber-400">
                                            {doc.document_label}
                                        </div>
                                    )}
                                </td>
                                <td className="px-2 py-2 text-muted-foreground">{formatBdDate(doc.date)}</td>
                                <td className="px-2 py-2 text-right">৳{parseFloat(doc.due_amount).toFixed(2)}</td>
                                <td className="px-2 py-2">
                                    <div className="flex items-center justify-end gap-1">
                                        <Input
                                            type="number"
                                            min="0"
                                            max={doc.due_amount}
                                            step="0.01"
                                            value={amountsById[rowKey] ?? ''}
                                            onChange={(e) => onAmountChange(rowKey, e.target.value)}
                                            placeholder="0"
                                            className="h-8 w-24 text-right"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-8 px-2 text-[10px]"
                                            onClick={() => onPayFull(rowKey, doc.due_amount)}
                                        >
                                            Full
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
