import { Layers } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    findMatchingVariation,
    getOptionsForAxis,
    inferVariationAxes,
    selectionsFromVariation,
} from '@/lib/variation-utils';
import { cn } from '@/lib/utils';

export function DynamicVariantPicker({ variations = [], selectedVariation, onVariationChange }) {
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

    const isMultiAxis = axes.length > 1 || axes[0]?.name !== 'Option';

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between gap-2">
                <p className="flex items-center gap-1.5 text-xs font-bold text-store-primary">
                    <Layers className="size-3.5 text-store-accent" aria-hidden />
                    {isMultiAxis ? 'Choose your options' : 'Choose option'}
                </p>
                <span className="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-store-muted ring-1 ring-gray-200">
                    {variations.length} {variations.length === 1 ? 'variant' : 'variants'}
                </span>
            </div>

            {axes.map((axis) => {
                const options = getOptionsForAxis(variations, axis.name, selections, axes);

                return (
                    <div key={axis.name}>
                        <p className="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-store-muted">
                            {axis.name}
                        </p>
                        <div className="flex flex-wrap gap-1.5">
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
                                            'rounded-full px-3 py-1.5 text-[11px] font-semibold ring-1 transition-all',
                                            selected
                                                ? 'bg-store-primary text-white ring-store-primary shadow-sm'
                                                : disabled
                                                  ? 'cursor-not-allowed bg-gray-50 text-gray-300 line-through ring-gray-100'
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
