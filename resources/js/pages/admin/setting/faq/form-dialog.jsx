import { CKEditorField } from '@/components/ckeditor-field';
import { RequiredMark } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/react';
import { HelpCircle } from 'lucide-react';
import { useEffect } from 'react';

export default function FaqFormDialog({ open, onOpenChange, item, routes }) {
    const isEditing = !!item?.id;

    const form = useForm({
        question: item?.question ?? '',
        answer: item?.answer ?? '',
        status: String(item?.status ?? 1),
    });

    useEffect(() => {
        form.setData({
            question: item?.question ?? '',
            answer: item?.answer ?? '',
            status: String(item?.status ?? 1),
        });
        form.clearErrors();
    }, [item]);

    function handleSubmit(event) {
        event.preventDefault();

        const options = {
            onSuccess: () => onOpenChange(false),
        };

        if (isEditing) {
            form.submit('patch', routes.update(item.id), options);
        } else {
            form.post(routes.store, options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="p-0 sm:max-w-2xl">
                <div className="flex items-center gap-2.5 bg-blue-950 px-5 py-3">
                    <div className="flex size-7 items-center justify-center rounded-md bg-white/15">
                        <HelpCircle className="size-3.5 text-white" />
                    </div>
                    <h2 className="text-sm font-semibold text-white">{isEditing ? 'Edit FAQ' : 'Create FAQ'}</h2>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4 px-5 py-4">
                    <div>
                        <Label htmlFor="question">
                            Question
                            <RequiredMark />
                        </Label>
                        <Input
                            id="question"
                            value={form.data.question}
                            onChange={(event) => form.setData('question', event.target.value)}
                            placeholder="Enter the question"
                            className="mt-1"
                            aria-invalid={!!form.errors.question}
                        />
                        {form.errors.question && <p className="mt-1 text-xs text-destructive">{form.errors.question}</p>}
                    </div>

                    <div>
                        <Label htmlFor="faq-answer">
                            Answer
                            <RequiredMark />
                        </Label>
                        <div className="mt-1">
                            <CKEditorField
                                id="faq-answer"
                                value={form.data.answer}
                                onChange={(value) => form.setData('answer', value)}
                                height={220}
                            />
                        </div>
                        {form.errors.answer && <p className="mt-1 text-xs text-destructive">{form.errors.answer}</p>}
                    </div>

                    <div>
                        <Label htmlFor="status">
                            Status
                            <RequiredMark />
                        </Label>
                        <Select value={form.data.status} onValueChange={(value) => form.setData('status', value)}>
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
                            className="bg-emerald-600 text-white shadow-sm shadow-emerald-500/30 transition-all duration-150 hover:bg-emerald-600 hover:-translate-y-0.5 hover:shadow-md hover:shadow-emerald-500/50"
                        >
                            {isEditing ? 'Update' : 'Create'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
