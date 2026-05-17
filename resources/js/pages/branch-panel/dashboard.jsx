import { Head } from '@inertiajs/react';

export default function BranchDashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <div className="p-6">
                <h1 className="text-xl font-semibold">Branch Dashboard</h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    Welcome to your branch panel. Use the sidebar to navigate modules.
                </p>
            </div>
        </>
    );
}
