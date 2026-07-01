import { Dialog, DialogContent } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

export function VoucherModalShell({ open, onOpenChange, title, subtitle, children, footer, headerActions, maxWidthClass = 'sm:max-w-4xl' }) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={cn(
                    'flex max-h-[min(92dvh,calc(100vh-2rem))] w-full max-w-[calc(100%-2rem)] flex-col gap-0 overflow-hidden rounded-lg border-0 p-0 shadow-xl',
                    'duration-300 ease-out',
                    'data-[state=open]:zoom-in-100 data-[state=closed]:zoom-out-100',
                    'data-[state=open]:slide-in-from-bottom-2 data-[state=closed]:slide-out-to-bottom-2',
                    maxWidthClass,
                )}
            >
                <div className="flex shrink-0 items-start justify-between gap-3 bg-blue-950 px-5 py-3 pr-12">
                    <div className="min-w-0 flex-1">
                        <h2 className="text-sm font-semibold text-white">{title}</h2>
                        {subtitle && <p className="text-xs text-white/70">{subtitle}</p>}
                    </div>
                    {headerActions ? (
                        <div className="flex shrink-0 items-center gap-2">{headerActions}</div>
                    ) : null}
                </div>
                <div className="min-h-0 flex-1 overflow-x-hidden overflow-y-auto">{children}</div>
                {footer ? <div className="shrink-0">{footer}</div> : null}
            </DialogContent>
        </Dialog>
    );
}
