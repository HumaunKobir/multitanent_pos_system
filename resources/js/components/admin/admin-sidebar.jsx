import { Link } from '@inertiajs/react';
import {
    BarChart2,
    Building2,
    ChevronDown,
    Globe,
    LayoutDashboard,
    LogOut,
    Package,
    PhoneCall,
    Send,
    Settings,
    Shield,
    ShoppingBag,
    ShoppingCart,
    Truck,
    UserCog,
    Users,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

const mk = (title, href) => ({ title, href });

const navSections = [
    {
        title: 'Dashboard',
        icon: LayoutDashboard,
        href: dashboard.url(),
        single: true,
    },
    {
        title: 'Sales',
        icon: ShoppingCart,
        children: [
            mk('Sale', '#'),
            mk('Sale Return', '#'),
            mk('Product Exchange', '#'),
        ],
    },
    {
        title: 'Purchases',
        icon: Package,
        children: [
            mk('Purchase', '#'),
            mk('Purchase Return', '#'),
            mk('Damage', '#'),
            mk('Initial Stock', '#'),
        ],
    },
    {
        title: 'Suppliers',
        icon: Truck,
        children: [
            mk('Supplier', '#'),
            mk('Supplier Payment', '#'),
        ],
    },
    {
        title: 'Customers',
        icon: Users,
        children: [
            mk('Group', '#'),
            mk('Customer', '#'),
            mk('Due Collection', '#'),
        ],
    },
    { title: 'Branch', icon: Building2, href: '#', single: true },
    { title: 'User', icon: UserCog, href: '#', single: true },
    { title: 'Online Order', icon: Globe, href: '#', single: true },
    { title: 'Pathao', icon: Send, href: '#', single: true },
    { title: 'Contact List', icon: PhoneCall, href: '#', single: true },
    {
        title: 'Products',
        icon: ShoppingBag,
        children: [
            mk('All Products', '/admin/products'),
            mk('Add Product', '/admin/products/create'),
        ],
    },
    {
        title: 'Settings',
        icon: Settings,
        children: [
            mk('Category', '#'),
            mk('Brand', '#'),
            mk('Unit', '#'),
            mk('Size', '#'),
            mk('Color', '#'),
            mk('Tailor Measurement', '#'),
            mk('Site Settings', '#'),
            mk('Collection Category', '#'),
            mk('Product Section', '#'),
            mk('Slider', '#'),
            mk('Warranty', '#'),
            mk('Membership Card', '#'),
            mk('Barcode', '#'),
            mk('Adjust', '#'),
        ],
    },
    {
        title: 'Accounts',
        icon: Wallet,
        children: [
            mk('Accounts', '#'),
            mk('Chart of Accounts', '#'),
            mk('Payment Methods', '#'),
            mk('Parties', '#'),
            mk('Expense', '#'),
            mk('Income', '#'),
        ],
    },
    {
        title: 'Reports',
        icon: BarChart2,
        children: [
            mk('Customer Ledger', '#'),
            mk('Customer Wise Sale', '#'),
            mk('Supplier Ledger', '#'),
            mk('Product Stock Summary', '#'),
            mk('Product Ledger', '#'),
            mk('Cash Flow', '#'),
            mk('Daily Cash Flow Summary', '#'),
            mk('Daily Transactions', '#'),
            mk('Date Wise Stock', '#'),
            mk('Customer Due Collection', '#'),
            mk('Daily Summary', '#'),
        ],
    },
    { title: 'Access Management', icon: Shield, href: '/laratrust', single: true },
];

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

function childLinkClass(active) {
    return cn(
        'block border-l-2 py-1.5 pl-3 pr-2 text-[0.8rem] leading-snug tracking-tight transition-[border-color,color,font-weight]',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400/60',
        active
            ? 'border-indigo-500 font-semibold text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
            : 'border-border/50 font-medium text-sidebar-foreground/75 hover:border-indigo-400/60 hover:text-sidebar-foreground',
    );
}

export function AdminSidebar() {
    const { currentUrl, isCurrentUrl } = useCurrentUrl();

    const defaultOpen = new Set(
        navSections
            .filter((s) => !s.single && s.children?.some((c) => isCurrentUrl(c.href)))
            .map((s) => s.title),
    );
    if (defaultOpen.size === 0) {
        navSections.forEach((s) => {
            if (!s.single) defaultOpen.add(s.title);
        });
    }

    const [expanded, setExpanded] = useState(() => defaultOpen);

    useEffect(() => {
        setExpanded((prev) => {
            const next = new Set(prev);
            let changed = false;
            for (const s of navSections) {
                if (s.single) continue;
                const hasActive = s.children?.some((c) => isCurrentUrl(c.href));
                if (hasActive && !next.has(s.title)) {
                    next.add(s.title);
                    changed = true;
                }
            }
            return changed ? next : prev;
        });
    }, [currentUrl]);

    const toggle = (title) =>
        setExpanded((prev) => {
            const next = new Set(prev);
            next.has(title) ? next.delete(title) : next.add(title);
            return next;
        });

    return (
        <aside className="flex w-64 shrink-0 flex-col border-r border-border bg-sidebar text-sidebar-foreground">
            {/* Header */}
            <div className="relative border-b border-sidebar-border bg-linear-to-b from-muted/40 to-transparent px-4 py-5">
                <div
                    className="pointer-events-none absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-primary/40 to-transparent"
                    aria-hidden
                />
                <p className="font-mono text-[0.65rem] font-medium uppercase tracking-[0.28em] text-muted-foreground">
                    Coolness Point
                </p>
                <p className="mt-1 font-semibold tracking-tight text-foreground">Admin Panel</p>
            </div>

            {/* Navigation */}
            <nav
                className="flex flex-1 flex-col gap-1 overflow-y-auto p-3"
                aria-label="Admin navigation"
            >
                <ul className="flex flex-col gap-0.5">
                    {navSections.map((section) => {
                        const SectionIcon = section.icon;

                        if (section.single) {
                            const active = isCurrentUrl(section.href);
                            return (
                                <li key={section.title}>
                                    <Link
                                        href={section.href}
                                        prefetch
                                        className={navLinkClass(active)}
                                        aria-current={active ? 'page' : undefined}
                                    >
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
                                    </Link>
                                </li>
                            );
                        }

                        const isOpen = expanded.has(section.title);
                        const sectionActive = section.children?.some((c) => isCurrentUrl(c.href));
                        const submenuId = `admin-submenu-${section.title.replace(/\s+/g, '-')}`;

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

                                <ul
                                    id={submenuId}
                                    className={cn('mt-0.5 ml-6 space-y-px', !isOpen && 'hidden')}
                                >
                                    {section.children?.map((child) => {
                                        const active = isCurrentUrl(child.href);
                                        return (
                                            <li key={child.title}>
                                                <Link
                                                    href={child.href}
                                                    prefetch
                                                    className={childLinkClass(active)}
                                                    aria-current={active ? 'page' : undefined}
                                                >
                                                    {child.title}
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </li>
                        );
                    })}
                </ul>
            </nav>

            {/* Footer */}
            <div className="border-t border-sidebar-border p-3">
                <Link
                    href="/logout"
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
