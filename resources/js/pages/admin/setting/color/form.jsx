import ColorController from '@/actions/App/Http/Controllers/Setting/ColorController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ColorForm({ color }) {
    const isEditing = !!color;

    const form = useForm({
        name: color?.name ?? '',
        code: color?.code ?? '',
        status: color ? String(color.status) : '1',
    });

    function handleSubmit(e) {
        e.preventDefault();
        if (isEditing) {
            form.patch(ColorController.update.url(color.id));
        } else {
            form.post(ColorController.store.url());
        }
    }

    return (
        <>
            <Head title={isEditing ? 'Edit Color' : 'Create Color'} />

            <div className="p-6">
                <div className="mb-5">
                    <h1 className="text-xl font-semibold">{isEditing ? 'Edit Color' : 'Create Color'}</h1>
                </div>

                <form onSubmit={handleSubmit} className="max-w-lg space-y-4">
                    <div>
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Color name"
                            className="mt-1"
                            aria-invalid={!!form.errors.name}
                        />
                        {form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}
                    </div>

                    <div>
                        <Label htmlFor="code">Color Code (optional)</Label>
                        <div className="mt-1 flex gap-2">
                            <input
                                type="color"
                                value={form.data.code || '#000000'}
                                onChange={(e) => form.setData('code', e.target.value)}
                                className="h-9 w-12 cursor-pointer rounded border border-input bg-transparent p-1"
                            />
                            <Input
                                id="code"
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value)}
                                placeholder="#000000"
                                className="flex-1"
                                aria-invalid={!!form.errors.code}
                            />
                        </div>
                        {form.errors.code && <p className="mt-1 text-xs text-destructive">{form.errors.code}</p>}
                    </div>

                    <div>
                        <Label htmlFor="status">Status</Label>
                        <Select value={form.data.status} onValueChange={(v) => form.setData('status', v)}>
                            <SelectTrigger id="status" className="mt-1 w-full" aria-invalid={!!form.errors.status}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">Active</SelectItem>
                                <SelectItem value="0">InActive</SelectItem>
                            </SelectContent>
                        </Select>
                        {form.errors.status && <p className="mt-1 text-xs text-destructive">{form.errors.status}</p>}
                    </div>

                    <div className="flex gap-3 pt-2">
                        <Button type="submit" disabled={form.processing}>
                            {isEditing ? 'Update' : 'Create'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={ColorController.index.url()}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
