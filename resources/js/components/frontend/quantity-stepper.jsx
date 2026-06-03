import { Minus, Plus } from 'lucide-react';
import { storeCn } from '@/lib/store-cn';

export function QuantityStepper({ value, onChange, min = 1, max = 99, className }) {
    return (
        <div className={storeCn('inline-flex items-center rounded-md border border-gray-200', className)}>
            <button
                type="button"
                onClick={() => onChange(Math.max(min, value - 1))}
                disabled={value <= min}
                className="flex size-8 items-center justify-center text-store-primary hover:bg-store-surface disabled:opacity-40"
                aria-label="Decrease quantity"
            >
                <Minus className="size-3.5" />
            </button>
            <span className="min-w-[2rem] px-2 text-center text-sm font-medium text-store-primary">{value}</span>
            <button
                type="button"
                onClick={() => onChange(Math.min(max, value + 1))}
                disabled={value >= max}
                className="flex size-8 items-center justify-center text-store-primary hover:bg-store-surface disabled:opacity-40"
                aria-label="Increase quantity"
            >
                <Plus className="size-3.5" />
            </button>
        </div>
    );
}
