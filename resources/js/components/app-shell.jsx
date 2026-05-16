import { usePage } from '@inertiajs/react';
import { jsx as _jsx } from "react/jsx-runtime";
import { SidebarProvider } from '@/components/ui/sidebar';
export function AppShell({ children, variant = 'sidebar' }) {
    const isOpen = usePage().props.sidebarOpen;

    if (variant === 'header') {
        return (_jsx("div", { className: "flex min-h-screen w-full flex-col", children: children }));
    }

    return _jsx(SidebarProvider, { defaultOpen: isOpen, children: children });
}
