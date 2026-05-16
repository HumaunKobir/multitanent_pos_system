import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { AppContent } from '@/components/app-content';
import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
export default function AppHeaderLayout({ children, breadcrumbs, }) {
    return (_jsxs(AppShell, { variant: "header", children: [_jsx(AppHeader, { breadcrumbs: breadcrumbs }), _jsx(AppContent, { variant: "header", children: children })] }));
}
