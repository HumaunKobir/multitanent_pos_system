import { PanelSidebar } from '@/components/admin/panel-sidebar';

export default function BranchLayout({ children }) {
    return (
        <div className="flex min-h-screen w-full bg-background">
            <PanelSidebar />
            <div className="flex min-h-screen flex-1 flex-col border-l border-border">
                <main className="flex-1 overflow-auto">{children}</main>
            </div>
        </div>
    );
}
