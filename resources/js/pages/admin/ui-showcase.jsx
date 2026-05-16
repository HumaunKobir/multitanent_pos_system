import { Head } from '@inertiajs/react';
import { useState } from 'react';

import { SmartSelect } from '@/components/smart-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePicker, DateInput, DateRangeFields } from '@/components/ui/date-kit';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAppToast } from '@/contexts/app-toast-context';

const smartSelectSeed = [
    { value: 'alpha', label: 'Alpha corridor' },
    { value: 'beta', label: 'Beta lane' },
    { value: 'gamma', label: 'Gamma hub' },
];

export default function AdminUiShowcase() {
    const toast = useAppToast();
    const [priority, setPriority] = useState('standard');

    const [searchOnlyOptions] = useState(() => [...smartSelectSeed]);
    const [searchOnlyValue, setSearchOnlyValue] = useState(/** @type {string | null} */ ('alpha'));

    const [inlineOptions, setInlineOptions] = useState(() => [...smartSelectSeed]);
    const [inlineValue, setInlineValue] = useState(/** @type {string | null} */ (null));

    const [modalOptions, setModalOptions] = useState(() => [...smartSelectSeed]);
    const [modalValue, setModalValue] = useState(/** @type {string | null} */ (null));

    const [dueDate, setDueDate] = useState('');
    const [windowFrom, setWindowFrom] = useState('');
    const [windowTo, setWindowTo] = useState('');
    const [sheetRangeFrom, setSheetRangeFrom] = useState('2026-05-01');
    const [sheetRangeTo, setSheetRangeTo] = useState('2026-05-31');
    const [sheetSingle, setSheetSingle] = useState('');
    const [compactRangeFrom, setCompactRangeFrom] = useState('');
    const [compactRangeTo, setCompactRangeTo] = useState('');

    return (
        <>
            <Head title="Admin UI showcase" />
            <div className="mx-auto max-w-6xl space-y-10 px-6 py-8">
                <header className="border-b border-border pb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">UI showcase</h1>
                    <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                        Reference blocks for admin surfaces: cards, inputs, date kit, select, and primary actions. Interact
                        with the toast row to preview the custom notification surface.
                    </p>
                </header>

                <section className="grid gap-4 md:grid-cols-2">
                    <Card className="rounded-none border-border">
                        <CardHeader>
                            <CardTitle>Incident template</CardTitle>
                            <CardDescription>Card pattern with sharp geometry and neutral chrome.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm text-muted-foreground">
                            <p>Use this card style for dense operational summaries or secondary modules.</p>
                        </CardContent>
                        <CardFooter className="border-t border-border pt-4">
                            <Button type="button" variant="outline" className="rounded-none">
                                Secondary
                            </Button>
                        </CardFooter>
                    </Card>

                    <Card className="rounded-none border-border">
                        <CardHeader>
                            <CardTitle>Toast preview</CardTitle>
                            <CardDescription>Custom stack · bottom-right · flash-compatible styling.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                className="rounded-none"
                                onClick={() => toast.success('Policy sync finished successfully.')}
                            >
                                Success
                            </Button>
                            <Button
                                type="button"
                                variant="secondary"
                                className="rounded-none"
                                onClick={() => toast.info('No new alerts in the last hour.')}
                            >
                                Info
                            </Button>
                            <Button
                                type="button"
                                variant="secondary"
                                className="rounded-none"
                                onClick={() => toast.warning('Review queue approaching SLA threshold.')}
                            >
                                Warning
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                className="rounded-none"
                                onClick={() => toast.error('Handshake failed with upstream provider.')}
                            >
                                Error
                            </Button>
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-6 border border-border p-6 md:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="admin-title">Title</Label>
                        <Input
                            id="admin-title"
                            name="title"
                            placeholder="Quarterly compliance review"
                            className="rounded-none"
                            autoComplete="off"
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="admin-priority">Priority route</Label>
                        <Select value={priority} onValueChange={setPriority}>
                            <SelectTrigger
                                id="admin-priority"
                                className="h-11 w-full gap-0 overflow-hidden rounded-none border-border bg-linear-to-r from-muted/35 via-transparent to-transparent pr-3 pl-0 shadow-none ring-offset-background transition-shadow hover:bg-muted/25 focus-visible:ring-2 focus-visible:ring-ring md:w-full **:data-[slot=select-value]:min-w-0 **:data-[slot=select-value]:flex-1 **:data-[slot=select-value]:pl-3"
                            >
                                <span
                                    className="flex h-full min-h-11 w-11 shrink-0 items-center justify-center border-r border-border bg-muted/50 font-mono text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-muted-foreground"
                                    aria-hidden
                                >
                                    RT
                                </span>
                                <SelectValue placeholder="Select priority" />
                            </SelectTrigger>
                            <SelectContent className="rounded-none border-border shadow-lg ring-1 ring-border/60">
                                <SelectItem
                                    value="standard"
                                    className="cursor-pointer rounded-none pl-8 data-highlighted:border-l-2 data-highlighted:border-primary data-highlighted:bg-accent/80 data-[state=checked]:border-l-2 data-[state=checked]:border-primary"
                                >
                                    Standard lane
                                </SelectItem>
                                <SelectItem
                                    value="elevated"
                                    className="cursor-pointer rounded-none pl-8 data-highlighted:border-l-2 data-highlighted:border-primary data-highlighted:bg-accent/80 data-[state=checked]:border-l-2 data-[state=checked]:border-primary"
                                >
                                    Elevated lane
                                </SelectItem>
                                <SelectItem
                                    value="critical"
                                    className="cursor-pointer rounded-none pl-8 data-highlighted:border-l-2 data-highlighted:border-primary data-highlighted:bg-accent/80 data-[state=checked]:border-l-2 data-[state=checked]:border-primary"
                                >
                                    Critical response
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p className="text-[0.7rem] text-muted-foreground">
                            Rail marker + left-edge selection chrome · compact operational control.
                        </p>
                    </div>

                    <div className="md:col-span-2">
                        <Button type="button" className="rounded-none">
                            Save draft
                        </Button>
                    </div>
                </section>

                <section className="space-y-6 border border-border p-6">
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">Smart select</h2>
                        <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                            Searchable combobox with optional create. Use <code className="text-xs">searchable</code>,{' '}
                            <code className="text-xs">creatable</code>, and <code className="text-xs">createMode</code>{' '}
                            (<code className="text-xs">inline</code> vs <code className="text-xs">modal</code>) per field.
                            Inline add uses the check row; modal opens for richer data then saves and selects.
                        </p>
                    </div>

                    <div className="grid gap-8 lg:grid-cols-3">
                        <div className="space-y-2">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Search only
                            </p>
                            <SmartSelect
                                label="Region (no create)"
                                options={searchOnlyOptions}
                                value={searchOnlyValue}
                                onValueChange={setSearchOnlyValue}
                                searchable
                                creatable={false}
                                placeholder="Type to filter…"
                                triggerClassName="rounded-none"
                            />
                            <p className="text-[0.7rem] text-muted-foreground">
                                Value: <span className="font-mono text-foreground">{searchOnlyValue ?? '—'}</span>
                            </p>
                        </div>

                        <div className="space-y-2">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Search + inline create
                            </p>
                            <SmartSelect
                                label="Site (quick add)"
                                options={inlineOptions}
                                value={inlineValue}
                                onValueChange={setInlineValue}
                                onOptionsChange={setInlineOptions}
                                searchable
                                creatable
                                createMode="inline"
                                placeholder="Search; if empty, add with check…"
                                triggerClassName="rounded-none"
                            />
                            <p className="text-[0.7rem] text-muted-foreground">
                                Type something that does not match (e.g. <span className="font-mono">Zeta</span>) then
                                choose the add row. Options: {inlineOptions.length}.
                            </p>
                        </div>

                        <div className="space-y-2">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Search + modal create
                            </p>
                            <SmartSelect
                                label="Vendor (modal form)"
                                options={modalOptions}
                                value={modalValue}
                                onValueChange={setModalValue}
                                onOptionsChange={setModalOptions}
                                searchable
                                creatable
                                createMode="modal"
                                modalTitle="Register vendor"
                                modalDescription="SKU and notes are required for procurement."
                                onModalCreate={async (payload) => {
                                    const sku = payload.sku.trim() || 'NEW';

                                    return {
                                        value: `${sku.toLowerCase().replace(/\s+/g, '-')}-${Date.now().toString(36)}`,
                                        label: `${payload.label} · ${sku}`,
                                    };
                                }}
                                placeholder="Search; unmatched opens modal on add…"
                                triggerClassName="rounded-none"
                            />
                            <p className="text-[0.7rem] text-muted-foreground">
                                No match → add row → opens modal → Save &amp; selects. Options: {modalOptions.length}.
                            </p>
                        </div>
                    </div>
                </section>

                <section className="space-y-6 border border-border p-6" data-showcase="date-kit">
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">Date kit</h2>
                        <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                            Use <code className="text-xs">DatePicker</code> for the sheet-style popover (single or range;
                            set <code className="text-xs">panels</code> to <code className="text-xs">1</code> or{' '}
                            <code className="text-xs">2</code> for one or two months; week starts Sunday). Use{' '}
                            <code className="text-xs">DateInput</code> / <code className="text-xs">DateRangeFields</code>{' '}
                            when you need native <code className="text-xs">type=&quot;date&quot;</code> for forms or
                            mobile OS pickers.
                        </p>
                    </div>

                    <div className="max-w-xl space-y-2">
                        <DatePicker
                            label="Reporting period (dual month · range)"
                            mode="range"
                            panels={2}
                            from={sheetRangeFrom}
                            to={sheetRangeTo}
                            placeholder="Select range"
                            onRangeChange={({ from, to }) => {
                                setSheetRangeFrom(from);
                                setSheetRangeTo(to);
                            }}
                        />
                        <p className="text-[0.7rem] text-muted-foreground">
                            ISO:{' '}
                            <span className="font-mono text-foreground">
                                {sheetRangeFrom || '—'} → {sheetRangeTo || '—'}
                            </span>
                        </p>
                    </div>

                    <div className="grid gap-8 lg:grid-cols-2">
                        <DatePicker
                            label="Single date · one month"
                            mode="single"
                            panels={1}
                            value={sheetSingle}
                            onValueChange={setSheetSingle}
                            placeholder="Pick a day"
                        />
                        <DatePicker
                            label="Range · single month view"
                            mode="range"
                            panels={1}
                            from={compactRangeFrom}
                            to={compactRangeTo}
                            placeholder="Select start and end"
                            onRangeChange={({ from, to }) => {
                                setCompactRangeFrom(from);
                                setCompactRangeTo(to);
                            }}
                        />
                    </div>

                    <div className="grid gap-8 border-t border-border pt-8 lg:grid-cols-2">
                        <DateInput
                            id="showcase-due"
                            label="Due date (native)"
                            description="Accessible native picker for dense forms."
                            value={dueDate}
                            onValueChange={setDueDate}
                        />
                        <div className="space-y-2">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Native range pair
                            </p>
                            <DateRangeFields
                                fromId="showcase-w-from"
                                toId="showcase-w-to"
                                fromLabel="Window start"
                                toLabel="Window end"
                                fromValue={windowFrom}
                                toValue={windowTo}
                                onFromChange={setWindowFrom}
                                onToChange={setWindowTo}
                            />
                        </div>
                    </div>
                </section>

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                        Compact grid
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {['North', 'South', 'East', 'West'].map((region) => (
                            <div
                                key={region}
                                className="border border-border bg-card px-4 py-5 text-sm font-medium text-card-foreground"
                            >
                                {region} corridor
                                <p className="mt-2 text-xs font-normal text-muted-foreground">
                                    Utilization within expected bounds.
                                </p>
                            </div>
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}
