import { Form } from '@inertiajs/react';
import { MessageSquare, PenLine, Star } from 'lucide-react';
import { useState } from 'react';
import { StarRating } from '@/components/frontend/star-rating';
import { StoreButton } from '@/components/frontend/store-button';
import { formatRating } from '@/lib/rating-utils';
import { cn } from '@/lib/utils';
import { storeCn, storeInputClass } from '@/lib/store-cn';

const reviewFieldClass = storeCn(
    storeInputClass,
    'rounded-xl transition-all duration-200 hover:border-store-accent/50',
);

function ReviewerAvatar({ name }) {
    const initials =
        name
            ?.trim()
            .split(/\s+/)
            .map((part) => part[0])
            .join('')
            .slice(0, 2)
            .toUpperCase() || '?';

    return (
        <div
            className="flex size-8 shrink-0 items-center justify-center rounded-full bg-store-accent/10 text-[10px] font-bold text-store-accent ring-1 ring-store-accent/20"
            aria-hidden
        >
            {initials}
        </div>
    );
}

function RatingInput({ value, onChange }) {
    const [hover, setHover] = useState(0);

    return (
        <div className="flex items-center gap-1">
            {Array.from({ length: 5 }, (_, i) => {
                const starValue = i + 1;
                const active = starValue <= (hover || value);

                return (
                    <button
                        key={i}
                        type="button"
                        onClick={() => onChange(starValue)}
                        onMouseEnter={() => setHover(starValue)}
                        onMouseLeave={() => setHover(0)}
                        className="rounded-full p-0.5 transition-transform hover:scale-110"
                        aria-label={`Rate ${starValue} stars`}
                    >
                        <Star
                            className={cn(
                                'size-6 transition-colors',
                                active ? 'fill-amber-400 text-amber-400' : 'fill-gray-100 text-gray-200',
                            )}
                        />
                    </button>
                );
            })}
        </div>
    );
}

function ReviewSummaryHeader({ reviewSummary }) {
    if (reviewSummary.count <= 0) {
        return null;
    }

    return (
        <div className="relative overflow-hidden rounded-xl bg-linear-to-br from-store-surface/90 via-white to-white ring-1 ring-gray-200">
            <div className="absolute inset-x-0 top-0 h-0.5 bg-store-accent/80" aria-hidden />

            <div className="flex flex-wrap items-center gap-3 px-4 py-3">
                <div className="flex items-center gap-3">
                    <p className="text-3xl font-bold leading-none tracking-tight text-store-accent">
                        {formatRating(reviewSummary.average)}
                    </p>
                    <StarRating rating={reviewSummary.average} size="md" />
                </div>

                <span className="rounded-full bg-white px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-store-muted ring-1 ring-gray-200">
                    Customer rating
                </span>
            </div>
        </div>
    );
}

function ReviewFormCard({ productSlug, selectedRating, onRatingChange, onSuccess }) {
    return (
        <div className="overflow-hidden rounded-xl bg-white ring-1 ring-gray-200">
            <div className="relative overflow-hidden bg-linear-to-br from-store-surface/90 via-white to-white px-4 py-3">
                <div className="absolute inset-x-0 top-0 h-0.5 bg-store-accent/80" aria-hidden />

                <div className="flex items-center gap-2">
                    <span className="inline-flex size-7 items-center justify-center rounded-full bg-store-accent/10 ring-1 ring-store-accent/20">
                        <PenLine className="size-3.5 text-store-accent" aria-hidden />
                    </span>
                    <div>
                        <h3 className="text-sm font-bold text-store-primary">Write a Review</h3>
                        <p className="text-[11px] text-store-muted">Share your experience with this product</p>
                    </div>
                </div>
            </div>

            <Form
                action={`/products/${productSlug}/reviews`}
                method="post"
                resetOnSuccess
                onSuccess={onSuccess}
                className="space-y-3 px-4 py-3"
            >
                {({ errors, processing }) => (
                    <>
                        <div>
                            <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-store-muted">
                                Your rating
                            </label>
                            <RatingInput value={selectedRating} onChange={onRatingChange} />
                            <input type="hidden" name="rating" value={selectedRating} />
                            {errors.rating && <p className="mt-1.5 text-[11px] text-red-600">{errors.rating}</p>}
                        </div>

                        <div>
                            <label
                                htmlFor="reviewer_name"
                                className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-store-muted"
                            >
                                Your name
                            </label>
                            <input
                                id="reviewer_name"
                                name="reviewer_name"
                                placeholder="Enter your name"
                                className={cn(reviewFieldClass, 'py-2.5')}
                            />
                            {errors.reviewer_name && (
                                <p className="mt-1.5 text-[11px] text-red-600">{errors.reviewer_name}</p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="comment"
                                className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-store-muted"
                            >
                                Your review
                            </label>
                            <textarea
                                id="comment"
                                name="comment"
                                rows={4}
                                placeholder="What did you think about the fit, quality, or delivery?"
                                className={cn(reviewFieldClass, 'resize-y py-2.5 leading-relaxed')}
                            />
                            {errors.comment && (
                                <p className="mt-1.5 text-[11px] text-red-600">{errors.comment}</p>
                            )}
                        </div>

                        <StoreButton
                            type="submit"
                            variant="accent"
                            disabled={processing || selectedRating === 0}
                            className="w-full rounded-full py-3 text-sm font-bold shadow-sm sm:w-auto"
                        >
                            {processing ? 'Submitting...' : 'Submit Review'}
                        </StoreButton>
                    </>
                )}
            </Form>
        </div>
    );
}

function ReviewList({ reviews }) {
    if (reviews.length === 0) {
        return (
            <div className="flex h-full min-h-56 flex-col items-center justify-center rounded-xl bg-linear-to-br from-store-surface/80 via-white to-white px-6 py-10 text-center ring-1 ring-gray-200">
                <div className="inline-flex size-12 items-center justify-center rounded-full bg-store-accent/10 ring-1 ring-store-accent/20">
                    <MessageSquare className="size-5 text-store-accent" strokeWidth={1.5} aria-hidden />
                </div>
                <p className="mt-3 text-sm font-bold text-store-primary">No reviews yet</p>
                <p className="mt-1 max-w-xs text-[11px] leading-relaxed text-store-muted">
                    Be the first to share your thoughts and help others choose with confidence.
                </p>
            </div>
        );
    }

    return (
        <div className="overflow-hidden rounded-xl bg-white ring-1 ring-gray-200">
            <div className="border-b border-gray-100 px-3 py-2">
                <h3 className="text-xs font-bold text-store-primary">Customer Reviews</h3>
            </div>

            <div className="divide-y divide-gray-100">
                {reviews.map((review) => (
                    <article key={review.id} className="px-3 py-2.5">
                        <div className="flex items-start gap-2.5">
                            <ReviewerAvatar name={review.reviewer_name} />

                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-start justify-between gap-1.5">
                                    <div>
                                        <p className="text-xs font-semibold text-store-primary">
                                            {review.reviewer_name}
                                        </p>
                                        <p className="text-[10px] text-store-muted">{review.created_at}</p>
                                    </div>
                                    <StarRating rating={review.rating} size="sm" />
                                </div>

                                <p className="mt-1 text-[11px] leading-snug text-store-primary">{review.comment}</p>
                            </div>
                        </div>
                    </article>
                ))}
            </div>
        </div>
    );
}

export function ProductReviews({ productSlug, reviews = [], reviewSummary = { average: 0, count: 0 } }) {
    const [selectedRating, setSelectedRating] = useState(0);

    return (
        <div className="space-y-3">
            <ReviewSummaryHeader reviewSummary={reviewSummary} />

            <div className="grid gap-3 lg:grid-cols-2 lg:items-start">
                <ReviewFormCard
                    productSlug={productSlug}
                    selectedRating={selectedRating}
                    onRatingChange={setSelectedRating}
                    onSuccess={() => setSelectedRating(0)}
                />

                <ReviewList reviews={reviews} />
            </div>
        </div>
    );
}
