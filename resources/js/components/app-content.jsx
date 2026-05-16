import { jsx as _jsx } from "react/jsx-runtime";
import { SidebarInset } from '@/components/ui/sidebar';
export function AppContent({ variant = 'sidebar', children, ...props }) {
    if (variant === 'sidebar') {
        return _jsx(SidebarInset, { ...props, children: children });
    }

    return (_jsx("main", { className: "mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-none", ...props, children: children }));
}
