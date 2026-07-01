import { formatSessionMoney } from '@/components/admin/business-session-controls';
import { cn } from '@/lib/utils';
import { usePage } from '@inertiajs/react';

function SectionTable({ title, count, headers, rows, renderRow, minWidth = '36rem' }) {
    return (
        <section className="space-y-2">
            <div className="flex items-center justify-between gap-2">
                <h3 className="text-sm font-semibold">{title}</h3>
                {typeof count === 'number' ? (
                    <span className="text-xs text-muted-foreground">
                        {count} record{count === 1 ? '' : 's'}
                    </span>
                ) : null}
            </div>
            <div className="overflow-hidden rounded-lg border bg-card">
                <div className="max-h-[min(40vh,22rem)] overflow-auto">
                    <table className="w-full text-sm" style={{ minWidth }}>
                        <thead className="sticky top-0 z-10 bg-muted/95 text-left text-xs uppercase tracking-wide text-muted-foreground backdrop-blur-sm">
                            <tr>
                                {headers.map((header) => (
                                    <th
                                        key={header}
                                        className={cn(
                                            'px-3 py-2.5',
                                            ['Opening', 'Received', 'Paid', 'Closing', 'Amount', 'Total'].includes(header) && 'text-right',
                                        )}
                                    >
                                        {header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan={headers.length} className="px-3 py-8 text-center text-muted-foreground">
                                        No records
                                    </td>
                                </tr>
                            ) : (
                                rows.map(renderRow)
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    );
}

function SummaryCard({ label, value }) {
    return (
        <div className="rounded-lg border bg-card p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-base font-semibold tabular-nums">{formatSessionMoney(value)}</p>
        </div>
    );
}

export function BusinessSessionReportContent({ report }) {
    const { panelType } = usePage().props;
    const showIncomeExpenseSummaries = panelType === 'admin';
    const info = report?.session ?? {};
    const closing = report?.closing_summary ?? {};
    const accountRows = report?.account_balances ?? [];
    const transactionRows = report?.transactions ?? [];
    const transferRows = report?.transfers ?? [];
    const incomeRows = report?.income_summary ?? [];
    const expenseRows = report?.expense_summary ?? [];

    return (
        <div className="space-y-4">
            <div className="grid gap-3 rounded-lg border bg-card p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <span className="text-muted-foreground">Branch</span>
                    <p className="font-medium">{info.branch_name ?? '—'}</p>
                </div>
                <div>
                    <span className="text-muted-foreground">Start Time</span>
                    <p className="font-medium">{info.started_at ?? '—'}</p>
                </div>
                <div>
                    <span className="text-muted-foreground">Close Time</span>
                    <p className="font-medium">{info.closed_at ?? '—'}</p>
                </div>
                <div>
                    <span className="text-muted-foreground">Duration</span>
                    <p className="font-medium">{info.duration ?? '—'}</p>
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <SummaryCard label="Total Opening" value={closing.total_opening_balance} />
                <SummaryCard label="Total Receipts" value={closing.total_receipts} />
                <SummaryCard label="Total Payments" value={closing.total_payments} />
                <SummaryCard label="Total Closing" value={closing.total_closing_balance} />
            </div>

            <SectionTable
                title="Account Balances"
                count={accountRows.length}
                minWidth="42rem"
                headers={['Account', 'Type', 'Opening', 'Received', 'Paid', 'Closing']}
                rows={accountRows}
                renderRow={(row) => (
                    <tr key={row.account_id} className="border-t hover:bg-muted/30">
                        <td className="px-3 py-2.5 font-medium">{row.account_name}</td>
                        <td className="px-3 py-2.5 text-muted-foreground">{row.account_type}</td>
                        <td className="px-3 py-2.5 text-right tabular-nums">{formatSessionMoney(row.opening_balance)}</td>
                        <td className="px-3 py-2.5 text-right tabular-nums">{formatSessionMoney(row.total_received)}</td>
                        <td className="px-3 py-2.5 text-right tabular-nums">{formatSessionMoney(row.total_paid)}</td>
                        <td className="px-3 py-2.5 text-right font-semibold tabular-nums">{formatSessionMoney(row.closing_balance)}</td>
                    </tr>
                )}
            />

            <SectionTable
                title="Contra Voucher (Bank/Cash Transfers)"
                count={transferRows.length}
                minWidth="44rem"
                headers={['Date', 'Reference', 'From', 'To', 'Amount', 'Remarks']}
                rows={transferRows}
                renderRow={(row, index) => (
                    <tr key={`${row.reference}-${index}`} className="border-t hover:bg-muted/30">
                        <td className="px-3 py-2.5 whitespace-nowrap">{row.date} {row.time}</td>
                        <td className="px-3 py-2.5">{row.reference}</td>
                        <td className="px-3 py-2.5">{row.from_account}</td>
                        <td className="px-3 py-2.5">{row.to_account}</td>
                        <td className="px-3 py-2.5 text-right tabular-nums">{formatSessionMoney(row.amount)}</td>
                        <td className="max-w-48 truncate px-3 py-2.5" title={row.remarks}>{row.remarks}</td>
                    </tr>
                )}
            />

            <SectionTable
                title="Transactions"
                count={transactionRows.length}
                minWidth="48rem"
                headers={['Date', 'Reference', 'Type', 'From', 'To', 'Description', 'Amount']}
                rows={transactionRows}
                renderRow={(row) => (
                    <tr key={row.id} className="border-t hover:bg-muted/30">
                        <td className="px-3 py-2.5 whitespace-nowrap">{row.date} {row.time}</td>
                        <td className="px-3 py-2.5">{row.reference}</td>
                        <td className="px-3 py-2.5">{row.type}</td>
                        <td className="max-w-40 truncate px-3 py-2.5" title={row.source_account}>{row.source_account}</td>
                        <td className="max-w-40 truncate px-3 py-2.5" title={row.destination_account}>{row.destination_account}</td>
                        <td className="max-w-48 truncate px-3 py-2.5" title={row.description}>
                            {row.description}
                            {row.is_deleted ? ' (Deleted)' : ''}
                        </td>
                        <td className="px-3 py-2.5 text-right tabular-nums">{formatSessionMoney(row.debit)}</td>
                    </tr>
                )}
            />

            {showIncomeExpenseSummaries ? (
                <>
                    <SectionTable
                        title="Income Summary"
                        count={incomeRows.length}
                        minWidth="40rem"
                        headers={['Category', 'Payment Account', 'Count', 'Total']}
                        rows={incomeRows}
                        renderRow={(row, index) => (
                            <tr key={`${row.category}-${row.payment_account}-${index}`} className="border-t hover:bg-muted/30">
                                <td className="px-3 py-2.5">{row.category}</td>
                                <td className="px-3 py-2.5">{row.payment_account}</td>
                                <td className="px-3 py-2.5">{row.transaction_count}</td>
                                <td className="px-3 py-2.5 text-right tabular-nums">{formatSessionMoney(row.total_amount)}</td>
                            </tr>
                        )}
                    />

                    <SectionTable
                        title="Expense Summary"
                        count={expenseRows.length}
                        minWidth="40rem"
                        headers={['Category', 'Payment Account', 'Count', 'Total']}
                        rows={expenseRows}
                        renderRow={(row, index) => (
                            <tr key={`${row.category}-${row.payment_account}-${index}`} className="border-t hover:bg-muted/30">
                                <td className="px-3 py-2.5">{row.category}</td>
                                <td className="px-3 py-2.5">{row.payment_account}</td>
                                <td className="px-3 py-2.5">{row.transaction_count}</td>
                                <td className="px-3 py-2.5 text-right tabular-nums">{formatSessionMoney(row.total_amount)}</td>
                            </tr>
                        )}
                    />
                </>
            ) : null}
        </div>
    );
}
