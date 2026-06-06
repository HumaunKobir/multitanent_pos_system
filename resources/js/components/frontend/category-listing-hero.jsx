import { Link } from '@inertiajs/react';
import { ChevronRight, Home, Layers } from 'lucide-react';

export function CategoryListingHero({ category, productCount }) {
    return (
        <section className="relative overflow-hidden bg-store-primary">
            {category.image ? (
                <>
                    <img
                        src={category.image}
                        alt=""
                        className="absolute inset-0 size-full object-cover"
                    />
                    <div
                        className="absolute inset-0 bg-linear-to-r from-store-primary/95 via-store-primary/85 to-store-primary/55"
                        aria-hidden
                    />
                </>
            ) : (
                <>
                    <div className="absolute inset-0 store-gradient opacity-35" aria-hidden />
                    <div
                        className="absolute inset-0 bg-linear-to-br from-store-primary via-store-primary/90 to-[#16213e]"
                        aria-hidden
                    />
                </>
            )}
            <div className="absolute inset-0 auth-grid-overlay opacity-20" aria-hidden />

            <div className="relative store-container py-4 sm:py-5 lg:py-6">
                <nav aria-label="Breadcrumb" className="flex flex-wrap items-center gap-1 text-white/60">
                    <Link
                        href="/"
                        className="inline-flex items-center gap-1 text-xs transition-colors hover:text-white"
                    >
                        <Home className="size-3.5" aria-hidden />
                        Home
                    </Link>
                    <ChevronRight className="size-3 shrink-0" aria-hidden />
                    <span className="text-xs font-medium text-white" aria-current="page">
                        {category.name}
                    </span>
                </nav>

                <div className="mt-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0">
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/75 ring-1 ring-white/10">
                            <Layers className="size-3 text-store-accent" aria-hidden />
                            Category
                        </span>
                        <h1 className="auth-display mt-2 text-xl font-bold text-white sm:text-2xl lg:text-3xl">
                            {category.name}
                        </h1>
                        <p className="mt-1.5 text-sm text-white/75">
                            {productCount} {productCount === 1 ? 'product' : 'products'} available
                        </p>
                    </div>

                    {category.image && (
                        <div className="hidden shrink-0 overflow-hidden rounded-xl ring-2 ring-white/20 sm:block sm:size-20 lg:size-24">
                            <img
                                src={category.image}
                                alt={category.name}
                                className="size-full object-cover"
                            />
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
}
