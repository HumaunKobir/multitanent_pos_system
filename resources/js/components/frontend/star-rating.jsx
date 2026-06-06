import { Star } from 'lucide-react';
import { formatRating } from '@/lib/rating-utils';

export function StarRating({ rating, size = 'sm', showValue = false }) {
    const sizeClass =
        size === 'lg' ? 'size-5' : size === 'md' ? 'size-4' : size === 'xs' ? 'size-2.5' : 'size-3.5';
    const rounded = Math.round(Number(rating) * 2) / 2;

    return (
        <div className="flex items-center gap-1.5">
            <div className="flex items-center gap-0.5" aria-label={`${rating} out of 5 stars`}>
                {Array.from({ length: 5 }, (_, i) => {
                    const filled = rounded >= i + 1;
                    const half = !filled && rounded >= i + 0.5;

                    return (
                        <Star
                            key={i}
                            className={`${sizeClass} ${
                                filled || half
                                    ? 'fill-amber-400 text-amber-400'
                                    : 'fill-gray-100 text-gray-200'
                            }`}
                            aria-hidden
                        />
                    );
                })}
            </div>
            {showValue && (
                <span className="text-sm font-medium text-store-primary">{formatRating(rating)}</span>
            )}
        </div>
    );
}
