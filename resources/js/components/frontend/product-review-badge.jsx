import { StarRating } from '@/components/frontend/star-rating';
import { formatRating } from '@/lib/rating-utils';
import { cn } from '@/lib/utils';

export function ProductReviewBadge({ summary, className }) {
    if (!summary?.count) {
        return null;
    }

    const average = Number(summary.average);
    const formatted = formatRating(average);

    return (
        <div
            className={cn(
                'inline-flex items-center gap-1.5 bg-linear-to-r from-amber-50 to-orange-50/80 px-2 py-0.5 ring-1 ring-amber-100/90',
                className,
            )}
            aria-label={`${formatted} out of 5 stars`}
        >
            <StarRating rating={average} size="xs" />
            <span className="text-[10px] font-bold leading-none text-store-primary">{formatted}</span>
        </div>
    );
}
