import { useEffect, useMemo, useState } from 'react';
import {
    findMatchingVariation,
    getOptionsForAxis,
    inferVariationAxes,
    selectionsFromVariation,
} from '@/lib/variation-utils';
import { cn } from '@/lib/utils';

export function DynamicVariantPicker({
    variations = [],
    selectedVariation,
    onVariationChange,
    compact = false,
    layout = 'default',
}) {
    const axes = useMemo(() => inferVariationAxes(variations), [variations]);
    const [selections, setSelections] = useState({});

    useEffect(() => {
        setSelections(selectionsFromVariation(selectedVariation, axes));
    }, [selectedVariation?.id, axes]);

    const handleSelect = (axisName, value) => {
        const next = { ...selections, [axisName]: value };
        setSelections(next);

        const match = findMatchingVariation(variations, next, axes);
        onVariationChange(match);
    };

    if (!axes.length) {
        return null;
    }

    const isDetails = layout === 'details';

    return (
        <div className={cn(isDetails ? 'space-y-2.5' : compact ? 'space-y-2' : 'space-y-3')}>
            {!compact && !isDetails && (
                <p className="text-xs font-bold text-store-primary">
                    {axes.length > 1 || axes[0]?.name !== 'Option' ? 'Choose your options' : 'Choose option'}
                </p>
            )}

            {axes.map((axis) => {
                const options = getOptionsForAxis(variations, axis.name, selections, axes);

                if (isDetails) {
                    return (
                        <div key={axis.name} className="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                            <p className="w-11 shrink-0 text-[10px] font-semibold uppercase tracking-wider text-store-muted">
                                {axis.name}
                            </p>
                            <div className="flex min-w-0 flex-1 flex-wrap gap-1.5 rounded-lg bg-store-surface p-1 ring-1 ring-gray-100">
                                {options.map((option) => {
                                    const selected = selections[axis.name] === option.value;
                                    const disabled = !option.available || !option.inStock;

                                    return (
                                        <button
                                            key={option.value}
                                            type="button"
                                            onClick={() => !disabled && handleSelect(axis.name, option.value)}
                                            disabled={disabled}
                                            className={cn(
                                                'rounded-md px-3 py-1.5 text-[11px] font-semibold transition-all',
                                                selected
                                                    ? 'bg-white text-store-primary shadow-sm ring-2 ring-store-accent'
                                                    : disabled
                                                      ? 'cursor-not-allowed text-gray-300'
                                                      : 'text-store-primary hover:bg-white/80',
                                            )}
                                        >
                                            {option.value}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    );
                }

                return (
                    <div key={axis.name} className={compact ? 'flex flex-wrap items-center gap-x-2 gap-y-1.5' : undefined}>
                        <p
                            className={cn(
                                'font-semibold uppercase text-store-muted',
                                compact
                                    ? 'w-11 shrink-0 text-[9px] tracking-wider'
                                    : 'mb-1.5 text-[10px] tracking-wider',
                            )}
                        >
                            {axis.name}
                        </p>
                        <div className={cn('flex flex-wrap', compact ? 'min-w-0 flex-1 gap-1' : 'gap-1.5')}>
                            {options.map((option) => {
                                const selected = selections[axis.name] === option.value;
                                const disabled = !option.available || !option.inStock;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() => !disabled && handleSelect(axis.name, option.value)}
                                        disabled={disabled}
                                        className={cn(
                                            'font-semibold ring-1 transition-all',
                                            compact
                                                ? 'min-w-7 rounded-md px-2 py-1 text-[10px]'
                                                : 'rounded-full px-3 py-1.5 text-[11px]',
                                            selected
                                                ? 'bg-store-primary text-white ring-store-primary shadow-sm'
                                                : disabled
                                                  ? 'cursor-not-allowed bg-gray-50 text-gray-300 line-through ring-gray-100'
                                                  : compact
                                                    ? 'bg-white text-store-primary ring-gray-200 hover:ring-store-accent/60'
                                                    : 'bg-white text-store-primary ring-gray-200 hover:ring-store-accent/50',
                                        )}
                                    >
                                        {option.value}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
