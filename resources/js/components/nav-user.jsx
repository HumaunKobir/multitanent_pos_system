import { usePage } from '@inertiajs/react';
import { ChevronsUpDown } from 'lucide-react';
import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger, } from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar, } from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { useIsMobile } from '@/hooks/use-mobile';
export function NavUser() {
    const { auth } = usePage().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    if (!auth.user) {
        return null;
    }

    return (_jsx(SidebarMenu, { children: _jsx(SidebarMenuItem, { children: _jsxs(DropdownMenu, { children: [_jsx(DropdownMenuTrigger, { asChild: true, children: _jsxs(SidebarMenuButton, { size: "lg", className: "group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent", "data-test": "sidebar-menu-button", children: [_jsx(UserInfo, { user: auth.user }), _jsx(ChevronsUpDown, { className: "ml-auto size-4" })] }) }), _jsx(DropdownMenuContent, { className: "w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-none", align: "end", side: isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                                ? 'left'
                                : 'bottom', children: _jsx(UserMenuContent, { user: auth.user }) })] }) }) }));
}
