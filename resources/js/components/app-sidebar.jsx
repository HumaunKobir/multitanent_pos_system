import { Link } from '@inertiajs/react';
import { LayoutGrid, Shield } from 'lucide-react';

import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { dashboard as adminDashboard } from '@/routes/admin';
import { dashboard } from '@/routes';
import { cn } from '@/lib/utils';

const mainNavItems = [
    {
        title: 'Dashboard',
        href: dashboard.url(),
        icon: LayoutGrid,
    },
    {
        title: 'Admin',
        href: adminDashboard.url(),
        icon: Shield,
        activeMatch: 'prefix',
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="gap-0 border-b border-sidebar-border/70 p-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className={cn(
                                'h-auto min-h-12 border border-transparent px-2 transition-colors',
                                'hover:border-border/80 hover:bg-sidebar-accent/50',
                            )}
                        >
                            <Link href={dashboard.url()} prefetch className="flex items-center gap-0">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent className="px-0 pt-2">
                <NavMain items={mainNavItems} />
            </SidebarContent>
            <SidebarFooter className="gap-0 border-t border-sidebar-border/70 p-2">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
