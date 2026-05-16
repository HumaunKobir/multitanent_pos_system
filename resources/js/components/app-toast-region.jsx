import { router } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Info, TriangleAlert, X } from 'lucide-react';
import { useEffect } from 'react';

import { useAppToast } from '@/contexts/app-toast-context';
import { cn } from '@/lib/utils';

const icons = {
    success: CheckCircle2,
    info: Info,
    warning: TriangleAlert,
    error: AlertCircle,
};

/** @type {Record<'success' | 'info' | 'warning' | 'error', string>} */
const toastSurfaces = {
    success:
        'border-green-400/35 bg-green-600 text-white shadow-[0_10px_32px_-10px_rgba(22,163,74,0.52)] dark:bg-green-700 dark:shadow-[0_10px_32px_-10px_rgba(21,128,61,0.45)]',
    error:
        'border-rose-400/35 bg-rose-600 text-white shadow-[0_10px_32px_-10px_rgba(225,29,72,0.5)] dark:bg-rose-700 dark:shadow-[0_10px_32px_-10px_rgba(190,18,60,0.45)]',
    warning:
        'border-amber-300/40 bg-amber-600 text-white shadow-[0_10px_32px_-10px_rgba(217,119,6,0.5)] dark:bg-amber-700 dark:shadow-[0_10px_32px_-10px_rgba(180,83,9,0.45)]',
    info: 'border-blue-400/35 bg-blue-600 text-white shadow-[0_10px_32px_-10px_rgba(37,99,235,0.5)] dark:bg-blue-700 dark:shadow-[0_10px_32px_-10px_rgba(29,78,216,0.45)]',
};

function ToastItem({ id, type, message, onDismiss }) {
    const resolvedType = type in toastSurfaces ? type : 'info';
    const Icon = icons[resolvedType] ?? Info;
    const surface = toastSurfaces[resolvedType];

    return (
        <div
            role="status"
            className={cn(
                'pointer-events-auto relative flex items-center gap-2 overflow-hidden border border-l-[3px] px-2.5 py-2',
                'border-l-white/40',
                surface,
            )}
        >
            <span
                className="pointer-events-none absolute inset-0 bg-linear-to-br from-white/12 via-transparent to-black/10"
                aria-hidden
            />
            <Icon className="relative size-4 shrink-0 text-white" aria-hidden />
            <p className="relative min-w-0 flex-1 text-[0.8125rem] leading-tight font-medium tracking-tight text-white">
                {message}
            </p>
            <button
                type="button"
                className={cn(
                    'relative flex size-7 shrink-0 items-center justify-center rounded-none text-white/85 transition-[color,background-color]',
                    'hover:bg-white/18 hover:text-white',
                    'active:bg-white/26',
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/55 focus-visible:ring-offset-0',
                )}
                onClick={() => onDismiss(id)}
                aria-label="Dismiss notification"
            >
                <X className="size-3.5" strokeWidth={2.25} />
            </button>
        </div>
    );
}

export function AppToastRegion() {
    const { push, dismiss, toasts } = useAppToast();

    useEffect(() => {
        return router.on('flash', (event) => {
            const data = event.detail?.flash?.toast;

            if (!data?.message) {
                return;
            }

            push({
                type: data.type ?? 'info',
                message: data.message,
            });
        });
    }, [push]);

    return (
        <div
            className="pointer-events-none fixed bottom-4 right-4 z-100 flex w-[min(100%-2rem,20rem)] flex-col gap-1.5"
            aria-live="polite"
        >
            {toasts.map((t) => (
                <ToastItem key={t.id} {...t} onDismiss={dismiss} />
            ))}
        </div>
    );
}
