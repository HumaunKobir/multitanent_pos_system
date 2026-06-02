import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Package, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { route } from '@/lib/route';

export default function ProductIndex({ products, filters, categories }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const [search, setSearch] = useState(filters.search ?? '');
    const [categoryId, setCategoryId] = useState(filters.category_id ?? '__all');
    const [deleting, setDeleting] = useState(null);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () => {
            router.get(
                route('product.index'),
                { search: search || undefined, category_id: categoryId === '__all' ? undefined : categoryId },
                { preserveState: true, replace: true },
            );
        },
        [search, categoryId],
        350,
        { skipFirstRun: true },
    );

    function handleDelete() {
        if (!deleting) return;
        router.delete(route('product.destroy', deleting.slug), {
            onSuccess: () => setDeleting(null),
        });
    }

    const categoryOptions = Object.entries(categories || {}).map(([value, label]) => ({ value, label }));

    const columns = [
        {
            id: 'num',
            header: '#',
            render: (_, i) => (products.from ?? 0) + i,
        },
        {
            id: 'image',
            header: 'Image',
            render: (row) =>
                row.image ? (
                    <img src={`/storage/${row.image}`} alt={row.name} className="h-12 w-12 rounded object-cover" />
                ) : (
                    <div className="flex h-12 w-12 items-center justify-center rounded bg-muted text-xs text-muted-foreground">No img</div>
                ),
        },
        {
            id: 'name',
            header: 'Name / Code',
            render: (row) => (
                <div>
                    <p className="font-medium">{row.name}</p>
                    <p className="text-xs text-muted-foreground">{row.code}</p>
                </div>
            ),
        },
        {
            id: 'category',
            header: 'Category',
            render: (row) => row.category?.name ?? '—',
        },
        {
            id: 'brand',
            header: 'Brand',
            render: (row) => row.brand?.name ?? '—',
        },
        {
            id: 'price',
            header: 'Price',
            render: (row) => (
                <div>
                    <p className="text-xs text-muted-foreground">Buy: ৳{row.purchase_price}</p>
                    <p className="font-medium">৳{row.sale_price}</p>
                </div>
            ),
        },
        {
            id: 'stock',
            header: 'Stock',
            render: (row) => {
                const stock = row.variations_sum_stock ?? 0;
                return (
                    <Badge className={stock > 0 ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-red-600 text-white hover:bg-red-700'}>
                        {stock}
                    </Badge>
                );
            },
        },
        {
            id: 'status',
            header: 'Status',
            render: (row) => (
                <Badge className={row.status ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-red-600 text-white hover:bg-red-700'}>
                    {row.status ? 'Active' : 'Inactive'}
                </Badge>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            align: 'right',
            render: (row) => (
                <div className="flex justify-end gap-2">
                    <Button size="sm" variant="outline" asChild>
                        <Link href={route('product.edit', row.slug)}>
                            <Pencil className="size-3.5" />
                        </Link>
                    </Button>
                    <Button size="sm" variant="destructive" onClick={() => setDeleting(row)}>
                        <Trash2 className="size-3.5" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Products" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Package className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Products</h1>
                            <p className="text-xs text-white/60">Manage your product inventory.</p>
                        </div>
                    </div>
                    <Button size="sm" asChild className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md">
                        <Link href={route('product.create')}>
                            <Plus className="size-3.5" />
                            Add Product
                        </Link>
                    </Button>
                </div>

                <div className="mb-4 flex flex-wrap gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name or code..."
                        className="max-w-xs"
                    />
                    <Select value={categoryId} onValueChange={(v) => setCategoryId(v)}>
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All categories" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all">All categories</SelectItem>
                            {categoryOptions.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable columns={columns} rows={products.data} rowKey="id" emptyMessage="No products found." />

                {products.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {products.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                className={[
                                    'border px-3 py-1 text-sm transition-colors',
                                    link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-accent',
                                    !link.url ? 'pointer-events-none opacity-50' : '',
                                ].join(' ')}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                preserveScroll
                            />
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                <DialogContent className="p-0">
                    <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                            <Trash2 className="size-3.5 text-white" />
                        </div>
                        <h2 className="text-sm font-semibold text-white">Delete Product</h2>
                    </div>
                    <div className="px-5 pb-5 pt-4">
                        <p className="text-sm text-muted-foreground">
                            Are you sure you want to delete <strong>{deleting?.name}</strong>? All photos and variations will also be deleted.
                        </p>
                        <DialogFooter className="mt-4">
                            <DialogClose asChild>
                                <Button variant="outline">Cancel</Button>
                            </DialogClose>
                            <Button variant="destructive" onClick={handleDelete}>
                                Delete
                            </Button>
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
