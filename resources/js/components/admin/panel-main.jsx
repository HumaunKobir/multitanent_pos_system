import { PanelGuideButton } from '@/components/admin/panel-guide-button';
import { MobileSidebarTrigger } from '@/components/admin/panel-sidebar';

export function PanelMain({ children }) {
    return (
        <>
            <MobileSidebarTrigger />
            <PanelGuideButton />
            {children}
        </>
    );
}
