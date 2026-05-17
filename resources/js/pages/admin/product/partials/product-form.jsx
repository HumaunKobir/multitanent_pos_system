import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function SectionCard({ title, children }) {
    return (
        <div className="rounded-lg border bg-card p-6">
            <h2 className="mb-4 text-base font-semibold">{title}</h2>
            {children}
        </div>
    );
}

function FieldError({ error }) {
    if (!error) return null;
    return <p className="mt-1 text-xs text-destructive">{error}</p>;
}

function MultiCheckbox({ options, selected, onChange, colorMode = false }) {
    const selectedNums = (selected || []).map(Number);

    function toggle(id) {
        const num = Number(id);
        if (selectedNums.includes(num)) {
            onChange(selectedNums.filter((v) => v !== num));
        } else {
            onChange([...selectedNums, num]);
        }
    }

    return (
        <div className="flex flex-wrap gap-2">
            {options.map((opt) => {
                const id = opt.value ?? opt.id;
                const label = opt.label ?? opt.name;
                const isSelected = selectedNums.includes(Number(id));
                return (
                    <button
                        key={id}
                        type="button"
                        onClick={() => toggle(id)}
                        className={[
                            'flex items-center gap-1.5 rounded border px-3 py-1.5 text-sm transition-colors',
                            isSelected ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-accent',
                        ].join(' ')}
                    >
                        {colorMode && opt.code && (
                            <span
                                className="inline-block h-3 w-3 rounded-full border border-white/30"
                                style={{ backgroundColor: opt.code }}
                            />
                        )}
                        {label}
                    </button>
                );
            })}
            {options.length === 0 && <p className="text-sm text-muted-foreground">No options available.</p>}
        </div>
    );
}

export default function ProductForm({ form, categories, brands, units, warranties, colors, sizes, tailors, branches, isEditing = false }) {
    const { auth } = usePage().props;
    const isAdmin = !auth.user?.branch_id;

    const categoryOptions = Object.entries(categories || {}).map(([value, label]) => ({ value, label }));
    const brandOptions = Object.entries(brands || {}).map(([value, label]) => ({ value, label }));
    const unitOptions = Object.entries(units || {}).map(([value, label]) => ({ value, label }));
    const warrantyOptions = Object.entries(warranties || {}).map(([value, label]) => ({ value, label }));
    const sizeOptions = Object.entries(sizes || {}).map(([value, label]) => ({ value, label }));
    const tailorOptions = Object.entries(tailors || {}).map(([value, label]) => ({ value, label }));
    const branchOptions = Object.entries(branches || {}).map(([value, label]) => ({ value, label }));

    const [tagInput, setTagInput] = useState((form.data.tags || []).join(', '));
    const [imagePreview, setImagePreview] = useState(null);

    useEffect(() => {
        setTagInput((form.data.tags || []).join(', '));
    }, []);

    function handleTagInput(e) {
        setTagInput(e.target.value);
        const tags = e.target.value
            .split(',')
            .map((t) => t.trim())
            .filter(Boolean);
        form.setData('tags', tags);
    }

    function handleImageChange(e) {
        const file = e.target.files[0] ?? null;
        form.setData('image', file);
        if (file) {
            setImagePreview(URL.createObjectURL(file));
        }
    }

    return (
        <div className="space-y-6">
            {/* Section 1: Basic Info */}
            <SectionCard title="Basic Information">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {isAdmin && (
                        <div>
                            <Label>Branch</Label>
                            <Select
                                value={form.data.branch_id ? String(form.data.branch_id) : '__none'}
                                onValueChange={(v) => form.setData('branch_id', v === '__none' ? null : v)}
                            >
                                <SelectTrigger className="mt-1" aria-invalid={!!form.errors.branch_id}>
                                    <SelectValue placeholder="All branches" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__none">All branches</SelectItem>
                                    {branchOptions.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FieldError error={form.errors.branch_id} />
                        </div>
                    )}

                    <div>
                        <Label>
                            Category <span className="text-destructive">*</span>
                        </Label>
                        <Select
                            value={String(form.data.category_id ?? '')}
                            onValueChange={(v) => form.setData('category_id', v)}
                        >
                            <SelectTrigger className="mt-1" aria-invalid={!!form.errors.category_id}>
                                <SelectValue placeholder="Select category" />
                            </SelectTrigger>
                            <SelectContent>
                                {categoryOptions.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldError error={form.errors.category_id} />
                    </div>

                    <div>
                        <Label>
                            Brand <span className="text-destructive">*</span>
                        </Label>
                        <Select
                            value={String(form.data.brand_id ?? '')}
                            onValueChange={(v) => form.setData('brand_id', v)}
                        >
                            <SelectTrigger className="mt-1" aria-invalid={!!form.errors.brand_id}>
                                <SelectValue placeholder="Select brand" />
                            </SelectTrigger>
                            <SelectContent>
                                {brandOptions.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldError error={form.errors.brand_id} />
                    </div>

                    <div>
                        <Label>
                            Unit <span className="text-destructive">*</span>
                        </Label>
                        <Select
                            value={String(form.data.unit_id ?? '')}
                            onValueChange={(v) => form.setData('unit_id', v)}
                        >
                            <SelectTrigger className="mt-1" aria-invalid={!!form.errors.unit_id}>
                                <SelectValue placeholder="Select unit" />
                            </SelectTrigger>
                            <SelectContent>
                                {unitOptions.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldError error={form.errors.unit_id} />
                    </div>

                    <div>
                        <Label>Warranty</Label>
                        <Select
                            value={form.data.warranty_id ? String(form.data.warranty_id) : '__none'}
                            onValueChange={(v) => form.setData('warranty_id', v === '__none' ? null : v)}
                        >
                            <SelectTrigger className="mt-1" aria-invalid={!!form.errors.warranty_id}>
                                <SelectValue placeholder="No warranty" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none">No warranty</SelectItem>
                                {warrantyOptions.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldError error={form.errors.warranty_id} />
                    </div>

                    <div className="md:col-span-2">
                        <Label>
                            Product Name <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Product name"
                            className="mt-1"
                            aria-invalid={!!form.errors.name}
                        />
                        <FieldError error={form.errors.name} />
                    </div>

                    <div>
                        <Label>
                            Product Code <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            placeholder="SKU / barcode"
                            className="mt-1"
                            aria-invalid={!!form.errors.code}
                        />
                        <FieldError error={form.errors.code} />
                    </div>

                    <div>
                        <Label>
                            Purchase Price <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.purchase_price}
                            onChange={(e) => form.setData('purchase_price', e.target.value)}
                            className="mt-1"
                            aria-invalid={!!form.errors.purchase_price}
                        />
                        <FieldError error={form.errors.purchase_price} />
                    </div>

                    <div>
                        <Label>
                            Sale Price <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.sale_price}
                            onChange={(e) => form.setData('sale_price', e.target.value)}
                            className="mt-1"
                            aria-invalid={!!form.errors.sale_price}
                        />
                        <FieldError error={form.errors.sale_price} />
                    </div>

                    <div>
                        <Label>Discount Price</Label>
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.discount_price}
                            onChange={(e) => form.setData('discount_price', e.target.value)}
                            className="mt-1"
                            aria-invalid={!!form.errors.discount_price}
                        />
                        <FieldError error={form.errors.discount_price} />
                    </div>
                </div>
            </SectionCard>

            {/* Section 2: Type & Tailor */}
            <SectionCard title="Type & Tailor Options">
                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label>
                            Product Type <span className="text-destructive">*</span>
                        </Label>
                        <div className="mt-2 flex gap-4">
                            {['stitch', 'notstitch'].map((t) => (
                                <label key={t} className="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="radio"
                                        name="type"
                                        value={t}
                                        checked={form.data.type === t}
                                        onChange={() => form.setData('type', t)}
                                        className="accent-primary"
                                    />
                                    <span className="text-sm capitalize">{t === 'notstitch' ? 'Not Stitch' : 'Stitch'}</span>
                                </label>
                            ))}
                        </div>
                        <FieldError error={form.errors.type} />
                    </div>

                    <div>
                        <Label>
                            Tailor Option <span className="text-destructive">*</span>
                        </Label>
                        <div className="mt-2 flex gap-4">
                            {['yes', 'no'].map((t) => (
                                <label key={t} className="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="radio"
                                        name="tailor_option"
                                        value={t}
                                        checked={form.data.tailor_option === t}
                                        onChange={() => form.setData('tailor_option', t)}
                                        className="accent-primary"
                                    />
                                    <span className="text-sm capitalize">{t}</span>
                                </label>
                            ))}
                        </div>
                        <FieldError error={form.errors.tailor_option} />
                    </div>

                    <div>
                        <Label>
                            Tailor Price <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.tailor_price}
                            onChange={(e) => form.setData('tailor_price', e.target.value)}
                            className="mt-1"
                            aria-invalid={!!form.errors.tailor_price}
                        />
                        <FieldError error={form.errors.tailor_price} />
                    </div>
                </div>
            </SectionCard>

            {/* Section 3: Variations / Options */}
            <SectionCard title="Colors, Sizes & Options">
                <div className="space-y-5">
                    <div>
                        <Label className="mb-2 block">Colors</Label>
                        <MultiCheckbox
                            options={colors || []}
                            selected={form.data.colors}
                            onChange={(v) => form.setData('colors', v)}
                            colorMode
                        />
                        <FieldError error={form.errors.colors} />
                    </div>

                    <div>
                        <Label className="mb-2 block">Sizes</Label>
                        <MultiCheckbox
                            options={sizeOptions}
                            selected={form.data.sizes}
                            onChange={(v) => form.setData('sizes', v)}
                        />
                        <FieldError error={form.errors.sizes} />
                    </div>

                    <div>
                        <Label className="mb-2 block">Tailor Measurements</Label>
                        <MultiCheckbox
                            options={tailorOptions}
                            selected={form.data.tailormeasurement}
                            onChange={(v) => form.setData('tailormeasurement', v)}
                        />
                        <FieldError error={form.errors.tailormeasurement} />
                    </div>

                    <div>
                        <Label>Tags</Label>
                        <Input
                            value={tagInput}
                            onChange={handleTagInput}
                            placeholder="Comma separated: tag1, tag2, tag3"
                            className="mt-1"
                        />
                        <div className="mt-2 flex flex-wrap gap-1">
                            {(form.data.tags || []).map((tag, i) => (
                                <Badge key={i} variant="secondary">
                                    {tag}
                                </Badge>
                            ))}
                        </div>
                        <FieldError error={form.errors.tags} />
                    </div>
                </div>
            </SectionCard>

            {/* Section 4: Content */}
            <SectionCard title="Content & Settings">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="md:col-span-2">
                        <Label>Description (EN)</Label>
                        <textarea
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            rows={4}
                            className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                            placeholder="Product description..."
                            aria-invalid={!!form.errors.description}
                        />
                        <FieldError error={form.errors.description} />
                    </div>

                    <div className="md:col-span-2">
                        <Label>Description (BN)</Label>
                        <textarea
                            value={form.data.bn_description}
                            onChange={(e) => form.setData('bn_description', e.target.value)}
                            rows={4}
                            className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                            placeholder="বাংলা বিবরণ..."
                            aria-invalid={!!form.errors.bn_description}
                        />
                        <FieldError error={form.errors.bn_description} />
                    </div>

                    <div className="md:col-span-2">
                        <Label>Delivery Info</Label>
                        <textarea
                            value={form.data.delivery_info}
                            onChange={(e) => form.setData('delivery_info', e.target.value)}
                            rows={3}
                            className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                            placeholder="Delivery information..."
                            aria-invalid={!!form.errors.delivery_info}
                        />
                        <FieldError error={form.errors.delivery_info} />
                    </div>

                    <div>
                        <Label>YouTube Link</Label>
                        <Input
                            value={form.data.youtube_link}
                            onChange={(e) => form.setData('youtube_link', e.target.value)}
                            placeholder="https://youtube.com/..."
                            className="mt-1"
                            aria-invalid={!!form.errors.youtube_link}
                        />
                        <FieldError error={form.errors.youtube_link} />
                    </div>

                    <div className="flex items-end gap-6">
                        <div>
                            <Label>Visible</Label>
                            <div className="mt-2 flex gap-4">
                                {['yes', 'no'].map((v) => (
                                    <label key={v} className="flex cursor-pointer items-center gap-2">
                                        <input
                                            type="radio"
                                            name="visible"
                                            value={v}
                                            checked={form.data.visible === v}
                                            onChange={() => form.setData('visible', v)}
                                            className="accent-primary"
                                        />
                                        <span className="text-sm capitalize">{v}</span>
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div>
                            <Label>Status</Label>
                            <Select
                                value={String(form.data.status ?? '1')}
                                onValueChange={(v) => form.setData('status', v)}
                            >
                                <SelectTrigger className="mt-1 w-36" aria-invalid={!!form.errors.status}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Active</SelectItem>
                                    <SelectItem value="0">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                            <FieldError error={form.errors.status} />
                        </div>
                    </div>
                </div>
            </SectionCard>

            {/* Section 5: Images */}
            <SectionCard title="Images">
                <div className="space-y-6">
                    <div>
                        <Label>Main Image</Label>
                        {isEditing && form.data._existing_image && !imagePreview && (
                            <div className="mt-2 mb-2">
                                <img src={`/storage/${form.data._existing_image}`} alt="Current" className="h-24 w-24 rounded object-cover" />
                                <p className="mt-1 text-xs text-muted-foreground">Upload a new image to replace.</p>
                            </div>
                        )}
                        {imagePreview && (
                            <div className="mt-2 mb-2">
                                <img src={imagePreview} alt="Preview" className="h-24 w-24 rounded object-cover" />
                            </div>
                        )}
                        <Input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={handleImageChange}
                            className="mt-1"
                            aria-invalid={!!form.errors.image}
                        />
                        <FieldError error={form.errors.image} />
                    </div>

                    <div>
                        <Label>Additional Photos</Label>
                        <Input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            onChange={(e) => form.setData('photos', Array.from(e.target.files))}
                            className="mt-1"
                            aria-invalid={!!form.errors['photos.*']}
                        />
                        <p className="mt-1 text-xs text-muted-foreground">Select multiple files. Max 3MB each.</p>
                        <FieldError error={form.errors.photos} />
                    </div>
                </div>
            </SectionCard>
        </div>
    );
}
