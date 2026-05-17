import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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

    function handleSearch(e) {
        e.preventDefault();
        router.get(
            route('product.index'),
            { search: search || undefined, category_id: categoryId === '__all' ? undefined : categoryId },
            { preserveState: true, replace: true },
        );
    }

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

            <div className="p-6">
                <div className="mb-5 flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Products</h1>
                    <Button asChild>
                        <Link href={route('product.create')}>
                            <Plus className="size-4" />
                            Add Product
                        </Link>
                    </Button>
                </div>

                <form onSubmit={handleSearch} className="mb-4 flex flex-wrap gap-2">
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
                    <Button type="submit" variant="outline" size="sm">
                        <Search className="size-4" />
                    </Button>
                </form>

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
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Product</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">
                        Are you sure you want to delete <strong>{deleting?.name}</strong>? All photos and variations will also be deleted.
                    </p>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button variant="destructive" onClick={handleDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
