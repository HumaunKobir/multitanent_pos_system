import { FileSpreadsheet, FileText, Printer } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { route } from '@/lib/route';

/**
 * Date range filters + PDF / Excel / Print export actions for admin list pages.
 */
export function ListDateExportBar({
    dateFrom,
    dateTo,
    onDateFromChange,
    onDateToChange,
    exportExcelRoute,
    exportPdfRoute,
    exportPrintRoute,
    query = {},
    className = '',
}) {
    function buildUrl(routeName) {
        const params = new URLSearchParams();

        Object.entries({
            ...query,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
        }).forEach(([key, value]) => {
            if (value != null && value !== '' && value !== '__all' && value !== 'all') {
                params.set(key, String(value));
            }
        });

        const qs = params.toString();

        return route(routeName) + (qs ? `?${qs}` : '');
    }

    function handleExcel() {
        window.location.href = buildUrl(exportExcelRoute);
    }

    function handlePdf() {
        window.location.href = buildUrl(exportPdfRoute);
    }

    function handlePrint() {
        window.open(buildUrl(exportPrintRoute), '_blank', 'noopener,noreferrer');
    }

    return (
        <div className={`flex flex-wrap items-end gap-2 ${className}`.trim()}>
            <div className="space-y-1">
                <Label className="text-[11px] text-muted-foreground">From Date</Label>
                <Input
                    type="date"
                    value={dateFrom}
                    onChange={(e) => onDateFromChange(e.target.value)}
                    className="h-9 w-40"
                />
            </div>
            <div className="space-y-1">
                <Label className="text-[11px] text-muted-foreground">To Date</Label>
                <Input
                    type="date"
                    value={dateTo}
                    onChange={(e) => onDateToChange(e.target.value)}
                    className="h-9 w-40"
                />
            </div>
            <div className="flex flex-wrap items-center gap-1.5">
                <Button type="button" variant="outline" size="sm" onClick={handlePdf}>
                    <FileText className="size-3.5" />
                    PDF
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={handleExcel}>
                    <FileSpreadsheet className="size-3.5" />
                    Excel
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={handlePrint}>
                    <Printer className="size-3.5" />
                    Print
                </Button>
            </div>
        </div>
    );
}
