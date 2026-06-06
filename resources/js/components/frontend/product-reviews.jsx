import { Form, usePage } from '@inertiajs/react';
import { MessageSquare, Star } from 'lucide-react';
import { useState } from 'react';
import { StarRating } from '@/components/frontend/star-rating';
import { StoreButton } from '@/components/frontend/store-button';
import { StoreInput } from '@/components/frontend/store-input';

function RatingInput({ value, onChange }) {
    const [hover, setHover] = useState(0);

    return (
        <div className="flex items-center gap-0.5">
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
                        className="rounded p-0.5 transition-transform hover:scale-110"
                        aria-label={`Rate ${starValue} stars`}
                    >
                        <Star
                            className={`size-5 ${active ? 'fill-amber-400 text-amber-400' : 'fill-gray-100 text-gray-200'}`}
                        />
                    </button>
                );
            })}
        </div>
    );
}

export function ProductReviews({ productSlug, reviews = [], reviewSummary = { average: 0, count: 0 } }) {
    const { flash } = usePage().props;
    const [selectedRating, setSelectedRating] = useState(0);

    return (
        <div className="space-y-3">
            {reviewSummary.count > 0 && (
                <div className="flex items-center gap-2 rounded-xl bg-store-surface/80 px-2.5 py-2 ring-1 ring-gray-100">
                    <StarRating rating={reviewSummary.average} size="sm" showValue />
                    <span className="text-[11px] text-store-muted">
                        {reviewSummary.count} {reviewSummary.count === 1 ? 'review' : 'reviews'}
                    </span>
                </div>
            )}

            {flash?.success && (
                <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                    {flash.success}
                </div>
            )}

            <div className="grid gap-3 lg:grid-cols-2">
                <div className="rounded-xl bg-store-surface/50 p-3 ring-1 ring-gray-100">
                    <h3 className="text-xs font-semibold text-store-primary">Write a Review</h3>
                    <p className="mt-0.5 text-[11px] text-store-muted">Share your experience</p>

                    <Form
                        action={`/products/${productSlug}/reviews`}
                        method="post"
                        resetOnSuccess
                        onSuccess={() => setSelectedRating(0)}
                        className="mt-3 space-y-3"
                    >
                        {({ errors, processing, wasSuccessful }) => (
                            <>
                                <div>
                                    <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-store-muted">
                                        Rating
                                    </label>
                                    <RatingInput value={selectedRating} onChange={setSelectedRating} />
                                    <input type="hidden" name="rating" value={selectedRating} />
                                    {errors.rating && (
                                        <p className="mt-1 text-[11px] text-red-600">{errors.rating}</p>
                                    )}
                                </div>

                                <StoreInput
                                    id="reviewer_name"
                                    name="reviewer_name"
                                    label="Your name"
                                    placeholder="Enter your name"
                                    error={errors.reviewer_name}
                                />

                                <div>
                                    <label htmlFor="comment" className="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-store-muted">
                                        Review
                                    </label>
                                    <textarea
                                        id="comment"
                                        name="comment"
                                        rows={3}
                                        placeholder="What did you think?"
                                        className="w-full rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-xs text-store-primary placeholder:text-gray-400 focus:border-store-accent focus:outline-none focus:ring-2 focus:ring-store-accent/20"
                                    />
                                    {errors.comment && (
                                        <p className="mt-1 text-[11px] text-red-600">{errors.comment}</p>
                                    )}
                                </div>

                                <StoreButton
                                    type="submit"
                                    disabled={processing || selectedRating === 0}
                                    className="w-full rounded-full py-2 text-xs sm:w-auto"
                                >
                                    {processing ? 'Submitting...' : 'Submit Review'}
                                </StoreButton>

                                {wasSuccessful && (
                                    <p className="text-[11px] text-emerald-600">Review submitted!</p>
                                )}
                            </>
                        )}
                    </Form>
                </div>

                <div className="space-y-2">
                    {reviews.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-xl bg-store-surface/50 px-4 py-8 text-center ring-1 ring-gray-100">
                            <MessageSquare className="size-8 text-gray-200" strokeWidth={1.25} />
                            <p className="mt-2 text-xs font-medium text-store-primary">No reviews yet</p>
                            <p className="mt-0.5 text-[11px] text-store-muted">Be the first to review!</p>
                        </div>
                    ) : (
                        reviews.map((review) => (
                            <article
                                key={review.id}
                                className="rounded-xl bg-store-surface/50 p-3 ring-1 ring-gray-100"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="text-xs font-semibold text-store-primary">{review.reviewer_name}</p>
                                        <p className="text-[10px] text-store-muted">{review.created_at}</p>
                                    </div>
                                    <StarRating rating={review.rating} size="sm" />
                                </div>
                                <p className="mt-2 text-xs leading-relaxed text-store-muted">{review.comment}</p>
                            </article>
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}
