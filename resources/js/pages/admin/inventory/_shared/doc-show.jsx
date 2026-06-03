import { formatQty } from '@/components/inventory/inventory-form';
import { useAppToast } from '@/contexts/app-toast-context';
import { formatBdDate } from '@/lib/format-bd-date';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Edit, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

export default function DocShow({ title, invoice, backRoute, editRoute, destroyRoute, id, date, comment, lines = [], extra }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    return (
        <>
            <Head title={`${title} — ${invoice}`} />
            <div className="px-2 py-1">
                <div className="mb-3 flex justify-between rounded-lg bg-blue-950 px-5 py-3 text-white">
                    <div><h1 className="text-base font-semibold">{title}</h1><p className="font-mono text-xs text-white/60">{invoice}</p></div>
                    <div className="flex gap-2">
                        {editRoute && (
                            <Button size="sm" variant="outline" asChild className="border-white/30 text-white">
                                <Link href={route(editRoute, id)}><Edit className="size-3.5" /></Link>
                            </Button>
                        )}
                        <Button size="sm" variant="destructive" onClick={() => setDeleting(true)}><Trash2 className="size-3.5" /></Button>
                        <Button size="sm" variant="outline" asChild className="border-white/30 text-white"><Link href={route(backRoute)}><ArrowLeft className="size-3.5" />Back</Link></Button>
                    </div>
                </div>
                <div className="mb-4 text-sm"><p>Date: {formatBdDate(date)}</p>{comment && <p className="text-muted-foreground">Note: {comment}</p>}{extra}</div>
                <table className="w-full text-xs border rounded-lg">
                    <thead className="bg-muted/50"><tr><th className="p-2 text-left">Product</th><th className="p-2 text-right">Qty</th>{lines[0]?.price != null && <th className="p-2 text-right">Price</th>}</tr></thead>
                    <tbody>{lines.map((l, i) => (
                        <tr key={i} className="border-t"><td className="p-2">{l.name}</td><td className="p-2 text-right">{formatQty(l.qty)}</td>{l.price != null && <td className="p-2 text-right">৳{parseFloat(l.price).toFixed(2)}</td>}</tr>
                    ))}</tbody>
                </table>
                <Dialog open={deleting} onOpenChange={setDeleting}>
                    <DialogContent className="max-w-sm">
                        <DialogHeader><DialogTitle>Delete?</DialogTitle><DialogDescription>This cannot be undone.</DialogDescription></DialogHeader>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild><Button variant="outline" size="sm">Cancel</Button></DialogClose>
                            <Button variant="destructive" size="sm" onClick={() => router.delete(route(destroyRoute, id))}>Delete</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
