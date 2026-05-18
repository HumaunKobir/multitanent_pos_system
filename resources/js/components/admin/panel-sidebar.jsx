import { Link, usePage } from '@inertiajs/react';
import {
    BarChart2,
    Building2,
    ChevronDown,
    CircleDollarSign,
    Globe,
    HandCoins,
    LayoutDashboard,
    LogOut,
    Package,
    PhoneCall,
    Send,
    Settings,
    Shield,
    ShoppingCart,
    User,
    UserCog,
    Users,
    UsersRound,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { route } from '@/lib/route';

const iconMap = {
    'layout-dashboard': LayoutDashboard,
    'circle-dollar-sign': CircleDollarSign,
    'hand-coins': HandCoins,
    user: User,
    'users-round': UsersRound,
    'shopping-cart': ShoppingCart,
    package: Package,
    users: Users,
    'building-2': Building2,
    'user-cog': UserCog,
    globe: Globe,
    send: Send,
    'phone-call': PhoneCall,
    settings: Settings,
    wallet: Wallet,
    'bar-chart-2': BarChart2,
    shield: Shield,
};

const navSelectedClass =
    'border-indigo-500/90 bg-indigo-600 font-semibold text-white shadow-[0_6px_22px_-6px_rgba(79,70,229,0.55),0_2px_8px_-2px_rgba(67,56,202,0.35)] dark:border-indigo-400/70 dark:bg-indigo-700';

function navLinkClass(active) {
    return cn(
        'group flex items-center gap-2.5 border px-2.5 py-2 text-[0.8125rem] leading-tight tracking-tight transition-[border-color,background-color,color,font-weight,box-shadow]',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400/60 focus-visible:ring-offset-2 focus-visible:ring-offset-sidebar',
        active
            ? cn(navSelectedClass, 'hover:bg-indigo-500 dark:hover:bg-indigo-600')
            : 'border-transparent font-medium text-sidebar-foreground/90 shadow-none hover:border-border/80 hover:bg-sidebar-accent/60 hover:text-sidebar-foreground',
    );
}

function childLinkClass(active, disabled = false) {
    return cn(
        'block border-l-2 py-1.5 pl-3 pr-2 text-[0.8rem] leading-snug tracking-tight transition-[border-color,color,font-weight]',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400/60',
        disabled
            ? 'cursor-not-allowed border-border/40 font-medium text-sidebar-foreground/45'
            : active
              ? 'border-indigo-500 font-semibold text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
              : 'border-border/50 font-medium text-sidebar-foreground/75 hover:border-indigo-400/60 hover:text-sidebar-foreground',
    );
}

function NavItem({ href, className, children, active }) {
    if (!href) {
        return (
            <span className={className} aria-disabled="true">
                {children}
            </span>
        );
    }

    return (
        <Link href={href} prefetch className={className} aria-current={active ? 'page' : undefined}>
            {children}
        </Link>
    );
}

export function PanelSidebar() {
    const { adminNavigation = [], panelType = 'admin' } = usePage().props;
    const panelTitle = panelType === 'branch' ? 'Branch Panel' : 'Admin Panel';
    const { currentUrl, isCurrentUrl } = useCurrentUrl();

    const [expanded, setExpanded] = useState(() => {
        return new Set(
            adminNavigation
                .filter((s) => !s.single && s.children?.some((c) => c.href && isCurrentUrl(c.href)))
                .map((s) => s.title),
        );
    });

    useEffect(() => {
        setExpanded((prev) => {
            const next = new Set(prev);
            let changed = false;
            for (const s of adminNavigation) {
                if (s.single) continue;
                const hasActive = s.children?.some((c) => c.href && isCurrentUrl(c.href));
                if (hasActive && !next.has(s.title)) {
                    next.add(s.title);
                    changed = true;
                }
            }
            return changed ? next : prev;
        });
    }, [currentUrl, adminNavigation]);

    const toggle = (title) =>
        setExpanded((prev) => {
            const next = new Set(prev);
            next.has(title) ? next.delete(title) : next.add(title);
            return next;
        });

    return (
        <aside className="flex w-64 shrink-0 flex-col border-r border-border bg-sidebar text-sidebar-foreground">
            <div className="relative border-b border-sidebar-border bg-linear-to-b from-muted/40 to-transparent px-4 py-5">
                <div
                    className="pointer-events-none absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-primary/40 to-transparent"
                    aria-hidden
                />
                <p className="font-mono text-[0.65rem] font-medium uppercase tracking-[0.28em] text-muted-foreground">
                    Coolness Point
                </p>
                <p className="mt-1 font-semibold tracking-tight text-foreground">{panelTitle}</p>
            </div>

            <nav className="flex flex-1 flex-col gap-1 overflow-y-auto p-3" aria-label={`${panelTitle} navigation`}>
                <ul className="flex flex-col gap-0.5">
                    {adminNavigation.map((section) => {
                        const SectionIcon = iconMap[section.icon] ?? LayoutDashboard;

                        if (section.single) {
                            const active = section.href ? isCurrentUrl(section.href) : false;
                            return (
                                <li key={section.title}>
                                    <NavItem href={section.href} className={navLinkClass(active)} active={active}>
                                        <SectionIcon
                                            className={cn(
                                                'size-4 shrink-0',
                                                active
                                                    ? 'text-white'
                                                    : 'text-muted-foreground group-hover:text-foreground',
                                            )}
                                            strokeWidth={active ? 2.25 : 2}
                                            aria-hidden
                                        />
                                        <span className="min-w-0 truncate">{section.title}</span>
                                    </NavItem>
                                </li>
                            );
                        }

                        const isOpen = expanded.has(section.title);
                        const sectionActive = section.children?.some((c) => c.href && isCurrentUrl(c.href));
                        const submenuId = `panel-submenu-${section.title.replace(/\s+/g, '-')}`;

                        return (
                            <li key={section.title}>
                                <button
                                    type="button"
                                    className={cn(
                                        'flex w-full items-center gap-2.5 border border-transparent px-2.5 py-1.5 text-left text-[0.8125rem] font-semibold tracking-tight transition-[border-color,background-color,color]',
                                        'hover:border-border/80 hover:bg-sidebar-accent/50',
                                        sectionActive ? 'text-foreground' : 'text-sidebar-foreground',
                                    )}
                                    aria-expanded={isOpen}
                                    aria-controls={submenuId}
                                    onClick={() => toggle(section.title)}
                                >
                                    <SectionIcon
                                        className={cn(
                                            'size-4 shrink-0',
                                            sectionActive ? 'text-primary' : 'text-muted-foreground',
                                        )}
                                        strokeWidth={2}
                                        aria-hidden
                                    />
                                    <span className="min-w-0 flex-1 truncate">{section.title}</span>
                                    <ChevronDown
                                        className={cn(
                                            'size-4 shrink-0 text-muted-foreground transition-transform duration-200',
                                            isOpen && 'rotate-180',
                                            sectionActive && 'text-indigo-600 dark:text-indigo-400',
                                        )}
                                        aria-hidden
                                    />
                                </button>

                                <ul id={submenuId} className={cn('mt-0.5 ml-6 space-y-px', !isOpen && 'hidden')}>
                                    {section.children?.map((child) => {
                                        const active = child.href ? isCurrentUrl(child.href) : false;
                                        const disabled = !child.href;
                                        return (
                                            <li key={child.title}>
                                                <NavItem
                                                    href={child.href}
                                                    className={childLinkClass(active, disabled)}
                                                    active={active}
                                                >
                                                    {child.title}
                                                </NavItem>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </li>
                        );
                    })}
                </ul>
            </nav>

            <div className="border-t border-sidebar-border p-3">
                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="flex w-full items-center gap-2 border border-dashed border-sidebar-border px-3 py-2.5 text-xs font-medium text-muted-foreground transition-colors hover:border-border hover:bg-sidebar-accent/40 hover:text-sidebar-foreground"
                >
                    <LogOut className="size-3.5 shrink-0" aria-hidden />
                    Logout
                </Link>
            </div>
        </aside>
    );
}
