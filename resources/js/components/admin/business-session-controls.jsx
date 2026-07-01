import { router, usePage } from '@inertiajs/react';
import { Clock, Loader2, PlayCircle, Square } from 'lucide-react';
import { useState } from 'react';

import { BusinessSessionCloseModal } from '@/components/admin/business-session-close-modal';
import { Button } from '@/components/ui/button';
import { route } from '@/lib/route';
import { cn } from '@/lib/utils';

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-BD', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export function BusinessSessionControls({ className, compact = false }) {
    const { businessSession } = usePage().props;
    const [closeOpen, setCloseOpen] = useState(false);
    const [openingClose, setOpeningClose] = useState(false);
    const [closeData, setCloseData] = useState(null);

    if (!businessSession) {
        return null;
    }

    const startSession = () => {
        router.post(route('accounts.daily-sessions.store'), {}, { preserveScroll: true });
    };

    const openCloseModal = async () => {
        if (openingClose) {
            return;
        }

        setOpeningClose(true);
        setCloseData(null);

        try {
            const response = await fetch(route('accounts.daily-sessions.close-preview'), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Failed to load closing summary');
            }

            setCloseData(await response.json());
            setCloseOpen(true);
        } catch {
            setCloseOpen(false);
        } finally {
            setOpeningClose(false);
        }
    };

    const handleCloseModalChange = (open) => {
        setCloseOpen(open);

        if (!open) {
            setTimeout(() => setCloseData(null), 300);
        }
    };

    if (businessSession.active) {
        const canOpenCloseModal = businessSession.can_close || businessSession.can_resume_close;

        return (
            <>
                <div className={cn('flex flex-wrap items-center gap-2 text-xs', className)}>
                    <span className="inline-flex items-center gap-1 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-1 font-medium text-emerald-700 dark:text-emerald-300">
                        <Clock className="size-3" aria-hidden />
                        Session Started: {businessSession.started_at_time}
                    </span>
                    {businessSession.can_resume_close ? (
                        <button
                            type="button"
                            onClick={openCloseModal}
                            disabled={openingClose}
                            className="rounded-md border border-amber-400 bg-amber-50 px-2 py-1 font-bold text-amber-900 transition-colors hover:bg-amber-100 disabled:opacity-60"
                        >
                            {businessSession.status}
                        </button>
                    ) : (
                        <span className="rounded-md border border-border px-2 py-1 font-bold text-black">
                            {businessSession.status}
                        </span>
                    )}
                    {canOpenCloseModal ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            className="h-7 gap-1 rounded-md px-2 text-xs"
                            onClick={openCloseModal}
                            disabled={openingClose}
                        >
                            {openingClose ? (
                                <Loader2 className="size-3 animate-spin" aria-hidden />
                            ) : (
                                <Square className="size-3" aria-hidden />
                            )}
                            {openingClose
                                ? 'Loading…'
                                : businessSession.can_resume_close
                                  ? 'Resume Close'
                                  : 'Close Now'}
                        </Button>
                    ) : null}
                </div>

                <BusinessSessionCloseModal
                    open={closeOpen}
                    onOpenChange={handleCloseModalChange}
                    data={closeData}
                />
            </>
        );
    }

    if (!businessSession.can_start) {
        return null;
    }

    return (
        <div className={cn('flex items-center', className)}>
            <Button
                type="button"
                size={compact ? 'sm' : 'default'}
                className={cn(
                    'gap-1.5 rounded-md bg-emerald-600 text-white hover:bg-emerald-700',
                    compact ? 'h-7 px-2 text-xs' : '',
                )}
                onClick={startSession}
            >
                <PlayCircle className="size-3.5" aria-hidden />
                Start Session
            </Button>
        </div>
    );
}

export { formatMoney as formatSessionMoney };
