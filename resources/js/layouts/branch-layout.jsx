import { PanelMain } from '@/components/admin/panel-main';
import { PanelSidebar } from '@/components/admin/panel-sidebar';
import { PanelSidebarProvider } from '@/contexts/panel-sidebar-context';
import { TooltipProvider } from '@/components/ui/tooltip';

export default function BranchLayout({ children }) {
    return (
        <TooltipProvider>
            <PanelSidebarProvider>
                <div data-panel-shell className="flex h-dvh w-full overflow-hidden bg-background">
                    <PanelSidebar />
                    <div className="relative flex min-h-0 flex-1 flex-col border-l border-border">
                        <main data-panel-main className="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                            <PanelMain>{children}</PanelMain>
                        </main>
                    </div>
                </div>
            </PanelSidebarProvider>
        </TooltipProvider>
    );
}
