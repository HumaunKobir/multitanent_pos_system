import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    LogOut,
    Package,
    Settings,
    Sparkles,
    UserRound,
} from 'lucide-react';

import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';

const navItems = [
    { href: '/customer/dashboard', label: 'Dashboard', icon: LayoutDashboard, exact: true },
    { href: '/customer/orders', label: 'Online Orders', icon: Package },
    { href: '/customer/settings', label: 'Profile', icon: Settings },
];

function isActive(item, isCurrentUrl, isCurrentOrParentUrl) {
    if (item.exact) {
        return isCurrentUrl(item.href);
    }

    return isCurrentOrParentUrl(item.href);
}

function SidebarNavLink({ href, label, icon: Icon, exact, onNavigate }) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const active = isActive({ href, exact }, isCurrentUrl, isCurrentOrParentUrl);

    return (
        <Link
            href={href}
            prefetch
            onClick={onNavigate}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'group relative flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-all duration-200',
                active
                    ? 'text-store-primary'
                    : 'text-store-muted hover:bg-store-surface hover:text-store-primary',
            )}
        >
            {active && (
                <>
                    <span
                        className="absolute left-0 top-1/2 h-7 w-1 -translate-y-1/2 rounded-r-md bg-store-accent"
                        aria-hidden
                    />
                    <span
                        className="pointer-events-none absolute inset-0 rounded-lg bg-store-accent/8 ring-1 ring-store-accent/15"
                        aria-hidden
                    />
                    <Sparkles
                        className="absolute right-2 top-1/2 size-3 -translate-y-1/2 text-store-accent/70"
                        aria-hidden
                    />
                </>
            )}
            <span
                className={cn(
                    'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors',
                    active
                        ? 'bg-store-accent text-white shadow-sm shadow-store-accent/25'
                        : 'bg-store-surface text-store-muted group-hover:bg-store-accent/10 group-hover:text-store-accent',
                )}
            >
                <Icon className="size-3.5" strokeWidth={active ? 2.25 : 2} />
            </span>
            <span className="relative z-10 min-w-0 flex-1 truncate text-[13px] font-semibold">{label}</span>
        </Link>
    );
}

function MobileNavLink({ href, label, icon: Icon, exact }) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const active = isActive({ href, exact }, isCurrentUrl, isCurrentOrParentUrl);

    return (
        <Link
            href={href}
            prefetch
            aria-current={active ? 'page' : undefined}
            className={cn(
                'relative flex shrink-0 items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-all',
                active
                    ? 'border-store-accent/30 bg-store-accent/10 text-store-primary'
                    : 'border-gray-200 bg-white text-store-muted hover:border-store-accent/20 hover:text-store-primary',
            )}
        >
            {active && (
                <span className="absolute inset-x-2 -bottom-px h-0.5 rounded-full bg-store-accent" aria-hidden />
            )}
            <Icon className={cn('size-3.5', active ? 'text-store-accent' : 'text-store-muted')} />
            {label}
        </Link>
    );
}

export function CustomerPanelNav({ variant = 'sidebar', onNavigate, className }) {
    const { auth } = usePage().props;
    const customer = auth?.customer;

    if (variant === 'mobile') {
        return (
            <nav
                className={cn(
                    'flex gap-1.5 overflow-x-auto pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
                    className,
                )}
                aria-label="Account menu"
            >
                {navItems.map((item) => (
                    <MobileNavLink key={item.href} {...item} />
                ))}
                <Link
                    href="/customer/logout"
                    method="post"
                    as="button"
                    className="flex shrink-0 items-center gap-1.5 rounded-lg border border-dashed border-gray-300 px-3 py-1.5 text-xs font-semibold text-store-muted transition hover:border-store-accent/40 hover:text-store-accent"
                >
                    <LogOut className="size-3.5" />
                    Sign out
                </Link>
            </nav>
        );
    }

    return (
        <div className={cn('flex flex-col', className)}>
            <div className="border-b border-gray-100 px-3 py-3">
                <div className="flex items-center gap-2.5">
                    <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-gray-200 bg-store-surface">
                        {customer?.image_url ? (
                            <img
                                src={customer.image_url}
                                alt={customer.name}
                                className="size-full object-cover"
                            />
                        ) : (
                            <UserRound className="size-5 text-store-muted" />
                        )}
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-bold text-store-primary">{customer?.name ?? 'Guest'}</p>
                        <p className="truncate text-[11px] text-store-muted">{customer?.phone}</p>
                    </div>
                </div>
            </div>

            <nav className="space-y-0.5 p-2" aria-label="Account menu">
                {navItems.map((item) => (
                    <SidebarNavLink key={item.href} {...item} onNavigate={onNavigate} />
                ))}
            </nav>

            <div className="border-t border-gray-100 p-2">
                <Link
                    href="/customer/logout"
                    method="post"
                    as="button"
                    className="flex w-full items-center gap-2.5 rounded-lg border border-dashed border-gray-200 px-2.5 py-2 text-[13px] font-medium text-store-muted transition hover:border-store-accent/40 hover:bg-store-accent/5 hover:text-store-accent"
                >
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-store-surface">
                        <LogOut className="size-3.5" />
                    </span>
                    Sign out
                </Link>
            </div>
        </div>
    );
}
