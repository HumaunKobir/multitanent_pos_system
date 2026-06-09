import { motion } from 'framer-motion';
import { Ban, BadgeCheck, Check, ClipboardList, Package, Truck, PackageCheck } from 'lucide-react';
import { cn } from '@/lib/utils';

const TRACKING_STEPS = [
    { status: 1, label: 'Pending', hint: 'Order placed', icon: ClipboardList },
    { status: 2, label: 'Processing', hint: 'Preparing', icon: Package },
    { status: 4, label: 'Confirmed', hint: 'Shop confirmed', icon: BadgeCheck },
    { status: 3, label: 'Shipping', hint: 'On the way', icon: Truck },
    { status: 5, label: 'Delivered', hint: 'Completed', icon: PackageCheck },
];

const STATUS_INDEX = { 1: 0, 2: 1, 4: 2, 3: 3, 5: 4 };

function normalizeStatus(status) {
    if (status == null) {
        return 1;
    }

    return typeof status === 'object' && 'value' in status ? status.value : Number(status);
}

function getActiveIndex(status) {
    return STATUS_INDEX[normalizeStatus(status)] ?? 0;
}

export function getOrderStatusLabel(status) {
    const value = normalizeStatus(status);

    return TRACKING_STEPS.find((step) => step.status === value)?.label ?? (value === 6 ? 'Cancelled' : 'Unknown');
}

export function OrderTrackingProgress({ status, variant = 'full', className }) {
    const normalized = normalizeStatus(status);
    const isCancelled = normalized === 6;

    if (isCancelled) {
        return (
            <div
                className={cn(
                    'flex items-start gap-3 rounded-xl border border-red-200/80 bg-red-50 px-4 py-3.5',
                    className,
                )}
            >
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-600">
                    <Ban className="size-5" strokeWidth={2.25} />
                </span>
                <div>
                    <p className="text-sm font-bold text-red-800">Order cancelled</p>
                    <p className="mt-0.5 text-xs text-red-600/90">This order will not be processed or delivered.</p>
                </div>
            </div>
        );
    }

    const activeIndex = getActiveIndex(normalized);
    const progressPercent = (activeIndex / (TRACKING_STEPS.length - 1)) * 100;

    if (variant === 'compact') {
        return (
            <div className={cn('space-y-2', className)}>
                <div className="flex items-center justify-between gap-2 text-[11px]">
                    <span className="font-semibold text-store-primary">{getOrderStatusLabel(normalized)}</span>
                    <span className="text-store-muted">
                        Step {activeIndex + 1} of {TRACKING_STEPS.length}
                    </span>
                </div>
                <div className="relative h-2 overflow-hidden rounded-full bg-gray-100">
                    <motion.div
                        className="absolute inset-y-0 left-0 rounded-full bg-store-accent"
                        initial={{ width: 0 }}
                        animate={{ width: `${progressPercent}%` }}
                        transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
                    />
                </div>
                <div className="flex justify-between gap-1">
                    {TRACKING_STEPS.map((step, index) => (
                        <span
                            key={step.status}
                            className={cn(
                                'flex-1 text-center text-[9px] font-medium leading-tight',
                                index <= activeIndex ? 'text-store-accent' : 'text-store-muted/60',
                            )}
                        >
                            {step.label}
                        </span>
                    ))}
                </div>
            </div>
        );
    }

    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border border-gray-200/80 bg-white p-4 shadow-sm sm:p-5',
                className,
            )}
        >
            <div className="mb-5 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-store-accent">Order tracking</p>
                    <p className="mt-1 text-sm font-semibold text-store-primary">
                        Current status:{' '}
                        <span className="text-store-accent">{getOrderStatusLabel(normalized)}</span>
                    </p>
                </div>
                <p className="text-xs text-store-muted">
                    {activeIndex + 1} / {TRACKING_STEPS.length} steps
                </p>
            </div>

            <div className="relative hidden sm:block">
                <div className="absolute left-[10%] right-[10%] top-5 h-1 -translate-y-1/2 rounded-full bg-gray-100">
                    <motion.div
                        className="h-full rounded-full bg-store-accent"
                        initial={{ width: 0 }}
                        animate={{ width: `${progressPercent}%` }}
                        transition={{ duration: 0.55, ease: [0.22, 1, 0.36, 1] }}
                    />
                </div>

                <ol className="relative grid grid-cols-5 gap-2">
                    {TRACKING_STEPS.map((step, index) => {
                        const Icon = step.icon;
                        const isComplete = index < activeIndex;
                        const isCurrent = index === activeIndex;
                        const isUpcoming = index > activeIndex;

                        return (
                            <li key={step.status} className="flex flex-col items-center text-center">
                                <span
                                    className={cn(
                                        'relative z-10 flex size-10 items-center justify-center rounded-full border-2 transition-colors',
                                        isComplete && 'border-store-accent bg-store-accent text-white shadow-md shadow-store-accent/25',
                                        isCurrent &&
                                            'border-store-accent bg-white text-store-accent shadow-md shadow-store-accent/20 ring-4 ring-store-accent/15',
                                        isUpcoming && 'border-gray-200 bg-white text-store-muted',
                                    )}
                                >
                                    {isComplete ? <Check className="size-4" strokeWidth={3} /> : <Icon className="size-4" />}
                                </span>
                                <p
                                    className={cn(
                                        'mt-2.5 text-xs font-bold',
                                        isCurrent ? 'text-store-accent' : isComplete ? 'text-store-primary' : 'text-store-muted',
                                    )}
                                >
                                    {step.label}
                                </p>
                                <p className="mt-0.5 text-[10px] text-store-muted">{step.hint}</p>
                            </li>
                        );
                    })}
                </ol>
            </div>

            <ol className="relative space-y-0 sm:hidden">
                {TRACKING_STEPS.map((step, index) => {
                    const Icon = step.icon;
                    const isComplete = index < activeIndex;
                    const isCurrent = index === activeIndex;
                    const isLast = index === TRACKING_STEPS.length - 1;

                    return (
                        <li key={step.status} className="relative flex gap-3 pb-5 last:pb-0">
                            {!isLast && (
                                <span
                                    className={cn(
                                        'absolute left-5 top-10 bottom-0 w-0.5 -translate-x-1/2',
                                        index < activeIndex ? 'bg-store-accent' : 'bg-gray-200',
                                    )}
                                    aria-hidden
                                />
                            )}
                            <span
                                className={cn(
                                    'relative z-10 flex size-10 shrink-0 items-center justify-center rounded-full border-2',
                                    isComplete && 'border-store-accent bg-store-accent text-white',
                                    isCurrent && 'border-store-accent bg-white text-store-accent ring-4 ring-store-accent/15',
                                    !isComplete && !isCurrent && 'border-gray-200 bg-white text-store-muted',
                                )}
                            >
                                {isComplete ? <Check className="size-4" strokeWidth={3} /> : <Icon className="size-4" />}
                            </span>
                            <div className="min-w-0 pt-1.5">
                                <p
                                    className={cn(
                                        'text-sm font-bold',
                                        isCurrent ? 'text-store-accent' : isComplete ? 'text-store-primary' : 'text-store-muted',
                                    )}
                                >
                                    {step.label}
                                </p>
                                <p className="text-xs text-store-muted">{step.hint}</p>
                            </div>
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}
