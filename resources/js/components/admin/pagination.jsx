import { Link } from '@inertiajs/react';

import { cn } from '@/lib/utils';

/**
 * @param {object} props
 * @param {{ links?: object[], from?: number|null, to?: number|null, total?: number, last_page?: number }} props.paginator
 * @param {string} [props.className]
 */
export function AdminPagination({ paginator, className }) {
    const total = paginator?.total ?? 0;

    if (total <= 0) {
        return null;
    }

    const from = paginator.from ?? 0;
    const to = paginator.to ?? 0;
    const lastPage = paginator.last_page ?? 1;
    const links = paginator.links ?? [];
    const showPageLinks = lastPage > 1 && links.length > 0;

    return (
        <div
            className={cn(
                'mt-4 flex flex-col gap-2 border-t border-border pt-3 sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <p className="text-xs text-muted-foreground">
                Showing <span className="font-medium text-foreground">{from}</span>–
                <span className="font-medium text-foreground">{to}</span> of{' '}
                <span className="font-medium text-foreground">{total}</span>
            </p>

            {showPageLinks ? (
                <nav aria-label="Pagination" className="flex flex-wrap gap-1">
                    {links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                preserveState
                                preserveScroll
                                className={cn(
                                    'border px-3 py-1 text-sm transition-colors',
                                    link.active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border hover:bg-accent',
                                )}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span
                                key={index}
                                className="border border-border px-3 py-1 text-sm text-muted-foreground opacity-50"
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ),
                    )}
                </nav>
            ) : (
                <p className="text-xs text-muted-foreground">Page 1 of 1</p>
            )}
        </div>
    );
}
