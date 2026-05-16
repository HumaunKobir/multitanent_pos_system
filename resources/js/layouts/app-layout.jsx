import { jsx as _jsx } from "react/jsx-runtime";
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
export default function AppLayout({ breadcrumbs = [], children, }) {
    return (_jsx(AppLayoutTemplate, { breadcrumbs: breadcrumbs, children: children }));
}
