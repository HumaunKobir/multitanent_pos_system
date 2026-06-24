import { createInertiaApp, usePage } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { AppToastRegion } from '@/components/app-toast-region';
import { TooltipProvider } from '@/components/ui/tooltip';
import { AppToastProvider } from '@/contexts/app-toast-context';
import { initializeTheme } from '@/hooks/use-appearance';
import { preventNumberInputScroll } from '@/lib/prevent-number-input-scroll';
import AdminLayout from '@/layouts/admin-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import BranchLayout from '@/layouts/branch-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

function StaffLayout({ children }) {
    const { panelType } = usePage().props;
    const Layout = panelType === 'branch' ? BranchLayout : AdminLayout;

    return <Layout>{children}</Layout>;
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name === 'auth/login':
            case name === 'auth/forgot-password':
            case name === 'auth/verify-password-reset':
            case name === 'auth/reset-password':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('frontend/'):
                return null;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name.startsWith('branch-panel/'):
                return BranchLayout;
            case name.startsWith('admin/'):
                return StaffLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    setup({ el, App, props }) {
        const root = window.__inertia_root__ ?? createRoot(el);
        window.__inertia_root__ = root;
        root.render(
            <AppToastProvider>
                <TooltipProvider delayDuration={0}>
                    <App {...props} />
                    <AppToastRegion />
                </TooltipProvider>
            </AppToastProvider>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

initializeTheme();
preventNumberInputScroll();
