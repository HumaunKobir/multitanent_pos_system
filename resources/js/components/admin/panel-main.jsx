import { PanelGuideButton } from '@/components/admin/panel-guide-button';
import { MobileSidebarTrigger } from '@/components/admin/panel-sidebar';
import { SubscriptionAlertBanner } from '@/components/admin/subscription-alert-banner';

export function PanelMain({ children }) {
    return (
        <>
            <MobileSidebarTrigger />
            <SubscriptionAlertBanner />
            <PanelGuideButton />
            {children}
        </>
    );
}
