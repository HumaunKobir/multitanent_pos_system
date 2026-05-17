import WarrantyController from '@/actions/App/Http/Controllers/Setting/WarrantyController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, Link, useForm } from '@inertiajs/react';

export default function WarrantyForm({ warranty }) {
    const isEditing = !!warranty;

    const form = useForm({
        name: warranty?.name ?? '',
        duration: warranty?.duration ?? '',
        status: warranty ? String(warranty.status) : '1',
    });

    function handleSubmit(e) {
        e.preventDefault();
        if (isEditing) {
            form.patch(WarrantyController.update.url(warranty.id));
        } else {
            form.post(WarrantyController.store.url());
        }
    }

    return (
        <>
            <Head title={isEditing ? 'Edit Warranty' : 'Create Warranty'} />

            <div className="p-6">
                <div className="mb-5">
                    <h1 className="text-xl font-semibold">{isEditing ? 'Edit Warranty' : 'Create Warranty'}</h1>
                </div>

                <form onSubmit={handleSubmit} className="max-w-lg space-y-4">
                    <div>
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Warranty name"
                            className="mt-1"
                            aria-invalid={!!form.errors.name}
                        />
                        {form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}
                    </div>

                    <div>
                        <Label htmlFor="duration">Duration (optional)</Label>
                        <Input
                            id="duration"
                            value={form.data.duration}
                            onChange={(e) => form.setData('duration', e.target.value)}
                            placeholder="e.g. 1 Year, 6 Months"
                            className="mt-1"
                            aria-invalid={!!form.errors.duration}
                        />
                        {form.errors.duration && <p className="mt-1 text-xs text-destructive">{form.errors.duration}</p>}
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
                            <Link href={WarrantyController.index.url()}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
