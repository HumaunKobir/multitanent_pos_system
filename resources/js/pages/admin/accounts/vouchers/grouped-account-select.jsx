import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';

export function FlatAccountSelect({ accounts = [], value, onChange, placeholder = 'Select account...', disabled }) {
    return (
        <Select value={value ? String(value) : ''} onValueChange={(v) => onChange(v)} disabled={disabled}>
            <SelectTrigger className="w-full">
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {accounts.map((acc) => (
                    <SelectItem key={acc.id} value={String(acc.id)}>
                        {acc.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export function GroupedAccountSelect({ picker = [], value, onChange, placeholder = 'Select account...', disabled }) {
    return (
        <Select value={value ? String(value) : ''} onValueChange={(v) => onChange(v)} disabled={disabled}>
            <SelectTrigger className="w-full">
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {picker.map((section) => (
                    <SelectGroup key={section.type}>
                        <SelectLabel>{section.type}</SelectLabel>
                        {section.groups?.map((group) => (
                            <SelectGroup key={`${section.type}-${group.parent?.id}`}>
                                <SelectLabel className="pl-2 text-xs text-muted-foreground">
                                    {group.parent?.code} — {group.parent?.name}
                                </SelectLabel>
                                {group.accounts?.map((acc) => (
                                    <SelectItem key={acc.id} value={String(acc.id)} className="pl-6">
                                        {acc.label}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        ))}
                    </SelectGroup>
                ))}
            </SelectContent>
        </Select>
    );
}
