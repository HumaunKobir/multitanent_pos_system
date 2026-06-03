import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { route } from '@/lib/route';
import { useForm } from '@inertiajs/react';
import { Loader2, Wallet } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const DEFAULT_TYPE = '1'; // Asset

export default function AccountFormDialog({ open, onOpenChange, item, accountTypes, parentAccounts }) {
    const isEditing = !!item?.id;
    const [codeLoading, setCodeLoading] = useState(false);
    const abortRef = useRef(null);

    const form = useForm({
        parent_id: '',
        type: DEFAULT_TYPE,
        name: '',
        code: '',
        account_number: '',
        description: '',
        status: '1',
        opening_balance: '',
    });

    useEffect(() => {
        form.setData({
            parent_id: item?.parent_id ? String(item.parent_id) : '',
            type: item?.type ? String(item.type) : DEFAULT_TYPE,
            name: item?.name ?? '',
            code: item?.code ?? '',
            account_number: item?.account_number ?? '',
            description: item?.description ?? '',
            status: item?.status !== undefined ? String(item.status) : '1',
            opening_balance: '',
        });
        form.clearErrors();
    }, [item, open]);

    // Fetch next code when type or parent_id changes (create mode only)
    useEffect(() => {
        if (isEditing || !open) return;

        const type = form.data.type;
        if (!type) return;

        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        setCodeLoading(true);

        const params = new URLSearchParams({ type });
        if (form.data.parent_id) params.set('parent_id', form.data.parent_id);

        fetch(`${route('accounts.next-code')}?${params}`, { signal: controller.signal })
            .then((r) => r.json())
            .then((data) => {
                form.setData('code', data.code);
                setCodeLoading(false);
            })
            .catch(() => setCodeLoading(false));
    }, [form.data.type, form.data.parent_id, open]);

    function handleSubmit(e) {
        e.preventDefault();

        const options = { onSuccess: () => onOpenChange(false) };

        if (isEditing) {
            form.patch(route('accounts.update', { chartOfAccount: item.id }), options);
        } else {
            form.post(route('accounts.store'), options);
        }
    }

    const filteredParents = parentAccounts.filter(
        (p) => p.id !== item?.id && (!form.data.type || String(p.type) === form.data.type),
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="p-0 sm:max-w-lg">
                <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                    <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                        <Wallet className="size-3.5 text-white" />
                    </div>
                    <h2 className="text-sm font-semibold text-white">{isEditing ? 'Edit Account' : 'Create Account'}</h2>
                </div>

                <form onSubmit={handleSubmit} className="space-y-3 px-5 py-4">
                    {/* Type */}
                    <div>
                        <Label htmlFor="type">
                            Account Type <span className="text-destructive">*</span>
                        </Label>
                        <Select
                            value={form.data.type}
                            onValueChange={(v) => form.setData((prev) => ({ ...prev, type: v, parent_id: '' }))}
                        >
                            <SelectTrigger id="type" className="mt-1 w-full" aria-invalid={!!form.errors.type}>
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                {accountTypes.map((t) => (
                                    <SelectItem key={t.id} value={String(t.id)}>
                                        {t.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.type && <p className="mt-1 text-xs text-destructive">{form.errors.type}</p>}
                    </div>

                    {/* Parent Account */}
                    <div>
                        <Label htmlFor="parent_id">
                            Parent Account <span className="text-xs text-muted-foreground">(optional)</span>
                        </Label>
                        <Select
                            value={form.data.parent_id || 'none'}
                            onValueChange={(v) => form.setData('parent_id', v === 'none' ? '' : v)}
                        >
                            <SelectTrigger id="parent_id" className="mt-1 w-full" aria-invalid={!!form.errors.parent_id}>
                                <SelectValue placeholder="None (top-level account)" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">None (top-level)</SelectItem>
                                {filteredParents.map((p) => (
                                    <SelectItem key={p.id} value={String(p.id)}>
                                        [{p.code}] {p.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.parent_id && <p className="mt-1 text-xs text-destructive">{form.errors.parent_id}</p>}
                    </div>

                    {/* Account Name */}
                    <div>
                        <Label htmlFor="name">
                            Account Name <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="e.g. Cash in Hand"
                            className="mt-1"
                            aria-invalid={!!form.errors.name}
                        />
                        {form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}
                    </div>

                    {/* Code + Account Number - side by side */}
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label htmlFor="code">Code</Label>
                            <div className="relative mt-1">
                                <Input
                                    id="code"
                                    value={form.data.code}
                                    onChange={(e) => form.setData('code', e.target.value)}
                                    placeholder="Auto-generated"
                                    className="pr-8"
                                    aria-invalid={!!form.errors.code}
                                />
                                {codeLoading && (
                                    <Loader2 className="absolute top-1/2 right-2.5 size-3.5 -translate-y-1/2 animate-spin text-muted-foreground" />
                                )}
                            </div>
                            {form.errors.code && <p className="mt-1 text-xs text-destructive">{form.errors.code}</p>}
                        </div>

                        <div>
                            <Label htmlFor="account_number">
                                Account Number <span className="text-xs text-muted-foreground">(optional)</span>
                            </Label>
                            <Input
                                id="account_number"
                                value={form.data.account_number}
                                onChange={(e) => form.setData('account_number', e.target.value)}
                                placeholder="e.g. 1001"
                                className="mt-1"
                                aria-invalid={!!form.errors.account_number}
                            />
                            {form.errors.account_number && (
                                <p className="mt-1 text-xs text-destructive">{form.errors.account_number}</p>
                            )}
                        </div>
                    </div>

                    {/* Description */}
                    <div>
                        <Label htmlFor="description">Description</Label>
                        <Input
                            id="description"
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            placeholder="Optional description"
                            className="mt-1"
                            aria-invalid={!!form.errors.description}
                        />
                        {form.errors.description && <p className="mt-1 text-xs text-destructive">{form.errors.description}</p>}
                    </div>

                    {/* Opening Balance - create only */}
                    {!isEditing && (
                        <div>
                            <Label htmlFor="opening_balance">Opening Balance</Label>
                            <Input
                                id="opening_balance"
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.opening_balance}
                                onChange={(e) => form.setData('opening_balance', e.target.value)}
                                placeholder="0.00"
                                className="mt-1"
                                aria-invalid={!!form.errors.opening_balance}
                            />
                            {form.errors.opening_balance && (
                                <p className="mt-1 text-xs text-destructive">{form.errors.opening_balance}</p>
                            )}
                        </div>
                    )}

                    {/* Status */}
                    <div className="flex items-center gap-3">
                        <Label>Status</Label>
                        <button
                            type="button"
                            onClick={() => form.setData('status', form.data.status === '1' ? '2' : '1')}
                            className="relative"
                        >
                            <div className={`h-5 w-9 rounded-full transition-colors ${form.data.status === '1' ? 'bg-green-600' : 'bg-muted'}`} />
                            <div className={`absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform ${form.data.status === '1' ? 'translate-x-4' : ''}`} />
                        </button>
                        {form.errors.status && <p className="mt-1 text-xs text-destructive">{form.errors.status}</p>}
                    </div>

                    <div className="flex justify-end gap-3 border-t pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="border-red-500 text-red-500 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-500 hover:text-white hover:shadow-md hover:shadow-red-500/30"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            size="sm"
                            className="bg-emerald-600 text-white shadow-sm shadow-emerald-500/30 transition-all duration-150 hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-md hover:shadow-emerald-500/50"
                        >
                            {isEditing ? 'Update' : 'Create'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
