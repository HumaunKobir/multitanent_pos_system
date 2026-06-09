import { CKEditorField } from '@/components/ckeditor-field';
import { useAppToast } from '@/contexts/app-toast-context';
import { Button } from '@/components/ui/button';
import { route } from '@/lib/route';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FileText, Save } from 'lucide-react';
import { useEffect } from 'react';

export default function PageContentEdit({ page }) {
    const { flash } = usePage().props;
    const toast = useAppToast();

    const { data, setData, put, processing, errors, transform } = useForm({
        content: page.content ?? '',
    });

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash.success, flash.error]);

    transform((formData) => ({
        ...formData,
        _method: 'put',
    }));

    const submit = (event) => {
        event.preventDefault();

        put(route('setting.page-content.update', { page: page.slug }), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={page.title} />

            <div className="px-2 py-1">
                <div className="mb-4 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <FileText className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">{page.title}</h1>
                            <p className="text-xs text-white/60">Manage the storefront content shown on the {page.title} page.</p>
                        </div>
                    </div>
                    <Button type="submit" form="page-content-form" disabled={processing} className="bg-emerald-600 hover:bg-emerald-700">
                        <Save className="size-4" />
                        {processing ? 'Saving...' : 'Save Content'}
                    </Button>
                </div>

                <form id="page-content-form" onSubmit={submit} className="space-y-4">
                    <section className="overflow-hidden rounded-lg border bg-card">
                        <div className="border-b bg-muted/30 px-5 py-3">
                            <h2 className="text-sm font-semibold text-foreground">Page content</h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                This content appears in the main section of the public {page.title} page.
                            </p>
                        </div>
                        <div className="px-5 py-4">
                            <CKEditorField
                                id={`page-content-${page.slug}`}
                                value={data.content}
                                onChange={(value) => setData('content', value)}
                                height={320}
                            />
                            {errors.content && <p className="mt-2 text-sm text-destructive">{errors.content}</p>}
                        </div>
                    </section>
                </form>
            </div>
        </>
    );
}
