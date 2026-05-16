import { AdminSidebar } from '@/components/admin/admin-sidebar';

export default function AdminLayout({ children }) {
    return (
        <div className="flex min-h-screen w-full bg-background">
            <AdminSidebar />
            <div className="flex min-h-screen flex-1 flex-col border-l border-border">
                <main className="flex-1 overflow-auto">{children}</main>
            </div>
        </div>
    );
}
