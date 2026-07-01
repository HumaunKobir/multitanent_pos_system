import { FileSpreadsheet } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { route } from '@/lib/route';

export function BusinessSessionExportButton({ sessionId, canExport }) {
    if (!canExport || !sessionId) {
        return null;
    }

    return (
        <Button
            asChild
            size="sm"
            className="h-8 gap-1.5 rounded-md border-0 bg-white/15 text-white hover:bg-white/25"
        >
            <a href={route('accounts.daily-sessions.export-excel', { dailySession: sessionId })}>
                <FileSpreadsheet className="size-3.5" />
                Export Excel
            </a>
        </Button>
    );
}
