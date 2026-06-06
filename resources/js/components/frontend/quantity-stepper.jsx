import { Minus, Plus } from 'lucide-react';
import { storeCn } from '@/lib/store-cn';

const sizeStyles = {
    md: {
        button: 'size-8',
        icon: 'size-3.5',
        value: 'min-w-8 px-2 text-sm',
    },
    sm: {
        button: 'size-6',
        icon: 'size-3',
        value: 'min-w-6 px-1 text-xs',
    },
};

function DefaultStepper({ value, onChange, min, max, size, className }) {
    const styles = sizeStyles[size] ?? sizeStyles.md;

    return (
        <div className={storeCn('inline-flex items-center rounded-md border border-gray-200 bg-white', className)}>
            <button
                type="button"
                onClick={() => onChange(Math.max(min, value - 1))}
                disabled={value <= min}
                className={storeCn(
                    'flex items-center justify-center text-store-primary hover:bg-store-surface disabled:opacity-40',
                    styles.button,
                )}
                aria-label="Decrease quantity"
            >
                <Minus className={styles.icon} />
            </button>
            <span className={storeCn('text-center font-medium text-store-primary', styles.value)}>{value}</span>
            <button
                type="button"
                onClick={() => onChange(Math.min(max, value + 1))}
                disabled={value >= max}
                className={storeCn(
                    'flex items-center justify-center text-store-primary hover:bg-store-surface disabled:opacity-40',
                    styles.button,
                )}
                aria-label="Increase quantity"
            >
                <Plus className={styles.icon} />
            </button>
        </div>
    );
}

function PillStepper({ value, onChange, min, max, className }) {
    return (
        <div className={storeCn('inline-flex items-center gap-1 rounded-full bg-store-surface p-0.5 ring-1 ring-gray-200', className)}>
            <button
                type="button"
                onClick={() => onChange(Math.max(min, value - 1))}
                disabled={value <= min}
                className="flex size-5 items-center justify-center rounded-full bg-white text-store-accent shadow-sm transition-colors hover:bg-store-accent hover:text-white disabled:cursor-not-allowed disabled:opacity-35 disabled:hover:bg-white disabled:hover:text-store-accent"
                aria-label="Decrease quantity"
            >
                <Minus className="size-2.5" strokeWidth={2.5} />
            </button>
            <span className="min-w-5 px-0.5 text-center text-[11px] font-bold tabular-nums text-store-primary">{value}</span>
            <button
                type="button"
                onClick={() => onChange(Math.min(max, value + 1))}
                disabled={value >= max}
                className="flex size-5 items-center justify-center rounded-full bg-store-accent text-white shadow-sm transition-colors hover:bg-store-primary disabled:cursor-not-allowed disabled:opacity-35"
                aria-label="Increase quantity"
            >
                <Plus className="size-2.5" strokeWidth={2.5} />
            </button>
        </div>
    );
}

export function QuantityStepper({ value, onChange, min = 1, max = 99, size = 'md', variant = 'default', className }) {
    if (variant === 'pill') {
        return (
            <PillStepper
                value={value}
                onChange={onChange}
                min={min}
                max={max}
                className={className}
            />
        );
    }

    return (
        <DefaultStepper
            value={value}
            onChange={onChange}
            min={min}
            max={max}
            size={size}
            className={className}
        />
    );
}
