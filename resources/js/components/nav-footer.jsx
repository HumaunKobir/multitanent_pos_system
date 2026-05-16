import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { SidebarGroup, SidebarGroupContent, SidebarMenu, SidebarMenuButton, SidebarMenuItem, } from '@/components/ui/sidebar';
import { toUrl } from '@/lib/utils';
export function NavFooter({ items, className, ...props }) {
    return (_jsx(SidebarGroup, { ...props, className: `group-data-[collapsible=icon]:p-0 ${className || ''}`, children: _jsx(SidebarGroupContent, { children: _jsx(SidebarMenu, { children: items.map((item) => (_jsx(SidebarMenuItem, { children: _jsx(SidebarMenuButton, { asChild: true, className: "text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100", children: _jsxs("a", { href: toUrl(item.href), target: "_blank", rel: "noopener noreferrer", children: [item.icon && (_jsx(item.icon, { className: "h-5 w-5" })), _jsx("span", { children: item.title })] }) }) }, item.title))) }) }) }));
}
