import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FileSpreadsheet, Pencil, Plus, RotateCcw, Search, Trash2, Users } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { FormField } from '@/components/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogClose, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AdminCreateButton, AdminInlineActions } from '@/components/admin/row-actions';
import { Can } from '@/components/can';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';
import { normalizeOptionalNumeric } from '@/lib/form-numeric';

function createCustomerFormDefaults() {
    return {
        name: '',
        phone: '',
        email: '',
        address: '',
        opening_balance: '',
        is_default: '0',
        status: 1,
    };
}

function editCustomerFormDefaults() {
    return {
        name: '',
        phone: '',
        email: '',
        address: '',
        is_default: '0',
        status: 1,
    };
}

function buildCustomerReportUrl(customerId, dateFrom, dateTo) {
    const params = new URLSearchParams();

    if (dateFrom) {
        params.set('date_from', dateFrom);
    }

    if (dateTo) {
        params.set('date_to', dateTo);
    }

    const base = route('party.customer.report', { customer: customerId });

    return params.toString() ? `${base}?${params.toString()}` : base;
}

function buildBulkReportUrl(selectedIds, dateFrom, dateTo) {
    const params = new URLSearchParams();

    selectedIds.forEach((id) => params.append('customer_ids[]', String(id)));

    if (dateFrom) {
        params.set('date_from', dateFrom);
    }

    if (dateTo) {
        params.set('date_to', dateTo);
    }

    return `${route('party.customer.bulk-report')}?${params.toString()}`;
}

function CustomerForm({ form, onSubmit, onCancel, isEditing, statuses }) {
    return (
        <form onSubmit={onSubmit} className="space-y-1.5 px-3 py-2">
            <div className="grid grid-cols-2 gap-3">
                <FormField label="Name" name="name" error={form.errors.name}>
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Customer name"
                        className="mt-1"
                        aria-invalid={!!form.errors.name}
                    />
                </FormField>
                <FormField label="Phone" required name="phone" error={form.errors.phone}>
                    <Input
                        id="phone"
                        value={form.data.phone}
                        onChange={(e) => form.setData('phone', e.target.value)}
                        placeholder="Phone Number"
                        className="mt-1"
                        aria-invalid={!!form.errors.phone}
                    />
                </FormField>
            </div>
            <div className="grid grid-cols-2 gap-3">
                <FormField label="Email" name="email" error={form.errors.email}>
                    <Input
                        id="email"
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        placeholder="Email Address"
                        className="mt-1"
                    />
                </FormField>
            </div>
            <FormField label="Address" name="address" error={form.errors.address}>
                <Input
                    id="address"
                    value={form.data.address}
                    onChange={(e) => form.setData('address', e.target.value)}
                    placeholder="Address"
                    className="mt-1"
                />
            </FormField>
            {!isEditing && (
                <FormField label="Opening Balance" name="opening_balance" error={form.errors.opening_balance}>
                    <Input
                        id="opening_balance"
                        type="number"
                        min="0"
                        step="0.01"
                        value={form.data.opening_balance}
                        onChange={(e) => form.setData('opening_balance', e.target.value)}
                        placeholder="0"
                        className="mt-1"
                    />
                </FormField>
            )}
            <FormField label="Status" required name="status" error={form.errors.status}>
                    <Select
                        value={String(form.data.status)}
                        onValueChange={(v) => form.setData('status', parseInt(v))}
                    >
                        <SelectTrigger id="status" className="mt-1 w-full" aria-invalid={!!form.errors.status}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {statuses.filter((s) => s.value !== 0).map((s) => (
                                <SelectItem key={s.value} value={String(s.value)}>{s.name}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
            </FormField>
            <div className="flex items-center justify-between rounded-md border border-input px-3 py-2">
                <div>
                    <p className="text-sm font-medium">Default Customer</p>
                    <p className="text-xs text-muted-foreground">Walk-in / fallback customer for this branch</p>
                </div>
                <button
                    type="button"
                    role="switch"
                    aria-checked={form.data.is_default === '1'}
                    onClick={() => form.setData('is_default', form.data.is_default === '1' ? '0' : '1')}
                    className={[
                        'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
                        form.data.is_default === '1' ? 'bg-green-600' : 'bg-muted',
                    ].join(' ')}
                >
                    <span
                        className={[
                            'pointer-events-none inline-block size-5 rounded-full bg-white shadow-sm ring-0 transition-transform duration-200',
                            form.data.is_default === '1' ? 'translate-x-5' : 'translate-x-0',
                        ].join(' ')}
                    />
                </button>
                {form.errors.is_default && <p className="mt-1 text-xs text-destructive">{form.errors.is_default}</p>}
            </div>
            <div className="flex justify-end gap-3 border-t pt-4">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="border-red-500 text-red-500 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-500 hover:text-white hover:shadow-md hover:shadow-red-500/30"
                    onClick={onCancel}
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    size="sm"
                    disabled={form.processing}
                    className="bg-emerald-600 text-white shadow-sm shadow-emerald-500/30 transition-all duration-150 hover:bg-emerald-600 hover:-translate-y-0.5 hover:shadow-md hover:shadow-emerald-500/50"
                >
                    {form.processing ? 'Saving…' : isEditing ? 'Update' : 'Create'}
                </Button>
            </div>
        </form>
    );
}

export default function CustomerIndex({ customers, filters, statuses }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [search, setSearch] = useState(filters.search ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [selectedIds, setSelectedIds] = useState([]);
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);

    const createForm = useForm(createCustomerFormDefaults());
    const editForm = useForm(editCustomerFormDefaults());

    const rows = customers.data ?? [];
    const pageIds = useMemo(() => rows.map((row) => Number(row.id)), [rows]);
    const allPageSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id));
    const hasActiveFilters = Boolean(search || dateFrom || dateTo);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    useEffect(() => {
        setSelectedIds([]);
    }, [filters.search, filters.date_from, filters.date_to, customers.current_page]);

    useDebouncedEffect(
        () => {
            router.get(
                route('party.customer.index'),
                {
                    search: search || undefined,
                    date_from: dateFrom || undefined,
                    date_to: dateTo || undefined,
                },
                { preserveState: true, replace: true },
            );
        },
        [search, dateFrom, dateTo],
        350,
        { skipFirstRun: true },
    );

    function toggleSelectAllOnPage(checked) {
        if (checked) {
            setSelectedIds((current) => [...new Set([...current, ...pageIds])]);
            return;
        }

        setSelectedIds((current) => current.filter((id) => !pageIds.includes(id)));
    }

    function toggleRowSelection(id, checked) {
        const numericId = Number(id);

        setSelectedIds((current) => (
            checked ? [...new Set([...current, numericId])] : current.filter((value) => value !== numericId)
        ));
    }

    function handleBulkExport() {
        if (selectedIds.length === 0) {
            toast.error('Select at least one customer to export.');
            return;
        }

        window.location.href = buildBulkReportUrl(selectedIds, dateFrom, dateTo);
    }

    function handleReset() {
        setSearch('');
        setDateFrom('');
        setDateTo('');
        setSelectedIds([]);
    }

    function openEdit(customer) {
        editForm.setData({
            ...editCustomerFormDefaults(),
            name: customer.name ?? '',
            phone: customer.phone,
            email: customer.email ?? '',
            address: customer.address ?? '',
            is_default: customer.is_default ? '1' : '0',
            status: customer.status,
        });
        setEditing(customer);
    }

    function handleCreate(e) {
        e.preventDefault();
        createForm.transform((data) => ({
            ...data,
            opening_balance: normalizeOptionalNumeric(data.opening_balance),
        }));
        createForm.post(route('party.customer.store'), {
            onSuccess: () => {
                setCreating(false);
                createForm.reset();
            },
        });
    }

    function handleUpdate(e) {
        e.preventDefault();
        if (!editing) return;
        editForm.patch(route('party.customer.update', editing.id), {
            onSuccess: () => {
                setEditing(null);
                editForm.reset();
            },
        });
    }

    function handleDelete() {
        if (!deleting) return;
        router.delete(route('party.customer.destroy', deleting.id), {
            onSuccess: () => setDeleting(null),
        });
    }

    const columns = [
        ...(can('party.customer.view')
            ? [{
                id: 'select',
                header: (
                    <Checkbox
                        checked={allPageSelected}
                        onCheckedChange={(checked) => toggleSelectAllOnPage(checked === true)}
                        aria-label="Select all customers on this page"
                    />
                ),
                render: (row) => (
                    <Checkbox
                        checked={selectedIds.includes(Number(row.id))}
                        onCheckedChange={(checked) => toggleRowSelection(row.id, checked === true)}
                        aria-label={`Select ${row.name ?? row.phone}`}
                    />
                ),
            }]
            : []),
        { id: 'num', header: '#', render: (_, i) => (customers.from ?? 0) + i },
        {
            id: 'customer', header: 'Customer', render: (row) => (
                <div>
                    <p className="font-medium">{row.name}</p>
                    <p className="text-xs text-muted-foreground">{row.phone}</p>
                </div>
            ),
        },
        { id: 'email', header: 'Email', render: (row) => row.email ?? '—' },
        {
            id: 'point',
            header: 'Coin Balance',
            render: (row) => (
                <span className="tabular-nums font-medium">{parseFloat(row.point ?? 0).toFixed(2)}</span>
            ),
        },
        {
            id: 'balance',
            header: 'Due Balance',
            render: (row) => (
                <span className="tabular-nums font-medium">৳{parseFloat(row.balance ?? 0).toFixed(2)}</span>
            ),
        },
        { id: 'address', header: 'Address', render: (row) => row.address ?? '—' },
        {
            id: 'status', header: 'Status', render: (row) => (
                <Badge className={row.status === 1 ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-red-600 text-white hover:bg-red-700'}>
                    {row.status === 1 ? 'Active' : 'InActive'}
                </Badge>
            ),
        },
        {
            id: 'is_default', header: 'Is Default', render: (row) => (
                <Badge className={row.is_default ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-red-600 text-white hover:bg-red-700'}>
                    {row.is_default ? 'Yes' : 'No'}
                </Badge>
            ),
        },
        {
            id: 'actions', header: 'Actions', align: 'right', render: (row) => (
                <div className="flex justify-end gap-1">
                    {can('party.customer.view') && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            asChild
                        >
                            <a
                                href={buildCustomerReportUrl(row.id, dateFrom, dateTo)}
                                aria-label={`Download report for ${row.name ?? row.phone}`}
                                title="Download Excel report"
                            >
                                <FileSpreadsheet className="size-4" />
                            </a>
                        </Button>
                    )}
                    <AdminInlineActions
                        prefix="party.customer"
                        onEdit={() => openEdit(row)}
                        onDelete={() => setDeleting(row)}
                    />
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Customers" />

            <div className="px-2 py-1">
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Users className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Customers</h1>
                            <p className="text-xs text-white/60">Manage your customers.</p>
                        </div>
                    </div>
                    <AdminCreateButton
                        permission="party.customer.create"
                        onClick={() => setCreating(true)}
                        label="Add Customer"
                        icon={Plus}
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    />
                </div>

                <div className="mb-4 flex flex-wrap items-end gap-2">
                    <div className="relative max-w-xs flex-1 min-w-[200px]">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name, phone or email…"
                            className="pl-9"
                        />
                    </div>
                    <div className="flex flex-wrap items-end gap-2">
                        <FormField label="From" name="date_from" className="space-y-1">
                            <Input
                                id="date_from"
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="w-[160px]"
                            />
                        </FormField>
                        <FormField label="To" name="date_to" className="space-y-1">
                            <Input
                                id="date_to"
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="w-[160px]"
                            />
                        </FormField>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="lg"
                        onClick={handleReset}
                        disabled={!hasActiveFilters}
                        title="Reset filters"
                        className="shrink-0"
                    >
                        <RotateCcw className="size-4" />
                        Reset
                    </Button>
                    {can('party.customer.view') && (
                        <Button
                            type="button"
                            variant="outline"
                            size="lg"
                            disabled={selectedIds.length === 0}
                            onClick={handleBulkExport}
                            className="shrink-0"
                        >
                            <FileSpreadsheet className="size-4" />
                            Export Excel{selectedIds.length > 0 ? ` (${selectedIds.length})` : ''}
                        </Button>
                    )}
                </div>

                {selectedIds.length > 0 && (
                    <p className="mb-3 text-sm text-muted-foreground">
                        {selectedIds.length} customer{selectedIds.length !== 1 ? 's' : ''} selected
                    </p>
                )}

                <DataTable columns={columns} rows={rows} rowKey="id" emptyMessage="No customers found." />

                {customers.links?.length > 3 && (
                    <div className="mt-4 flex flex-wrap gap-1">
                        {customers.links.map((link, i) => (
                            <a
                                key={i}
                                href={link.url ?? '#'}
                                className={[
                                    'border px-3 py-1 text-sm transition-colors',
                                    link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-accent',
                                    !link.url ? 'pointer-events-none opacity-50' : '',
                                ].join(' ')}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>

            <Can permission="party.customer.create">
            {/* Create Dialog */}
            <Dialog open={creating} onOpenChange={(open) => { if (!open) { setCreating(false); createForm.reset(); } }}>
                <DialogContent className="p-0 sm:max-w-lg">
                    <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                            <Plus className="size-3.5 text-white" />
                        </div>
                        <h2 className="text-sm font-semibold text-white">Add Customer</h2>
                    </div>
                    <CustomerForm
                        form={createForm}
                        onSubmit={handleCreate}
                        onCancel={() => setCreating(false)}
                        isEditing={false}
                        statuses={statuses}
                    />
                </DialogContent>
            </Dialog>
            </Can>

            <Can permission="party.customer.update">
            {/* Edit Dialog */}
            <Dialog open={!!editing} onOpenChange={(open) => !open && setEditing(null)}>
                <DialogContent className="p-0 sm:max-w-lg">
                    <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                            <Pencil className="size-3.5 text-white" />
                        </div>
                        <h2 className="text-sm font-semibold text-white">Edit Customer</h2>
                    </div>
                    <CustomerForm
                        form={editForm}
                        onSubmit={handleUpdate}
                        onCancel={() => setEditing(null)}
                        isEditing={true}
                        statuses={statuses}
                    />
                </DialogContent>
            </Dialog>
            </Can>

            {can('party.customer.delete') && (
            <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                <DialogContent className="p-0">
                    <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                            <Trash2 className="size-3.5 text-white" />
                        </div>
                        <h2 className="text-sm font-semibold text-white">Delete Customer</h2>
                    </div>
                    <div className="px-5 pb-5 pt-4">
                        <p className="text-sm text-muted-foreground">
                            Are you sure you want to delete <strong>{deleting?.name}</strong>?
                        </p>
                        <div className="mt-4 flex justify-end gap-3 border-t pt-4">
                            <DialogClose asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="border-red-500 text-red-500 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-500 hover:text-white hover:shadow-md hover:shadow-red-500/30"
                                >
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                size="sm"
                                className="bg-red-600 text-white shadow-sm shadow-red-500/30 transition-all duration-150 hover:bg-red-700 hover:-translate-y-0.5 hover:shadow-md hover:shadow-red-500/50"
                                onClick={handleDelete}
                            >
                                Delete
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
            )}
        </>
    );
}
