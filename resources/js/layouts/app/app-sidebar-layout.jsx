import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
export default function AppSidebarLayout({ children, breadcrumbs = [], }) {
    return (_jsxs(AppShell, { variant: "sidebar", children: [_jsx(AppSidebar, {}), _jsxs(AppContent, { variant: "sidebar", className: "overflow-x-hidden", children: [_jsx(AppSidebarHeader, { breadcrumbs: breadcrumbs }), children] })] }));
}
