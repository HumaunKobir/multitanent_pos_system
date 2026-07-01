import { BusinessSessionExportButton } from '@/components/admin/business-session-export-button';
import { BusinessSessionReportContent } from '@/components/admin/business-session-report-content';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';

import { VoucherModalShell } from '@/pages/admin/accounts/vouchers/voucher-modal-shell';

export function BusinessSessionViewModal({ open, onOpenChange, sessionId, sessionNumber, data }) {
    const { can } = useCan();
    const report = data?.report;
    const info = report?.session ?? {};
    const canExport = can('business-session.export') && (data?.can_export ?? false);

    const handleOpenChange = (nextOpen) => {
        if (!nextOpen) {
            onOpenChange(false);
        }
    };

    if (!data) {
        return null;
    }

    return (
        <VoucherModalShell
            open={open}
            onOpenChange={handleOpenChange}
            title={`Session Report — ${sessionNumber ?? info.session_number ?? ''}`}
            subtitle={`${info.branch_name ?? '—'} · ${info.status ?? '—'}`}
            maxWidthClass="sm:max-w-6xl"
            headerActions={(
                <BusinessSessionExportButton sessionId={sessionId} canExport={canExport} />
            )}
            footer={(
                <div className="flex justify-end border-t px-5 py-4">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="rounded-md"
                        onClick={() => handleOpenChange(false)}
                    >
                        Close
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
