import { DataTable } from '@/components/ui/data-table';
import { useAppToast } from '@/contexts/app-toast-context';
import { Head, router, usePage } from '@inertiajs/react';
import { Package, Pencil, Plus, RotateCcw, Search, Trash2, Inbox, ChevronDown } from 'lucide-react';
import { useEffect, useState } from 'react';

import { ListDateExportBar } from '@/components/admin/list-date-export-bar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AdminCreateLink, AdminRowActions } from '@/components/admin/row-actions';
import { AdminPagination } from '@/components/admin/pagination';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';
import { route } from '@/lib/route';

export default function ProductIndex({ products, filters, categories, brands, tags, branches = {}, mainBranchId = null, pendingReceiveProducts = [], showSelectedBranchColumn = false }) {
    const { flash, auth, panelType } = usePage().props;
    const isSuperAdmin = !auth.user?.branch_id;
    const canManageMainCatalog = panelType === 'admin';
    const defaultBranchId = mainBranchId != null ? String(mainBranchId) : 'all';
    const toast = useAppToast();
    const { can } = useCan();
    const [search, setSearch] = useState(filters.search ?? '');
    const [categoryId, setCategoryId] = useState(filters.category_id ?? '__all');
    const [brandId, setBrandId] = useState(filters.brand_id ?? '__all');
    const [tag, setTag] = useState(filters.tag ?? '__all');
    const [status, setStatus] = useState(filters.status ?? 'active');
    const [branchId, setBranchId] = useState(filters.branch_id ?? defaultBranchId);
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [deleting, setDeleting] = useState(null);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useDebouncedEffect(
        () => {
            router.get(
                route('product.index'),
                {
                    search: search || undefined,
                    category_id: categoryId === '__all' ? undefined : categoryId,
                    brand_id: brandId === '__all' ? undefined : brandId,
                    tag: tag === '__all' ? undefined : tag,
                    status: status === 'active' ? undefined : status,
                    date_from: dateFrom || undefined,
                    date_to: dateTo || undefined,
                    ...(isSuperAdmin ? { branch_id: branchId } : {}),
                },
                { preserveState: true, replace: true },
            );
        },
        [search, categoryId, brandId, tag, status, branchId, dateFrom, dateTo, isSuperAdmin],
        350,
        { skipFirstRun: true },
    );

    function handleReset() {
        setSearch('');
        setCategoryId('__all');
        setBrandId('__all');
        setTag('__all');
        setStatus('active');
        setDateFrom('');
        setDateTo('');
        if (isSuperAdmin) {
            setBranchId(defaultBranchId);
        }
    }

    function handleDelete() {
        if (!deleting) return;
        router.delete(route('product.destroy', deleting.slug), {
            onSuccess: () => setDeleting(null),
        });
    }

    const categoryOptions = Object.entries(categories || {}).map(([value, label]) => ({ value, label }));
    const brandOptions = Object.entries(brands || {}).map(([value, label]) => ({ value, label }));
    const branchOptions = Object.entries(branches || {}).map(([value, label]) => ({ value, label }));

    const flatRows = (products.data ?? []).flatMap((product) => {
        if (!product.variations?.length) {
            return [{ ...product, _rowKey: `p-${product.id}`, _isVariant: false, _showProductActions: true }];
        }

        return product.variations.map((v, index) => ({
            ...product,
            _rowKey: `v-${v.id}`,
            _isVariant: true,
            _showProductActions: index === 0,
            _variantLabel: v.variation_data?.label ?? v.sku,
            _variantPrice: parseFloat(v.price ?? 0),
            _variantPurchasePrice: parseFloat(v.purchase_price ?? 0),
            _variantStock: v.stock,
        }));
    });

    const columns = [
        {
            id: 'num',
            header: '#',
            render: (_, i) => Number(products.from ?? 1) + i,
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
                    {row._isVariant ? (
                        <p className="text-xs font-medium text-blue-700">{row._variantLabel}</p>
                    ) : (
                        <p className="text-xs text-muted-foreground">{row.code}</p>
                    )}
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
        ...(showSelectedBranchColumn
            ? [
                  {
                      id: 'selected_branch',
                      header: 'Selected Branch',
                      render: (row) => row.selected_branch?.name ?? '—',
                  },
              ]
            : []),
        {
            id: 'price',
            header: 'Price',
            render: (row) => {
                const buy = row._isVariant ? row._variantPurchasePrice : parseFloat(row.purchase_price ?? 0);
                const sell = row._isVariant ? row._variantPrice : parseFloat(row.sale_price ?? 0);
                return (
                    <div>
                        <p className="text-xs text-muted-foreground">Buy: ৳{buy.toFixed(2)}</p>
                        <p className="font-medium">৳{sell.toFixed(2)}</p>
                    </div>
                );
            },
        },
        {
            id: 'stock',
            header: 'Stock',
            render: (row) => {
                const stock = row._isVariant
                    ? parseFloat(row._variantStock ?? 0)
                    : parseFloat(row.batches_sum_available ?? 0) || 0;
                const branchInitialStock = row.submission_stock_summary?.total ?? 0;
                const isReceivedFromBranch = row.source_branch_id != null && row.received_at != null;

                if (isReceivedFromBranch && stock === 0 && branchInitialStock > 0) {
                    return (
                        <div>
                            <span className="font-medium">0</span>
                            <p className="text-xs font-medium text-amber-700 dark:text-amber-400">
                                Branch initial stock: {branchInitialStock}
                            </p>
                        </div>
                    );
                }

                return <span className="font-medium">{stock}</span>;
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
                <AdminRowActions
                    prefix="product"
                    id={row.slug}
                    editRoute="product.edit"
                    onDelete={row._showProductActions ? () => setDeleting(row) : undefined}
                />
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
                    <div className="flex items-center gap-2">
                        {canManageMainCatalog && pendingReceiveProducts.length > 0 && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        size="sm"
                                        className="border border-amber-300/60 bg-amber-400/15 text-amber-50 backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-amber-200/70 hover:bg-amber-400/25 hover:shadow-md"
                                    >
                                        <Inbox className="size-3.5" />
                                        Pending Receive
                                        <Badge className="ml-1 h-5 min-w-5 border-amber-200/40 bg-amber-500 px-1.5 text-[10px] text-white">
                                            {pendingReceiveProducts.length}
                                        </Badge>
                                        <ChevronDown className="size-3.5 opacity-70" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-[min(100vw-2rem,24rem)] p-1">
                                    <DropdownMenuLabel className="text-xs text-muted-foreground">
                                        Pending receive from branches
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    {pendingReceiveProducts.map((item) => (
                                        <DropdownMenuItem
                                            key={item.id}
                                            className="cursor-default items-start rounded-md p-0 focus:bg-transparent"
                                            onSelect={(event) => event.preventDefault()}
                                        >
                                            <div className="flex w-full items-start justify-between gap-2 rounded-md border border-transparent px-2 py-2 hover:border-border hover:bg-accent/40">
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium">{item.name}</p>
                                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                                        {item.branch_name ?? 'Branch'}
                                                        {item.code ? ` · ${item.code}` : ''}
                                                    </p>
                                                    {item.stock_summary?.total > 0 && (
                                                        <p className="mt-1 text-xs font-medium text-amber-700 dark:text-amber-400">
                                                            Branch initial stock: {item.stock_summary.total}
                                                        </p>
                                                    )}
                                                </div>
                                                {can('product.update') && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="shrink-0"
                                                        onClick={() => router.post(route('product.receive', { product: item.slug }))}
                                                    >
                                                        Receive
                                                    </Button>
                                                )}
                                            </div>
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                        <AdminCreateLink
                            permission="product.create"
                            href={route('product.create')}
                            label="Add Product"
                            icon={Plus}
                            className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                        />
                    </div>
                </div>

                <div className="mb-4 flex flex-wrap items-end gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name, code, brand, category, tag..."
                        className="max-w-xs"
                    />
                    {isSuperAdmin && (
                        <Select value={branchId} onValueChange={(v) => setBranchId(v)}>
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="Branch" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All branches</SelectItem>
                                {branchOptions.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
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
                    <Select value={brandId} onValueChange={(v) => setBrandId(v)}>
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All brands" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all">All brands</SelectItem>
                            {brandOptions.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={tag} onValueChange={(v) => setTag(v)}>
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All tags" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all">All tags</SelectItem>
                            {(tags || []).map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={status} onValueChange={(v) => setStatus(v)}>
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Inactive</SelectItem>
                            <SelectItem value="all">All statuses</SelectItem>
                        </SelectContent>
                    </Select>
                    <ListDateExportBar
                        dateFrom={dateFrom}
                        dateTo={dateTo}
                        onDateFromChange={setDateFrom}
                        onDateToChange={setDateTo}
                        exportExcelRoute="product.export-excel"
                        exportPdfRoute="product.export-pdf"
                        exportPrintRoute="product.export-print"
                        query={{
                            search,
                            category_id: categoryId,
                            brand_id: brandId,
                            tag,
                            status,
                            ...(isSuperAdmin ? { branch_id: branchId } : {}),
                        }}
                    />
                    <Button variant="outline" size="icon" onClick={handleReset} title="Reset filters">
                        <RotateCcw className="size-4" />
                    </Button>
                </div>

                <DataTable
                    columns={columns}
                    rows={flatRows}
                    rowKey="_rowKey"
                    emptyMessage="No products found."
                    getRowProps={(row) => row._isVariant ? { className: 'bg-blue-50/40' } : {}}
                />

                <AdminPagination paginator={products} />
            </div>

            {can('product.delete') && (
            <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                <DialogContent className="p-0">
                    <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                            <Trash2 className="size-3.5 text-white" />
                        </div>
                        <h2 className="text-sm font-semibold text-white">Remove Product</h2>
                    </div>
                    <div className="px-5 pb-5 pt-4">
                        <p className="text-sm text-muted-foreground">
                            Are you sure you want to remove <strong>{deleting?.name}</strong>?
                            {' '}Products with no transaction history are deleted permanently.
                            {' '}Products linked to purchases, sales, returns, damages, or exchanges are archived instead so history stays intact.
                        </p>
                        <DialogFooter className="mt-4">
                            <DialogClose asChild>
                                <Button variant="outline">Cancel</Button>
                            </DialogClose>
                            <Button variant="destructive" onClick={handleDelete}>
                                Remove
                            </Button>
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>
            )}
        </>
    );
}
