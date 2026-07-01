import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';

import { BusinessSessionExportButton } from '@/components/admin/business-session-export-button';
import { BusinessSessionReportContent } from '@/components/admin/business-session-report-content';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { route } from '@/lib/route';

import { VoucherModalShell } from '@/pages/admin/accounts/vouchers/voucher-modal-shell';

export function BusinessSessionCloseModal({ open, onOpenChange, data }) {
    const confirmedRef = useRef(false);
    const [confirming, setConfirming] = useState(false);
    const { can } = useCan();
    const report = data?.report;
    const session = data?.session;
    const info = report?.session ?? {};
    const canExport = can('business-session.export') && (data?.can_export ?? false);

    const handleOpenChange = (nextOpen) => {
        if (!nextOpen && !confirmedRef.current && data) {
            router.post(route('accounts.daily-sessions.close-cancel'), {}, { preserveScroll: true });
        }

        if (!nextOpen) {
            confirmedRef.current = false;
        }

        onOpenChange(nextOpen);
    };

    const confirmClose = () => {
        confirmedRef.current = true;
        setConfirming(true);
        router.post(route('accounts.daily-sessions.close-confirm'), {}, {
            preserveScroll: true,
            onFinish: () => {
                setConfirming(false);
                onOpenChange(false);
            },
        });
    };

    if (!data) {
        return null;
    }

    return (
        <VoucherModalShell
            open={open}
            onOpenChange={handleOpenChange}
            title="Close Business Session"
            subtitle={`${info.branch_name ?? '—'} · ${info.session_number ?? session?.session_number ?? '—'} · review before confirming`}
            maxWidthClass="sm:max-w-6xl"
            headerActions={(
                <BusinessSessionExportButton sessionId={session?.id} canExport={canExport} />
            )}
            footer={(
                <div className="flex flex-wrap justify-end gap-2 border-t px-5 py-4">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="rounded-md border-red-500 text-red-500 hover:bg-red-500 hover:text-white"
                        disabled={confirming}
                        onClick={() => handleOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        className="rounded-md bg-emerald-600 text-white hover:bg-emerald-700"
                        disabled={confirming}
                        onClick={confirmClose}
                    >
                        {confirming ? 'Closing…' : 'Confirm and Close Session'}
                    </Button>
                </div>
            )}
        >
            <div className="space-y-4 p-5">
                {report ? <BusinessSessionReportContent report={report} /> : null}
            </div>
        </VoucherModalShell>
    );
}
