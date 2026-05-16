import { Link } from '@inertiajs/react';

import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';

/**
 * @typedef {{ title: string; href: string | { url: string }; icon?: import('react').ComponentType<{ className?: string }>; activeMatch?: 'exact' | 'prefix' }} NavItem
 */

/**
 * @param {{ items?: NavItem[] }} props
 */
export function NavMain({ items = [] }) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel className="mb-1 flex items-center gap-2 px-2 text-[0.625rem] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                <span className="h-px flex-1 bg-border" aria-hidden />
                <span className="shrink-0">Menu</span>
                <span className="h-px flex-1 bg-border" aria-hidden />
            </SidebarGroupLabel>
            <SidebarMenu className="gap-0.5">
                {items.map((item) => {
                    const active =
                        item.activeMatch === 'prefix'
                            ? isCurrentOrParentUrl(item.href)
                            : isCurrentUrl(item.href);

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={active}
                                tooltip={{ children: item.title }}
                                className={cn(
                                    'group/nav relative h-auto min-h-9 overflow-hidden border border-transparent py-2 pr-2 pl-2 transition-[border-color,background-color,color,box-shadow]',
                                    'hover:border-border/80 hover:bg-sidebar-accent/60',
                                    active &&
                                        cn(
                                            'border-indigo-500/90 bg-indigo-600 font-semibold text-white shadow-[0_6px_22px_-6px_rgba(79,70,229,0.5),0_2px_8px_-2px_rgba(67,56,202,0.32)]',
                                            'dark:border-indigo-400/70 dark:bg-indigo-700 dark:shadow-[0_6px_22px_-6px_rgba(67,56,202,0.45),0_2px_8px_-2px_rgba(55,48,163,0.35)]',
                                            'hover:bg-indigo-500 dark:hover:bg-indigo-600',
                                        ),
                                )}
                            >
                                <Link
                                    href={item.href}
                                    prefetch
                                    className={cn(
                                        'flex w-full min-w-0 items-center gap-2.5',
                                        'group-data-[collapsible=icon]/sidebar-wrapper:justify-center group-data-[collapsible=icon]/sidebar-wrapper:gap-0',
                                    )}
                                >
                                    {item.icon ? (
                                        <span
                                            className={cn(
                                                'flex size-8 shrink-0 items-center justify-center border border-border/60 bg-muted/40 text-muted-foreground transition-colors',
                                                active &&
                                                    'border-white/30 bg-white/15 text-white dark:border-white/25 dark:bg-white/10',
                                            )}
                                            aria-hidden
                                        >
                                            <item.icon className="size-4 shrink-0" strokeWidth={active ? 2.25 : 2} />
                                        </span>
                                    ) : null}
                                    <span
                                        className={cn(
                                            'truncate text-[0.8125rem] leading-tight tracking-tight',
                                            active ? 'text-white' : 'text-sidebar-foreground/90',
                                            'group-data-[collapsible=icon]/sidebar-wrapper:hidden',
                                        )}
                                    >
                                        {item.title}
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
