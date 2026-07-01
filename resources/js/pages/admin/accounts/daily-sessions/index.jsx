import { Head, router } from '@inertiajs/react';
import { Eye, FileSpreadsheet, Loader2, RotateCcw } from 'lucide-react';
import { useState } from 'react';

import { BusinessSessionViewModal } from '@/components/admin/business-session-view-modal';
import { formatSessionMoney } from '@/components/admin/business-session-controls';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatBdDateLong, formatBdDateTimeLong } from '@/lib/format-bd-date';
import { route } from '@/lib/route';

export default function DailySessionsIndex({ sessions, filters, statusOptions }) {
    const [viewModal, setViewModal] = useState(null);
    const [viewOpen, setViewOpen] = useState(false);
    const [viewLoadingId, setViewLoadingId] = useState(null);

    const applyFilters = (next) => {
        router.get(route('accounts.daily-sessions.index'), { ...filters, ...next }, { preserveState: true, replace: true });
    };

    const openViewModal = async (session) => {
        if (viewLoadingId) {
            return;
        }

        setViewLoadingId(session.id);

        try {
            const response = await fetch(route('accounts.daily-sessions.report', { dailySession: session.id }), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Failed to load session report');
            }

            setViewModal({
                id: session.id,
                session_number: session.session_number,
                data: await response.json(),
            });
            setViewOpen(true);
        } catch {
            setViewModal(null);
        } finally {
            setViewLoadingId(null);
        }
    };

    return (
        <>
            <Head title="Daily Sessions" />

            <div className="min-w-0 px-2 py-1 sm:px-3">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-blue-950 px-4 py-3">
                    <div>
                        <h1 className="text-base font-semibold text-white">Daily Business Sessions</h1>
                        <p className="text-xs text-white/60">View and export previous session closing reports</p>
                    </div>
                </div>

                <div className="mb-4 grid gap-3 rounded-lg border bg-card p-4 sm:grid-cols-3">
                    <div>
                        <Label>Status</Label>
                        <Select value={filters.status || 'all'} onValueChange={(value) => applyFilters({ status: value === 'all' ? '' : value })}>
                            <SelectTrigger><SelectValue placeholder="All statuses" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                {statusOptions.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>Date from</Label>
                        <Input type="date" value={filters.date_from || ''} onChange={(e) => applyFilters({ date_from: e.target.value })} />
                    </div>
                    <div>
                        <Label>Date to</Label>
                        <Input type="date" value={filters.date_to || ''} onChange={(e) => applyFilters({ date_to: e.target.value })} />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-lg border bg-card">
                    <table className="min-w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2">Session #</th>
                                <th className="px-3 py-2">Date</th>
                                <th className="px-3 py-2">Branch</th>
                                <th className="px-3 py-2">Started By</th>
                                <th className="px-3 py-2">Start</th>
                                <th className="px-3 py-2">Close</th>
                                <th className="px-3 py-2 text-right">Opening</th>
                                <th className="px-3 py-2 text-right">Closing</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sessions.data.map((session) => (
                                <tr key={session.id} className="border-t">
                                    <td className="px-3 py-2 font-medium">{session.session_number}</td>
                                    <td className="px-3 py-2">{formatBdDateLong(session.session_date)}</td>
                                    <td className="px-3 py-2">{session.branch}</td>
                                    <td className="px-3 py-2">{session.started_by}</td>
                                    <td className="px-3 py-2">{formatBdDateTimeLong(session.started_at)}</td>
                                    <td className="px-3 py-2">{session.closed_at ? formatBdDateTimeLong(session.closed_at) : '—'}</td>
                                    <td className="px-3 py-2 text-right">{formatSessionMoney(session.opening_balance)}</td>
                                    <td className="px-3 py-2 text-right">{session.closing_balance != null ? formatSessionMoney(session.closing_balance) : '—'}</td>
                                    <td className="px-3 py-2">{session.status}</td>
                                    <td className="px-3 py-2">
                                        <div className="flex flex-wrap gap-1">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="h-7 rounded-md px-2"
                                                disabled={viewLoadingId === session.id}
                                                onClick={() => openViewModal(session)}
                                            >
                                                {viewLoadingId === session.id ? (
                                                    <Loader2 className="size-3.5 animate-spin" />
                                                ) : (
                                                    <Eye className="size-3.5" />
                                                )}
                                            </Button>
                                            <Button asChild size="sm" variant="outline" className="h-7 rounded-md px-2">
                                                <a href={route('accounts.daily-sessions.export-excel', { dailySession: session.id })}>
                                                    <FileSpreadsheet className="size-3.5" />
                                                </a>
                                            </Button>
                                            {session.can_reopen ? (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-7 rounded-md px-2"
                                                    onClick={() => router.post(route('accounts.daily-sessions.reopen', { dailySession: session.id }))}
                                                >
                                                    <RotateCcw className="size-3.5" />
                                                </Button>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <BusinessSessionViewModal
                open={viewOpen}
                onOpenChange={(open) => {
                    setViewOpen(open);

                    if (!open) {
                        setTimeout(() => setViewModal(null), 300);
                    }
                }}
                sessionId={viewModal?.id}
                sessionNumber={viewModal?.session_number}
                data={viewModal?.data}
            />
        </>
    );
}
