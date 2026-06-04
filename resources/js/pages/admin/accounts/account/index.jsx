import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useAppToast } from '@/contexts/app-toast-context';
import { AdminCreateButton } from '@/components/admin/row-actions';
import { Can } from '@/components/can';
import { useDebouncedEffect } from '@/hooks/use-debounced-effect';
import { useCan } from '@/hooks/use-can';
import { route } from '@/lib/route';
import { Head, router, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Pencil, Plus, Search, Trash2, Wallet } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import AccountFormDialog from './form-dialog';

const TYPE_COLORS = {
    Asset: 'bg-blue-100 text-blue-700 hover:bg-blue-100',
    Liability: 'bg-orange-100 text-orange-700 hover:bg-orange-100',
    Equity: 'bg-purple-100 text-purple-700 hover:bg-purple-100',
    Income: 'bg-green-100 text-green-700 hover:bg-green-100',
    Expenses: 'bg-red-100 text-red-700 hover:bg-red-100',
};

function buildTree(accounts) {
    const map = {};
    accounts.forEach((a) => (map[a.id] = { ...a, children: [] }));
    const roots = [];
    accounts.forEach((a) => {
        if (a.parent_id && map[a.parent_id]) {
            map[a.parent_id].children.push(map[a.id]);
        } else {
            roots.push(map[a.id]);
        }
    });
    return roots;
}

function AccountRow({ node, depth, accountTypes, onEdit, onDelete, expandedIds, toggleExpand }) {
    const { can } = useCan();
    const hasChildren = node.children.length > 0;
    const isExpanded = expandedIds.has(node.id);
    const typeLabel = accountTypes.find((t) => t.id === node.type)?.name ?? '';
    const indent = depth * 28;

    return (
        <>
            <div
                className="group flex items-center gap-3 border-b border-border/50 py-2.5 pr-3 transition-colors hover:bg-muted/30"
                style={{ paddingLeft: `${12 + indent}px` }}
            >
                {/* Expand toggle */}
                <div className="flex size-5 shrink-0 items-center justify-center">
                    {hasChildren ? (
                        <button
                            type="button"
                            onClick={() => toggleExpand(node.id)}
                            className="flex size-5 items-center justify-center text-muted-foreground hover:text-foreground"
                        >
                            {isExpanded ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
                        </button>
                    ) : (
                        <span className="size-4" />
                    )}
                </div>

                {/* Content */}
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-mono text-sm font-semibold text-muted-foreground">{node.code}</span>
                        <span className="text-sm font-semibold text-foreground">{node.name}</span>
                        {node.is_system && (
                            <span className="rounded border border-border bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                                System
                            </span>
                        )}
                        {depth === 0 && typeLabel && (
                            <Badge className={`text-[10px] ${TYPE_COLORS[typeLabel] ?? 'bg-muted text-muted-foreground'}`}>
                                {typeLabel}
                            </Badge>
                        )}
                    </div>
                    {node.description && (
                        <p className="mt-0.5 text-xs text-muted-foreground">{node.description}</p>
                    )}
                </div>

                {/* Account Number */}
                <span className="w-24 shrink-0 font-mono text-xs text-muted-foreground">
                    {node.account_number ?? '—'}
                </span>

                {/* Balance */}
                <span className="w-28 shrink-0 text-right font-mono text-xs text-foreground">
                    {parseFloat(node.current_balance).toLocaleString('en-BD', { minimumFractionDigits: 2 })}
                </span>

                {/* Actions */}
                <div className="flex shrink-0 gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                    {can('accounts.update') && (
                        <Button size="sm" variant="outline" className="h-7 w-7 p-0" onClick={() => onEdit(node)}>
                            <Pencil className="size-3" />
                        </Button>
                    )}
                    {!node.is_system && can('accounts.delete') && (
                        <Button size="sm" variant="destructive" className="h-7 w-7 p-0" onClick={() => onDelete(node)}>
                            <Trash2 className="size-3" />
                        </Button>
                    )}
                </div>
            </div>

            {/* Render children */}
            {hasChildren && isExpanded &&
                node.children.map((child) => (
                    <AccountRow
                        key={child.id}
                        node={child}
                        depth={depth + 1}
                        accountTypes={accountTypes}
                        onEdit={onEdit}
                        onDelete={onDelete}
                        expandedIds={expandedIds}
                        toggleExpand={toggleExpand}
                    />
                ))}
        </>
    );
}

export default function AccountIndex({ accounts, parentAccounts, accountTypes, filters }) {
    const { flash } = usePage().props;
    const toast = useAppToast();
    const { can } = useCan();
    const [search, setSearch] = useState(filters.search ?? '');
    const [typeFilter, setTypeFilter] = useState(filters.type ?? '');
    const [deleting, setDeleting] = useState(null);
    const [editing, setEditing] = useState(null);
    const [formOpen, setFormOpen] = useState(false);
    const [expandedIds, setExpandedIds] = useState(() => new Set());

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);


    useDebouncedEffect(
        () => {
            router.get(
                route('accounts.index', { query: { search: search || undefined, type: typeFilter || undefined } }),
                {},
                { preserveState: true, replace: true },
            );
        },
        [search, typeFilter],
        350,
        { skipFirstRun: true },
    );

    function toggleExpand(id) {
        setExpandedIds((prev) => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    }

    function handleDelete() {
        if (!deleting) return;
        router.delete(route('accounts.destroy', { chartOfAccount: deleting.id }), {
            onSuccess: () => setDeleting(null),
        });
    }

    function openCreate() {
        setEditing(null);
        setFormOpen(true);
    }

    function openEdit(row) {
        setEditing(row);
        setFormOpen(true);
    }

    const tree = useMemo(() => buildTree(accounts), [accounts]);

    return (
        <>
            <Head title="Accounts" />

            <div className="px-2 py-1">
                {/* Header */}
                <div className="mb-3 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Wallet className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Accounts</h1>
                            <p className="text-xs text-white/60">Manage chart of accounts</p>
                        </div>
                    </div>
                    <AdminCreateButton
                        permission="accounts.create"
                        onClick={openCreate}
                        label="Add New"
                        icon={Plus}
                        className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                    />
                </div>

                {/* Filters */}
                <div className="mb-3 flex gap-2">
                    <div className="relative max-w-xs flex-1">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name or code..."
                            className="pl-9"
                        />
                    </div>
                    <Select value={typeFilter} onValueChange={(v) => setTypeFilter(v === 'all' ? '' : v)}>
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="All Types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Types</SelectItem>
                            {accountTypes.map((t) => (
                                <SelectItem key={t.id} value={String(t.id)}>
                                    {t.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Tree */}
                <div className="rounded-lg border border-border bg-card">
                    {/* Table header */}
                    <div className="flex items-center gap-3 border-b-2 border-blue-900/80 bg-blue-950 px-3 py-3.5 text-xs font-semibold uppercase tracking-wider text-blue-50">
                        <div className="size-5 shrink-0" />
                        <span className="flex-1">Account</span>
                        <span className="w-24 shrink-0">Acc. Number</span>
                        <span className="w-28 shrink-0 text-right">Balance</span>
                        <span className="w-16 shrink-0" />
                    </div>

                    {tree.length === 0 ? (
                        <div className="flex items-center justify-center py-16 text-sm text-muted-foreground">
                            No accounts found.
                        </div>
                    ) : (
                        tree.map((node) => (
                            <AccountRow
                                key={node.id}
                                node={node}
                                depth={0}
                                accountTypes={accountTypes}
                                onEdit={openEdit}
                                onDelete={setDeleting}
                                expandedIds={expandedIds}
                                toggleExpand={toggleExpand}
                            />
                        ))
                    )}
                </div>
            </div>

            {can('accounts.delete') && (
            <Dialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                <DialogContent className="p-0">
                    <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                        <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                            <Trash2 className="size-3.5 text-white" />
                        </div>
                        <h2 className="text-sm font-semibold text-white">Delete Account</h2>
                    </div>
                    <div className="px-5 pb-5 pt-4">
                        <p className="text-sm text-muted-foreground">
                            Are you sure you want to delete <strong>{deleting?.name}</strong>? This action cannot be undone.
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
            )}

            <Can permission={['accounts.create', 'accounts.update']}>
                <AccountFormDialog
                    open={formOpen}
                    onOpenChange={setFormOpen}
                    item={editing}
                    accountTypes={accountTypes}
                    parentAccounts={parentAccounts}
                />
            </Can>
        </>
    );
}
