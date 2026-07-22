import { Button } from '@/components/ui/button';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';

const TITLE_BY_TYPE = {
    income: 'Receipt voucher',
    expense: 'Expense voucher',
    journal: 'Journal voucher',
    contra: 'Contra voucher',
};

const PRINT_TITLE_BY_TYPE = {
    income: 'RECEIPT VOUCHER',
    expense: 'EXPENSE VOUCHER',
    journal: 'JOURNAL VOUCHER',
    contra: 'CONTRA VOUCHER',
};

function money(value) {
    return parseFloat(value ?? 0).toLocaleString('en-BD', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function DetailField({ label, children }) {
    return (
        <div className="min-w-0">
            <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">{label}</p>
            <div className="mt-1 text-sm font-semibold text-foreground">{children}</div>
        </div>
    );
}

function voucherHeadAccounts(voucher) {
    const creditLines = (voucher.lines ?? []).filter((line) => line.side === 'credit');
    const debitLines = (voucher.lines ?? []).filter((line) => line.side === 'debit');
    const creditNames = creditLines.map((line) => line.account_name).filter(Boolean).join(', ') || '—';
    const debitNames = debitLines.map((line) => line.account_name).filter(Boolean).join(', ') || '—';

    if (voucher.type_slug === 'income') {
        return {
            partyLabel: 'Received from',
            creditLabel: 'Account head (credit)',
            debitLabel: 'Received in (debit)',
            creditValue: creditNames,
            debitValue: voucher.payment_account_name || debitNames,
        };
    }

    if (voucher.type_slug === 'expense') {
        return {
            partyLabel: 'Paid to',
            creditLabel: 'Paid from (credit)',
            debitLabel: 'Account head (debit)',
            creditValue: voucher.payment_account_name || creditNames,
            debitValue: debitNames,
        };
    }

    if (voucher.type_slug === 'contra') {
        return {
            partyLabel: 'Party',
            creditLabel: 'To account (credit)',
            debitLabel: 'From account (debit)',
            creditValue: voucher.to_account_name || creditNames,
            debitValue: voucher.from_account_name || debitNames,
        };
    }

    return {
        partyLabel: 'Party',
        creditLabel: 'Credit accounts',
        debitLabel: 'Debit accounts',
        creditValue: creditNames,
        debitValue: debitNames,
    };
}

export default function VoucherShow({ voucher }) {
    const typeSlug = voucher.type_slug ?? 'journal';
    const title = TITLE_BY_TYPE[typeSlug] ?? 'Voucher';
    const printTitle = PRINT_TITLE_BY_TYPE[typeSlug] ?? 'VOUCHER';
    const heads = voucherHeadAccounts(voucher);
    const lines = voucher.lines ?? [];
    const totalDebit = lines
        .filter((line) => line.side === 'debit')
        .reduce((sum, line) => sum + parseFloat(line.amount ?? 0), 0);
    const totalCredit = lines
        .filter((line) => line.side === 'credit')
        .reduce((sum, line) => sum + parseFloat(line.amount ?? 0), 0);

    function handlePrint() {
        window.print();
    }

    return (
        <>
            <Head title={`${title} (${voucher.voucher_no})`} />

            <style>{`
                @media print {
                    @page {
                        size: A4;
                        margin: 16mm;
                    }
                    html, body {
                        width: 100% !important;
                        height: auto !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        background: white !important;
                    }
                    body * { visibility: hidden !important; }
                    #voucher-print-sheet,
                    #voucher-print-sheet * { visibility: visible !important; }
                    #voucher-print-sheet {
                        position: fixed !important;
                        inset: 0 !important;
                        display: flex !important;
                        justify-content: center !important;
                        align-items: flex-start !important;
                        width: 100% !important;
                        margin: 0 auto !important;
                        padding: 0 !important;
                        background: white !important;
                        color: black !important;
                        z-index: 9999 !important;
                    }
                    #voucher-print-inner {
                        width: 100% !important;
                        max-width: 700px !important;
                        margin: 0 auto !important;
                    }
                }
            `}</style>

            <div className="px-2 py-1 print:hidden">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex min-w-0 items-center gap-3">
                        <Button variant="outline" size="sm" asChild className="h-8 gap-1.5">
                            <Link href={route('accounts.vouchers.index', { query: { type: typeSlug } })}>
                                <ArrowLeft className="size-3.5" />
                                Back
                            </Link>
                        </Button>
                        <h1 className="truncate text-lg font-semibold text-foreground">
                            {title} ({voucher.voucher_no})
                        </h1>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        onClick={handlePrint}
                        className="h-9 gap-1.5 bg-teal-600 text-white hover:bg-teal-700"
                    >
                        <Printer className="size-4" />
                        Print
                    </Button>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div className="relative border-b border-border bg-muted/40 px-6 py-8 text-center">
                        <p className="absolute left-6 top-4 font-mono text-xs text-muted-foreground">#{voucher.voucher_no}</p>
                        <span className="absolute right-6 top-4 rounded-md border border-border bg-card px-2.5 py-1 text-xs font-medium text-muted-foreground">
                            {voucher.status ?? 'Active'}
                        </span>
                        <p className="text-4xl font-bold tracking-tight text-foreground">{money(voucher.total_amount)}</p>
                        <p className="mt-2 text-sm text-muted-foreground">{formatBdDate(voucher.date)}</p>
                    </div>

                    <div className="grid gap-8 px-6 py-6 sm:grid-cols-2">
                        <DetailField label={heads.partyLabel}>
                            <p>{voucher.party_name || '—'}</p>
                            {voucher.party_email ? (
                                <p className="mt-0.5 text-xs font-normal text-muted-foreground">{voucher.party_email}</p>
                            ) : null}
                        </DetailField>
                        <DetailField label="Transaction ref">
                            <span className="font-mono">{voucher.transaction_reference || '—'}</span>
                        </DetailField>
                        <DetailField label={heads.creditLabel}>{heads.creditValue}</DetailField>
                        <DetailField label={heads.debitLabel}>{heads.debitValue}</DetailField>
                    </div>

                    <div className="border-t border-border px-6 py-5">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Description</p>
                        <p className="mt-1 text-sm text-muted-foreground">{voucher.narration || '—'}</p>
                    </div>

                    {lines.length > 0 ? (
                        <div className="border-t border-border px-6 py-5">
                            <p className="mb-3 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                Entries
                            </p>
                            <div className="overflow-hidden rounded-lg border border-border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left text-xs uppercase tracking-wider text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-semibold">Account</th>
                                            <th className="px-3 py-2 text-right font-semibold">Debit</th>
                                            <th className="px-3 py-2 text-right font-semibold">Credit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {lines.map((line) => (
                                            <tr key={line.id} className="border-t border-border/60">
                                                <td className="px-3 py-2">{line.account_name || line.account_label || '—'}</td>
                                                <td className="px-3 py-2 text-right font-mono tabular-nums">
                                                    {line.side === 'debit' ? money(line.amount) : ''}
                                                </td>
                                                <td className="px-3 py-2 text-right font-mono tabular-nums">
                                                    {line.side === 'credit' ? money(line.amount) : ''}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : null}
                </div>
            </div>

            <div id="voucher-print-sheet" className="hidden print:block">
                <div id="voucher-print-inner" className="mx-auto w-full max-w-[700px] text-black">
                    <div className="mb-8 text-center">
                        <h1 className="text-2xl font-bold tracking-wide">{printTitle}</h1>
                        <p className="mt-1 text-base font-semibold">{voucher.voucher_no}</p>
                    </div>

                    <div className="mb-6 space-y-1 text-sm">
                        <p>
                            <span className="font-semibold">Date:</span> {formatBdDate(voucher.date)}
                        </p>
                        <p>
                            <span className="font-semibold">Status:</span> {voucher.status ?? 'Active'}
                        </p>
                        <p>
                            <span className="font-semibold">
                                {typeSlug === 'expense' ? 'Paid To:' : typeSlug === 'income' ? 'Received From:' : 'Party:'}
                            </span>{' '}
                            {voucher.party_name || '—'}
                            {voucher.party_email ? ` (${voucher.party_email})` : ''}
                        </p>
                        <p>
                            <span className="font-semibold">Reference:</span> {voucher.transaction_reference || '—'}
                        </p>
                    </div>

                    <table className="mb-4 w-full border-collapse text-sm">
                        <thead>
                            <tr className="bg-neutral-100">
                                <th className="border-b border-neutral-300 px-3 py-2 text-left font-semibold">Account</th>
                                <th className="border-b border-neutral-300 px-3 py-2 text-right font-semibold">Debit</th>
                                <th className="border-b border-neutral-300 px-3 py-2 text-right font-semibold">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            {lines.map((line) => (
                                <tr key={`print-${line.id}`}>
                                    <td className="border-b border-neutral-200 px-3 py-2">
                                        {line.account_name || line.account_label || '—'}
                                    </td>
                                    <td className="border-b border-neutral-200 px-3 py-2 text-right font-mono">
                                        {line.side === 'debit' ? money(line.amount) : ''}
                                    </td>
                                    <td className="border-b border-neutral-200 px-3 py-2 text-right font-mono">
                                        {line.side === 'credit' ? money(line.amount) : ''}
                                    </td>
                                </tr>
                            ))}
                            <tr>
                                <td className="border-t-2 border-double border-neutral-800 px-3 py-2 font-semibold">
                                    Total Amount:
                                </td>
                                <td className="border-t-2 border-double border-neutral-800 px-3 py-2 text-right font-mono font-semibold">
                                    {money(totalDebit || voucher.total_amount)}
                                </td>
                                <td className="border-t-2 border-double border-neutral-800 px-3 py-2 text-right font-mono font-semibold">
                                    {money(totalCredit || voucher.total_amount)}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p className="mb-16 text-sm">
                        <span className="font-semibold">Description:</span> {voucher.narration || '—'}
                    </p>

                    <div className="mt-20 grid grid-cols-2 gap-16 text-center text-sm">
                        <div>
                            <div className="mb-2 border-t border-neutral-800 pt-2">Prepared By</div>
                            <p className="text-xs text-neutral-600">{voucher.created_by_name || ''}</p>
                        </div>
                        <div>
                            <div className="mb-2 border-t border-neutral-800 pt-2">Approved By</div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
