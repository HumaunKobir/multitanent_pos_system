import CategoryController from '@/actions/App/Http/Controllers/Setting/CategoryController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CategoryForm({ category }) {
    const isEditing = !!category;

    const form = useForm({
        name: category?.name ?? '',
        image: null,
        status: category ? String(category.status) : '1',
    });

    function handleSubmit(e) {
        e.preventDefault();
        if (isEditing) {
            form.post(CategoryController.update.url(category.id), {
                _method: 'patch',
            });
        } else {
            form.post(CategoryController.store.url());
        }
    }

    return (
        <>
            <Head title={isEditing ? 'Edit Category' : 'Create Category'} />

            <div className="p-6">
                <div className="mb-5">
                    <h1 className="text-xl font-semibold">{isEditing ? 'Edit Category' : 'Create Category'}</h1>
                </div>

                <form onSubmit={handleSubmit} className="max-w-lg space-y-4" encType="multipart/form-data">
                    <div>
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Category name"
                            className="mt-1"
                            aria-invalid={!!form.errors.name}
                        />
                        {form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}
                    </div>

                    <div>
                        <Label htmlFor="image">Image</Label>
                        {isEditing && category.image && (
                            <div className="mt-1 mb-2">
                                <img src={`/storage/${category.image}`} alt="Current" className="h-16 w-16 rounded object-cover" />
                                <p className="mt-1 text-xs text-muted-foreground">Upload a new image to replace the current one.</p>
                            </div>
                        )}
                        <Input
                            id="image"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={(e) => form.setData('image', e.target.files[0] ?? null)}
                            className="mt-1"
                            aria-invalid={!!form.errors.image}
                        />
                        {form.errors.image && <p className="mt-1 text-xs text-destructive">{form.errors.image}</p>}
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
                            <Link href={CategoryController.index.url()}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
