import { RequiredMark } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/react';
import { LayoutDashboard, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo } from 'react';

const emptyImageRow = () => ({
    image_name: '',
    image: null,
    button_text: '',
    link: '',
    description: '',
    existing_image: null,
});

export default function ProductSectionFormDialog({
    open,
    onOpenChange,
    item,
    routes,
    layoutTypeOptions,
    blockTypeOptions,
    productOptions,
}) {
    const isEditing = !!item?.id;

    const initial = useMemo(
        () => ({
            name: item?.name ?? '',
            description: item?.description ?? '',
            button_text: item?.button_text ?? '',
            layout_type: String(item?.layout_type ?? layoutTypeOptions[0]?.value ?? '1'),
            block_type: String(item?.block_type ?? blockTypeOptions[0]?.value ?? '2'),
            block_per_line: String(item?.block_per_line ?? '4'),
            status: String(item?.status ?? '1'),
            items: item?.items ?? [],
            image_rows:
                item?.images?.length > 0
                    ? item.images.map((row) => ({
                          image_name: row.image_name ?? '',
                          image: null,
                          button_text: row.button_text ?? '',
                          link: row.link ?? '',
                          description: row.description ?? '',
                          existing_image: row.image ?? null,
                      }))
                    : [emptyImageRow()],
        }),
        [item, layoutTypeOptions, blockTypeOptions],
    );

    const form = useForm(initial);

    useEffect(() => {
        form.setData(initial);
        form.clearErrors();
    }, [item, open]);

    const isImageBlock = form.data.block_type === '1';
    const isItemBlock = form.data.block_type === '2';
    const isSliderLayout = form.data.layout_type === '2';
    const showItemExtras = isItemBlock && !isSliderLayout;

    function buildPayload() {
        const payload = {
            block_type: form.data.block_type,
            block_per_line: form.data.block_per_line,
            status: form.data.status,
            name: form.data.name,
        };

        if (!isEditing) {
            payload.layout_type = form.data.layout_type;
        }

        if (isItemBlock) {
            payload.items = form.data.items;

            if (showItemExtras) {
                payload.description = form.data.description;
                payload.button_text = form.data.button_text;
            }
        }

        if (isImageBlock) {
            payload.image_name = [];
            payload.button_text = [];
            payload.link = [];
            payload.description = [];
            payload.images = [];

            form.data.image_rows.forEach((row, index) => {
                payload.image_name[index] = row.image_name;
                payload.button_text[index] = row.button_text;
                payload.link[index] = row.link;
                payload.description[index] = row.description;

                if (row.image) {
                    payload.images[index] = row.image;
                }
            });
        }

        return payload;
    }

    function handleSubmit(e) {
        e.preventDefault();

        const options = { forceFormData: true, onSuccess: () => onOpenChange(false) };

        if (isEditing) {
            form.transform(() => buildPayload());
            form.post(routes.update(item.id), { ...options, _method: 'patch' });
        } else {
            form.transform(() => buildPayload());
            form.post(routes.store, options);
        }
    }

    function updateImageRow(index, field, value) {
        const rows = [...form.data.image_rows];
        rows[index] = { ...rows[index], [field]: value };
        form.setData('image_rows', rows);
    }

    function addImageRow() {
        form.setData('image_rows', [...form.data.image_rows, emptyImageRow()]);
    }

    function removeImageRow(index) {
        form.setData(
            'image_rows',
            form.data.image_rows.filter((_, i) => i !== index),
        );
    }

    function toggleProduct(productId) {
        const id = Number(productId);
        const items = form.data.items.includes(id)
            ? form.data.items.filter((value) => value !== id)
            : [...form.data.items, id];
        form.setData('items', items);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl p-0">
                <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                    <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                        <LayoutDashboard className="size-3.5 text-white" />
                    </div>
                    <h2 className="text-sm font-semibold text-white">{isEditing ? 'Edit Product Section' : 'Create Product Section'}</h2>
                </div>

                <form onSubmit={handleSubmit} className="space-y-1.5 px-3 py-2">
                    <div>
                        <Label htmlFor="name">
                            Section Name
                            <RequiredMark />
                        </Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            className="mt-1"
                        />
                        {form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}
                    </div>

                    <div className={`grid gap-4 ${isEditing ? '' : 'sm:grid-cols-2'}`}>
                        {!isEditing && (
                            <div>
                                <Label>
                                    Layout Type
                                    <RequiredMark />
                                </Label>
                                <Select value={form.data.layout_type} onValueChange={(value) => form.setData('layout_type', value)}>
                                    <SelectTrigger className="mt-1 w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {layoutTypeOptions.map((option) => (
                                            <SelectItem key={option.value} value={String(option.value)}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        <div>
                            <Label>
                                Block Type
                                <RequiredMark />
                            </Label>
                            <Select value={form.data.block_type} onValueChange={(value) => form.setData('block_type', value)}>
                                <SelectTrigger className="mt-1 w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {blockTypeOptions.map((option) => (
                                        <SelectItem key={option.value} value={String(option.value)}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label htmlFor="block_per_line">
                                Blocks Per Line
                                <RequiredMark />
                            </Label>
                            <Input
                                id="block_per_line"
                                type="number"
                                min={1}
                                max={6}
                                value={form.data.block_per_line}
                                onChange={(e) => form.setData('block_per_line', e.target.value)}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <Label>
                                Status
                                <RequiredMark />
                            </Label>
                            <Select value={form.data.status} onValueChange={(value) => form.setData('status', value)}>
                                <SelectTrigger className="mt-1 w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Active</SelectItem>
                                    <SelectItem value="0">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {isItemBlock && (
                        <>
                            {showItemExtras && (
                                <>
                                    <div>
                                        <Label htmlFor="description">Description</Label>
                                        <textarea
                                            id="description"
                                            value={form.data.description}
                                            onChange={(e) => form.setData('description', e.target.value)}
                                            className="mt-1 flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="section_button_text">Button Text</Label>
                                        <Input
                                            id="section_button_text"
                                            value={form.data.button_text}
                                            onChange={(e) => form.setData('button_text', e.target.value)}
                                            className="mt-1"
                                        />
                                    </div>
                                </>
                            )}
                            <div>
                                <Label>
                                    Products
                                    <RequiredMark />
                                </Label>
                                <div className="mt-2 max-h-48 space-y-2 overflow-y-auto rounded border p-3">
                                    {productOptions.map((option) => (
                                        <label key={option.value} className="flex cursor-pointer items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={form.data.items.includes(option.value)}
                                                onChange={() => toggleProduct(option.value)}
                                            />
                                            {option.label}
                                        </label>
                                    ))}
                                </div>
                                {form.errors.items && <p className="mt-1 text-xs text-destructive">{form.errors.items}</p>}
                            </div>
                        </>
                    )}

                    {isImageBlock && (
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <Label>Image Blocks</Label>
                                <Button type="button" size="sm" variant="outline" onClick={addImageRow}>
                                    <Plus className="mr-1 size-3.5" />
                                    Add Image
                                </Button>
                            </div>

                            {form.data.image_rows.map((row, index) => (
                                <div key={index} className="space-y-1.5 rounded-none border p-3">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium">Image #{index + 1}</span>
                                        {form.data.image_rows.length > 1 && (
                                            <Button type="button" size="sm" variant="ghost" onClick={() => removeImageRow(index)}>
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                    <Input
                                        placeholder="Image title"
                                        value={row.image_name}
                                        onChange={(e) => updateImageRow(index, 'image_name', e.target.value)}
                                    />
                                    {row.existing_image && (
                                        <img src={`/storage/${row.existing_image}`} alt="" className="h-20 w-32 rounded object-cover" />
                                    )}
                                    <Input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp,image/gif"
                                        onChange={(e) => updateImageRow(index, 'image', e.target.files[0] ?? null)}
                                    />
                                    <Input
                                        placeholder="Button text"
                                        value={row.button_text}
                                        onChange={(e) => updateImageRow(index, 'button_text', e.target.value)}
                                    />
                                    <Input
                                        placeholder="Link"
                                        value={row.link}
                                        onChange={(e) => updateImageRow(index, 'link', e.target.value)}
                                    />
                                    <textarea
                                        placeholder="Description"
                                        value={row.description}
                                        onChange={(e) => updateImageRow(index, 'description', e.target.value)}
                                        className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                    />
                                </div>
                            ))}
                        </div>
                    )}

                    <div className="flex justify-end gap-3 border-t pt-4">
                        <Button type="button" variant="outline" size="sm" className="border-red-500 text-red-500 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-500 hover:text-white hover:shadow-md hover:shadow-red-500/30" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing} size="sm" className="bg-emerald-600 text-white shadow-sm shadow-emerald-500/30 transition-all duration-150 hover:bg-emerald-600 hover:-translate-y-0.5 hover:shadow-md hover:shadow-emerald-500/50">
                            {isEditing ? 'Update' : 'Create'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
