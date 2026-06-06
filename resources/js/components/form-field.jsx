import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export function RequiredMark({ className }) {
    return <span className={cn('ml-0.5 text-destructive', className)}>*</span>;
}

export function FormField({
    label,
    name,
    required = false,
    error,
    className,
    labelClassName,
    children,
}) {
    return (
        <div className={className}>
            <Label htmlFor={name} className={labelClassName}>
                {label}
                {required && <RequiredMark />}
            </Label>
            {children}
            {error && <p className="mt-1 text-xs text-destructive">{error}</p>}
        </div>
    );
}
