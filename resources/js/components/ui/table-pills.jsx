import { cva } from 'class-variance-authority';

import { cn } from '@/lib/utils';

const statusToneVariants = cva(
    'inline-flex items-center gap-1.5 rounded-none border px-2.5 py-1 text-xs font-medium tabular-nums tracking-tight transition-[color,background-color,border-color]',
    {
        variants: {
            tone: {
                success:
                    'border-emerald-500/40 bg-emerald-500/[0.12] text-emerald-900 dark:border-emerald-400/35 dark:bg-emerald-400/10 dark:text-emerald-200',
                warning:
                    'border-amber-500/40 bg-amber-500/[0.12] text-amber-950 dark:border-amber-400/35 dark:bg-amber-400/10 dark:text-amber-100',
                danger:
                    'border-destructive/45 bg-destructive/10 text-destructive dark:text-red-200',
                info: 'border-sky-500/40 bg-sky-500/[0.12] text-sky-950 dark:border-sky-400/35 dark:bg-sky-400/10 dark:text-sky-100',
                pending:
                    'border-violet-500/40 bg-violet-500/[0.12] text-violet-950 dark:border-violet-400/35 dark:bg-violet-400/10 dark:text-violet-100',
                neutral: 'border-border bg-muted/60 text-muted-foreground',
            },
        },
        defaultVariants: {
            tone: 'neutral',
        },
    },
);

const statusDotVariants = cva('size-1.5 shrink-0 rounded-full', {
    variants: {
        tone: {
            success: 'bg-emerald-500 dark:bg-emerald-400',
            warning: 'bg-amber-500 dark:bg-amber-400',
            danger: 'bg-destructive',
            info: 'bg-sky-500 dark:bg-sky-400',
            pending: 'bg-violet-500 dark:bg-violet-400',
            neutral: 'bg-muted-foreground',
        },
    },
    defaultVariants: {
        tone: 'neutral',
    },
});

/**
 * Semantic status pill with optional leading dot (good for table status columns).
 *
 * @param {object} props
 * @param {'success'|'warning'|'danger'|'info'|'pending'|'neutral'} [props.tone]
 * @param {boolean} [props.showDot]
 * @param {string} [props.label]
 * @param {import('react').ReactNode} [props.children]
 * @param {string} [props.className]
 */
export function StatusPill({ tone = 'neutral', showDot = true, label, children, className, ...props }) {
    const text = label ?? children;

    return (
        <span data-slot="status-pill" className={cn(statusToneVariants({ tone }), className)} {...props}>
            {showDot ? <span className={statusDotVariants({ tone })} aria-hidden /> : null}
            <span>{text}</span>
        </span>
    );
}

const labelPillVariants = cva(
    'inline-flex items-center rounded-none border border-dashed border-border/90 bg-background/80 px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground',
);

/**
 * Neutral tag / category pill for secondary labels (region, queue, type).
 *
 * @param {object} props
 * @param {import('react').ReactNode} props.children
 * @param {string} [props.className]
 */
export function LabelPill({ children, className, ...props }) {
    return (
        <span data-slot="label-pill" className={cn(labelPillVariants(), className)} {...props}>
            {children}
        </span>
    );
}
