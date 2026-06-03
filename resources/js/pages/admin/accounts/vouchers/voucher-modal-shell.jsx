import { Dialog, DialogContent } from '@/components/ui/dialog';

export function VoucherModalShell({ open, onOpenChange, title, subtitle, children, footer, maxWidthClass = 'sm:max-w-4xl' }) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={`flex max-h-[90vh] w-full max-w-[calc(100%-2rem)] flex-col gap-0 overflow-hidden p-0 ${maxWidthClass}`}
            >
                <div className="flex items-center justify-between bg-blue-950 px-5 py-3">
                    <div>
                        <h2 className="text-sm font-semibold text-white">{title}</h2>
                        {subtitle && <p className="text-xs text-white/70">{subtitle}</p>}
                    </div>
                </div>
                <div className="flex-1 overflow-x-hidden overflow-y-auto">{children}</div>
                {footer}
            </DialogContent>
        </Dialog>
    );
}
