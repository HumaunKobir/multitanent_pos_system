import { Button } from '@/components/ui/button';

export function VoucherFormFooter({ onCancel, processing, submitLabel = 'Create Voucher', formId = 'voucher-form' }) {
    return (
        <div className="flex justify-end gap-3 border-t px-5 py-4">
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="border-red-500 text-red-500 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-500 hover:text-white hover:shadow-md hover:shadow-red-500/30"
                onClick={onCancel}
            >
                Cancel
            </Button>
            <Button
                type="submit"
                form={formId}
                disabled={processing}
                size="sm"
                className="bg-emerald-600 text-white shadow-sm shadow-emerald-500/30 transition-all duration-150 hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-md hover:shadow-emerald-500/50"
            >
                {submitLabel}
            </Button>
        </div>
    );
}
