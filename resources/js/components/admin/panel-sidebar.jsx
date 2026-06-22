import { Link, usePage } from '@inertiajs/react';
import {
    BarChart2,
    Building2,
    ChevronDown,
    CircleDollarSign,
    FileText,
    Globe,
    HandCoins,
    LayoutDashboard,
    LogOut,
    Mail,
    Menu,
    PanelLeftClose,
    PanelLeftOpen,
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
import { useCan } from '@/hooks/use-can';
import { usePanelSidebar } from '@/contexts/panel-sidebar-context';
import { cn } from '@/lib/utils';
import { route } from '@/lib/route';

import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

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
    'file-text': FileText,
    send: Send,
    mail: Mail,
    'phone-call': PhoneCall,
    settings: Settings,
    wallet: Wallet,
    'bar-chart-2': BarChart2,
    shield: Shield,
};

const navSelectedClass =
    'border-blue-900/90 bg-blue-950 font-semibold text-white shadow-[0_4px_14px_-4px_rgba(23,37,84,0.5)] dark:border-blue-800/70 dark:bg-blue-950';

function navLinkClass(active) {
    return cn(
        'group flex items-center gap-2.5 border px-2.5 py-2 text-[0.8125rem] leading-tight tracking-tight transition-[border-color,background-color,color,font-weight,box-shadow]',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400/60 focus-visible:ring-offset-2 focus-visible:ring-offset-sidebar',
        active
            ? cn(navSelectedClass, 'hover:bg-blue-900 dark:hover:bg-blue-900')
            : 'border-transparent font-medium text-sidebar-foreground/90 shadow-none hover:border-border/80 hover:bg-sidebar-accent/60 hover:text-sidebar-foreground',
    );
}

function childLinkClass(active, disabled = false) {
    if (disabled) {
        return cn(
            'block border border-transparent px-2.5 py-2 text-[0.8125rem] leading-tight tracking-tight',
            'cursor-not-allowed font-medium text-sidebar-foreground/45',
        );
    }

    return navLinkClass(active);
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

function SidebarCollapseButton({ collapsed }) {
    const { toggle } = usePanelSidebar();
    const label = collapsed ? 'Expand sidebar' : 'Collapse sidebar';

    const button = (
        <button
            type="button"
            className={cn(
                'flex w-full items-center border border-dashed border-sidebar-border text-muted-foreground transition-colors hover:border-border hover:bg-sidebar-accent/40 hover:text-sidebar-foreground',
                collapsed ? 'justify-center py-1.5' : 'gap-2 px-3 py-2.5 text-xs font-medium',
            )}
            onClick={toggle}
        >
            {collapsed ? (
                <PanelLeftOpen className="size-3 shrink-0" aria-hidden />
            ) : (
                <>
                    <PanelLeftClose className="size-3.5 shrink-0" aria-hidden />
                    Collapse
                </>
            )}
            <span className="sr-only">{label}</span>
        </button>
    );

    if (collapsed) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>{button}</TooltipTrigger>
                <TooltipContent side="right">Expand</TooltipContent>
            </Tooltip>
        );
    }

    return button;
}

export function MobileSidebarTrigger() {
    const { toggle, isMobile } = usePanelSidebar();

    if (!isMobile) {
        return null;
    }

    return (
        <div className="sticky top-0 z-30 flex shrink-0 items-center border-b border-border bg-background px-3 py-2">
            <Button variant="outline" size="icon" className="size-8 shrink-0" onClick={toggle}>
                <Menu className="size-4" />
                <span className="sr-only">Open sidebar</span>
            </Button>
        </div>
    );
}

function canSeeNavItem(can, item) {
    if (!item?.permission) {
        return true;
    }

    return can(item.permission);
}

function filterNavigationByPermission(sections, can) {
    return sections
        .map((section) => {
            if (section.single) {
                return canSeeNavItem(can, section) ? section : null;
            }

            const children = (section.children ?? []).filter((child) => canSeeNavItem(can, child));

            if (children.length === 0) {
                return null;
            }

            return { ...section, children };
        })
        .filter(Boolean);
}

function SidebarContent({ adminNavigation, panelType, currentUrl, isCurrentUrl, expanded, toggle, collapsed }) {
    const panelTitle = panelType === 'branch' ? 'Branch Panel' : 'Admin Panel';

    return (
        <>
            <div className={cn(
                'relative border-b border-sidebar-border bg-linear-to-b from-muted/40 to-transparent transition-[padding] duration-200',
                collapsed ? 'px-1 py-2' : 'px-4 py-5',
            )}>
                <div
                    className="pointer-events-none absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-primary/40 to-transparent"
                    aria-hidden
                />
                {collapsed ? (
                    <div className="flex justify-center">
                        <Building2 className="size-4 text-muted-foreground" />
                    </div>
                ) : (
                    <>
                        <p className="font-mono text-[0.65rem] font-medium uppercase tracking-[0.28em] text-muted-foreground">
                            Coolness Point
                        </p>
                        <p className="mt-1 font-semibold tracking-tight text-foreground">{panelTitle}</p>
                    </>
                )}
            </div>

            <nav className={cn('flex flex-1 flex-col gap-0.5 overflow-y-auto', collapsed ? 'px-0.5 py-1' : 'gap-1 p-3')} aria-label={`${panelTitle} navigation`}>
                <ul className={cn('flex flex-col', collapsed ? 'gap-px' : 'gap-0.5')}>
                    {adminNavigation.map((section) => {
                        const SectionIcon = iconMap[section.icon] ?? LayoutDashboard;

                        if (section.single) {
                            const dashboardPaths =
                                panelType === 'branch'
                                    ? ['/branch-panel']
                                    : ['/dashboard', '/admin'];
                            const active = section.href
                                ? isCurrentUrl(section.href) ||
                                  (section.title === 'Dashboard' &&
                                      dashboardPaths.some((path) => isCurrentUrl(path)))
                                : false;

                            if (collapsed) {
                                return (
                                    <li key={section.title}>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <NavItem href={section.href} className={cn(navLinkClass(active), 'justify-center px-0 py-1.5')} active={active}>
                                                    <SectionIcon
                                                        className={cn(
                                                            'size-3.5 shrink-0',
                                                            active
                                                                ? 'text-white'
                                                                : 'text-muted-foreground group-hover:text-foreground',
                                                        )}
                                                        strokeWidth={active ? 2.25 : 2}
                                                        aria-hidden
                                                    />
                                                </NavItem>
                                            </TooltipTrigger>
                                            <TooltipContent side="right">{section.title}</TooltipContent>
                                        </Tooltip>
                                    </li>
                                );
                            }

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

                        if (collapsed) {
                            return (
                                <li key={section.title}>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <button
                                                type="button"
                                                className={cn(
                                                    'flex w-full items-center justify-center border border-transparent py-1.5 text-left transition-[border-color,background-color,color]',
                                                    'hover:border-border/80 hover:bg-sidebar-accent/50',
                                                    sectionActive ? 'text-foreground' : 'text-sidebar-foreground',
                                                )}
                                                onClick={() => toggle(section.title)}
                                            >
                                                <SectionIcon
                                                    className={cn(
                                                        'size-3.5 shrink-0',
                                                        sectionActive ? 'text-primary' : 'text-muted-foreground',
                                                    )}
                                                    strokeWidth={2}
                                                    aria-hidden
                                                />
                                            </button>
                                        </TooltipTrigger>
                                        <TooltipContent side="right">{section.title}</TooltipContent>
                                    </Tooltip>
                                </li>
                            );
                        }

                        return (
                            <li key={section.title}>
                                <button
                                    type="button"
                                    className={cn(
                                        'flex w-full items-center gap-2.5 border border-transparent px-2.5 py-1.5 text-left text-[0.8125rem] font-medium tracking-tight transition-[border-color,background-color,color]',
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
                                            sectionActive && 'text-blue-950 dark:text-blue-400',
                                        )}
                                        aria-hidden
                                    />
                                </button>

                                <ul id={submenuId} className={cn('mt-0.5 flex flex-col gap-0.5', !isOpen && 'hidden')}>
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

            <div className={cn('flex flex-col border-t border-sidebar-border', collapsed ? 'gap-px px-0.5 py-1' : 'gap-1 p-3')}>
                <SidebarCollapseButton collapsed={collapsed} />
                {collapsed ? (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="flex w-full items-center justify-center border border-dashed border-sidebar-border py-1.5 text-muted-foreground transition-colors hover:border-border hover:bg-sidebar-accent/40 hover:text-sidebar-foreground"
                            >
                                <LogOut className="size-3 shrink-0" aria-hidden />
                            </Link>
                        </TooltipTrigger>
                        <TooltipContent side="right">Logout</TooltipContent>
                    </Tooltip>
                ) : (
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="flex w-full items-center gap-2 border border-dashed border-sidebar-border px-3 py-2.5 text-xs font-medium text-muted-foreground transition-colors hover:border-border hover:bg-sidebar-accent/40 hover:text-sidebar-foreground"
                    >
                        <LogOut className="size-3.5 shrink-0" aria-hidden />
                        Logout
                    </Link>
                )}
            </div>
        </>
    );
}

export function PanelSidebar() {
    const { adminNavigation = [], panelType = 'admin' } = usePage().props;
    const { can } = useCan();
    const { currentUrl, isCurrentUrl } = useCurrentUrl();
    const { collapsed, mobileOpen, closeMobile, isMobile } = usePanelSidebar();
    const visibleNavigation =
        panelType === 'admin' ? filterNavigationByPermission(adminNavigation, can) : adminNavigation;

    const [expanded, setExpanded] = useState(() => {
        return new Set(
            visibleNavigation
                .filter((s) => !s.single && s.children?.some((c) => c.href && isCurrentUrl(c.href)))
                .map((s) => s.title),
        );
    });

    useEffect(() => {
        setExpanded((prev) => {
            const next = new Set(prev);
            let changed = false;
            for (const s of visibleNavigation) {
                if (s.single) continue;
                const hasActive = s.children?.some((c) => c.href && isCurrentUrl(c.href));
                if (hasActive && !next.has(s.title)) {
                    next.add(s.title);
                    changed = true;
                }
            }
            return changed ? next : prev;
        });
    }, [currentUrl, visibleNavigation]);

    const toggle = (title) =>
        setExpanded((prev) => {
            const next = new Set(prev);
            next.has(title) ? next.delete(title) : next.add(title);
            return next;
        });

    if (isMobile) {
        return (
            <Sheet open={mobileOpen} onOpenChange={(open) => !open && closeMobile()}>
                <SheetContent side="left" className="w-72 p-0 [&>button]:hidden">
                    <SheetTitle className="sr-only">Navigation</SheetTitle>
                    <aside className="flex h-full flex-col bg-sidebar text-sidebar-foreground">
                        <SidebarContent
                            adminNavigation={visibleNavigation}
                            panelType={panelType}
                            currentUrl={currentUrl}
                            isCurrentUrl={isCurrentUrl}
                            expanded={expanded}
                            toggle={toggle}
                            collapsed={false}
                        />
                    </aside>
                </SheetContent>
            </Sheet>
        );
    }

    return (
        <aside
            className={cn(
                'flex h-full min-h-0 shrink-0 flex-col border-r border-border bg-sidebar text-sidebar-foreground transition-[width] duration-200',
                collapsed ? 'w-10' : 'w-64',
            )}
            data-collapsed={collapsed || undefined}
        >
            <SidebarContent
                adminNavigation={visibleNavigation}
                panelType={panelType}
                currentUrl={currentUrl}
                isCurrentUrl={isCurrentUrl}
                expanded={expanded}
                toggle={toggle}
                collapsed={collapsed}
            />
        </aside>
    );
}

