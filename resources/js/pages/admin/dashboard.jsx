import { Head } from '@inertiajs/react';
import { Activity, Boxes, Users } from 'lucide-react';

import { DataTable } from '@/components/ui/data-table';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { LabelPill, StatusPill } from '@/components/ui/table-pills';

const recentOperations = [
    {
        ref: 'ADM-204',
        owner: 'Operations',
        status: 'Complete',
        tone: 'success',
        tags: ['EU', 'Batch'],
        time: '09:42',
    },
    {
        ref: 'ADM-205',
        owner: 'Fulfillment',
        status: 'In review',
        tone: 'warning',
        tags: ['Priority'],
        time: '10:01',
    },
    {
        ref: 'ADM-206',
        owner: 'Finance',
        status: 'Queued',
        tone: 'pending',
        tags: ['Ledger', 'Nightly'],
        time: '10:18',
    },
];

export default function AdminDashboard() {
    return (
        <>
            <Head title="Admin overview" />
            <div className="mx-auto max-w-6xl space-y-8 px-6 py-8">
                <header className="border-b border-border pb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">Overview</h1>
                    <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                        Administrative snapshot with neutral metrics cards and a compact operations table. Every surface
                        stays square-edged to match the global UI policy.
                    </p>
                </header>

                <section className="grid gap-4 md:grid-cols-3">
                    <Card className="rounded-none border-border shadow-none">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Active sessions</CardTitle>
                            <Users className="size-4 text-muted-foreground" aria-hidden />
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-semibold tabular-nums">128</p>
                            <CardDescription className="mt-1">Rolling 24h window</CardDescription>
                        </CardContent>
                    </Card>

                    <Card className="rounded-none border-border shadow-none">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Inventory SKUs</CardTitle>
                            <Boxes className="size-4 text-muted-foreground" aria-hidden />
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-semibold tabular-nums">842</p>
                            <CardDescription className="mt-1">Synced across regions</CardDescription>
                        </CardContent>
                    </Card>

                    <Card className="rounded-none border-border shadow-none">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Automation jobs</CardTitle>
                            <Activity className="size-4 text-muted-foreground" aria-hidden />
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-semibold tabular-nums">36</p>
                            <CardDescription className="mt-1">Queues healthy · 0 blocked</CardDescription>
                        </CardContent>
                    </Card>
                </section>

                <section className="space-y-3">
                    <div>
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                            Recent operations
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Demo grid using the shared <span className="font-mono text-foreground/80">DataTable</span> with{' '}
                            <span className="font-mono text-foreground/80">StatusPill</span> and{' '}
                            <span className="font-mono text-foreground/80">LabelPill</span> for tags.
                        </p>
                    </div>
                    <DataTable
                        caption="Synthetic operations for layout preview"
                        rowKey="ref"
                        rows={recentOperations}
                        columns={[
                            {
                                id: 'ref',
                                header: 'Reference',
                                accessorKey: 'ref',
                                cellClassName: 'font-mono text-xs text-foreground',
                            },
                            {
                                id: 'owner',
                                header: 'Owner',
                                accessorKey: 'owner',
                            },
                            {
                                id: 'status',
                                header: 'Status',
                                render: (row) => <StatusPill tone={row.tone}>{row.status}</StatusPill>,
                            },
                            {
                                id: 'tags',
                                header: 'Tags',
                                render: (row) => (
                                    <div className="flex max-w-[220px] flex-wrap gap-1.5">
                                        {row.tags.map((tag) => (
                                            <LabelPill key={tag}>{tag}</LabelPill>
                                        ))}
                                    </div>
                                ),
                            },
                            {
                                id: 'time',
                                header: 'Updated',
                                accessorKey: 'time',
                                align: 'right',
                                headerClassName: 'text-right',
                                cellClassName: 'tabular-nums text-muted-foreground',
                            },
                        ]}
                    />
                </section>
            </div>
        </>
    );
}
