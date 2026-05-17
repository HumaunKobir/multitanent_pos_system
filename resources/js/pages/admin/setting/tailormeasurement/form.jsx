import TailorMeasurementController from '@/actions/App/Http/Controllers/Setting/TailorMeasurementController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, Link, useForm } from '@inertiajs/react';

export default function TailorMeasurementForm({ measurement }) {
    const isEditing = !!measurement;

    const form = useForm({
        name: measurement?.name ?? '',
        status: measurement ? String(measurement.status) : '1',
    });

    function handleSubmit(e) {
        e.preventDefault();
        if (isEditing) {
            form.patch(TailorMeasurementController.update.url(measurement.id));
        } else {
            form.post(TailorMeasurementController.store.url());
        }
    }

    return (
        <>
            <Head title={isEditing ? 'Edit Tailor Measurement' : 'Create Tailor Measurement'} />

            <div className="p-6">
                <div className="mb-5">
                    <h1 className="text-xl font-semibold">
                        {isEditing ? 'Edit Tailor Measurement' : 'Create Tailor Measurement'}
                    </h1>
                </div>

                <form onSubmit={handleSubmit} className="max-w-lg space-y-4">
                    <div>
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Measurement name"
                            className="mt-1"
                            aria-invalid={!!form.errors.name}
                        />
                        {form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}
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
                            <Link href={TailorMeasurementController.index.url()}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
