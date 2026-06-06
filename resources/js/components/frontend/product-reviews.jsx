import { Form, usePage } from '@inertiajs/react';
import { MessageSquare, Star } from 'lucide-react';
import { useState } from 'react';
import { StarRating } from '@/components/frontend/star-rating';
import { StoreButton } from '@/components/frontend/store-button';
import { StoreInput } from '@/components/frontend/store-input';

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
                        className="rounded p-0.5 transition-transform hover:scale-110"
                        aria-label={`Rate ${starValue} stars`}
                    >
                        <Star
                            className={`size-6 ${active ? 'fill-amber-400 text-amber-400' : 'fill-gray-100 text-gray-200'}`}
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
        <div className="space-y-8">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-lg font-bold text-store-primary sm:text-xl">Customer Reviews</h2>
                    <div className="mt-1 h-1 w-10 rounded-full store-gradient" aria-hidden />
                </div>
                {reviewSummary.count > 0 && (
                    <div className="flex items-center gap-3 rounded-xl bg-white px-4 py-3 ring-1 ring-gray-100">
                        <StarRating rating={reviewSummary.average} size="md" showValue />
                        <span className="text-sm text-store-muted">
                            Based on {reviewSummary.count} {reviewSummary.count === 1 ? 'review' : 'reviews'}
                        </span>
                    </div>
                )}
            </div>

            {flash?.success && (
                <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {flash.success}
                </div>
            )}

            <div className="grid gap-8 lg:grid-cols-2">
                <div className="rounded-2xl bg-white p-5 ring-1 ring-gray-100 sm:p-6">
                    <h3 className="text-sm font-semibold text-store-primary">Write a Review</h3>
                    <p className="mt-1 text-sm text-store-muted">Share your experience with this product</p>

                    <Form
                        action={`/products/${productSlug}/reviews`}
                        method="post"
                        resetOnSuccess
                        onSuccess={() => setSelectedRating(0)}
                        className="mt-5 space-y-4"
                    >
                        {({ errors, processing, wasSuccessful }) => (
                            <>
                                <div>
                                    <label className="mb-1.5 block text-xs font-medium text-store-primary">
                                        Your rating
                                    </label>
                                    <RatingInput value={selectedRating} onChange={setSelectedRating} />
                                    <input type="hidden" name="rating" value={selectedRating} />
                                    {errors.rating && (
                                        <p className="mt-1 text-xs text-red-600">{errors.rating}</p>
                                    )}
                                </div>

                                <div>
                                    <label htmlFor="reviewer_name" className="mb-1.5 block text-xs font-medium text-store-primary">
                                        Your name
                                    </label>
                                    <StoreInput
                                        id="reviewer_name"
                                        name="reviewer_name"
                                        placeholder="Enter your name"
                                        error={errors.reviewer_name}
                                    />
                                </div>

                                <div>
                                    <label htmlFor="comment" className="mb-1.5 block text-xs font-medium text-store-primary">
                                        Your review
                                    </label>
                                    <textarea
                                        id="comment"
                                        name="comment"
                                        rows={4}
                                        placeholder="Tell others what you think about this product..."
                                        className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-store-primary placeholder:text-gray-400 focus:border-store-accent focus:outline-none focus:ring-2 focus:ring-store-accent/20"
                                    />
                                    {errors.comment && (
                                        <p className="mt-1 text-xs text-red-600">{errors.comment}</p>
                                    )}
                                </div>

                                <StoreButton type="submit" disabled={processing || selectedRating === 0} className="w-full sm:w-auto">
                                    {processing ? 'Submitting...' : 'Submit Review'}
                                </StoreButton>

                                {wasSuccessful && (
                                    <p className="text-sm text-emerald-600">Your review has been submitted!</p>
                                )}
                            </>
                        )}
                    </Form>
                </div>

                <div className="space-y-4">
                    {reviews.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-2xl bg-white px-6 py-12 text-center ring-1 ring-gray-100">
                            <MessageSquare className="size-10 text-gray-200" strokeWidth={1.25} />
                            <p className="mt-3 text-sm font-medium text-store-primary">No reviews yet</p>
                            <p className="mt-1 text-sm text-store-muted">Be the first to share your thoughts!</p>
                        </div>
                    ) : (
                        reviews.map((review) => (
                            <article
                                key={review.id}
                                className="rounded-2xl bg-white p-5 ring-1 ring-gray-100 sm:p-6"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-semibold text-store-primary">{review.reviewer_name}</p>
                                        <p className="mt-0.5 text-xs text-store-muted">{review.created_at}</p>
                                    </div>
                                    <StarRating rating={review.rating} size="sm" />
                                </div>
                                <p className="mt-3 text-sm leading-relaxed text-store-muted">{review.comment}</p>
                            </article>
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}
