import { createInertiaApp } from '@inertiajs/react';
import { AppToastRegion } from '@/components/app-toast-region';
import { TooltipProvider } from '@/components/ui/tooltip';
import { AppToastProvider } from '@/contexts/app-toast-context';
import { initializeTheme } from '@/hooks/use-appearance';
import AdminLayout from '@/layouts/admin-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('frontend/'):
                return null;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name.startsWith('admin/'):
                return AdminLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <AppToastProvider>
                <TooltipProvider delayDuration={0}>
                    {app}
                    <AppToastRegion />
                </TooltipProvider>
            </AppToastProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

initializeTheme();
