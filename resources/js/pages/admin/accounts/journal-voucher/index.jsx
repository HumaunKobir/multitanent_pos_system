import { Head } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';

export default function JournalVoucherIndex() {
    return (
        <>
            <Head title="Journal Voucher" />

            <div className="flex flex-col gap-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex size-10 items-center justify-center border border-border bg-muted/40">
                            <BookOpen className="size-5 text-muted-foreground" />
                        </div>
                        <div>
                            <h1 className="text-lg font-semibold tracking-tight">Journal Voucher</h1>
                            <p className="text-sm text-muted-foreground">General journal entries</p>
                        </div>
                    </div>
                </div>

                <div className="flex items-center justify-center rounded-lg border border-dashed border-border py-24 text-sm text-muted-foreground">
                    Journal voucher list coming soon
                </div>
            </div>
        </>
    );
}
