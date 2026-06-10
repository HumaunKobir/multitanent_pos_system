import { PanelSidebar } from '@/components/admin/panel-sidebar';

export default function AdminLayout({ children }) {
    return (
        <div data-panel-shell className="flex h-dvh w-full overflow-hidden bg-background">
            <PanelSidebar />
            <div className="flex min-h-0 flex-1 flex-col border-l border-border">
                <main data-panel-main className="min-h-0 flex-1 overflow-y-auto overscroll-contain">{children}</main>
            </div>
        </div>
    );
}
