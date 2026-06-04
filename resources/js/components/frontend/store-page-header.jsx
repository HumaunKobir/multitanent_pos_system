import { Link } from '@inertiajs/react';
import { ChevronRight, Home } from 'lucide-react';

/**
 * @param {{
 *   title: string;
 *   subtitle?: string;
 *   breadcrumbs?: { label: string; href?: string }[];
 *   hideTitle?: boolean;
 * }} props
 * Pass breadcrumbs for the trail after Home. Last item = current page (omit href).
 */
export function StorePageHeader({ title, subtitle, breadcrumbs = [], hideTitle = false }) {
    const pages = breadcrumbs.length > 0 ? breadcrumbs : [{ label: title }];
    const trail = [{ label: 'Home', href: '/' }, ...pages];

    return (
        <section className="border-b border-gray-200/80 bg-white">
            <div className="store-container py-3 sm:py-4">
                <nav aria-label="Breadcrumb" className="flex flex-wrap items-center gap-1">
                    {trail.map((item, index) => {
                        const isLast = index === trail.length - 1;
                        const isHome = index === 0;

                        return (
                            <span key={`${item.label}-${index}`} className="inline-flex items-center gap-1">
                                {index > 0 && (
                                    <ChevronRight
                                        className="size-3 shrink-0 text-gray-300"
                                        aria-hidden
                                    />
                                )}
                                {isLast ? (
                                    <span
                                        className="text-xs font-medium text-store-primary sm:text-sm"
                                        aria-current="page"
                                    >
                                        {isHome ? (
                                            <span className="inline-flex items-center gap-1">
                                                <Home className="size-3.5" aria-hidden />
                                                {item.label}
                                            </span>
                                        ) : (
                                            item.label
                                        )}
                                    </span>
                                ) : (
                                    <Link
                                        href={item.href}
                                        className="inline-flex items-center gap-1 text-xs text-store-muted transition-colors hover:text-store-accent sm:text-sm"
                                    >
                                        {isHome && <Home className="size-3.5" aria-hidden />}
                                        {item.label}
                                    </Link>
                                )}
                            </span>
                        );
                    })}
                </nav>

                {!hideTitle && (
                    <>
                        <h1 className="mt-2 text-lg font-bold tracking-tight text-store-primary sm:text-xl">
                            {title}
                        </h1>
                        {subtitle && (
                            <p className="mt-1 text-sm text-store-muted">{subtitle}</p>
                        )}
                    </>
                )}
            </div>
        </section>
    );
}
