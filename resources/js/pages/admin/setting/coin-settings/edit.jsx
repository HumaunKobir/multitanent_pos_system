import { Can } from '@/components/can';
import { useAppToast } from '@/contexts/app-toast-context';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { route } from '@/lib/route';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Coins, Save } from 'lucide-react';
import { useEffect } from 'react';

export default function CoinSettingsEdit({ coinSettings, isCreating = false }) {
    const { flash } = usePage().props;
    const toast = useAppToast();

    const { data, setData, post, put, processing, errors } = useForm({
        enabled: coinSettings.enabled ?? false,
        earn_spend_amount: coinSettings.earn_spend_amount ?? '100',
        earn_coins: coinSettings.earn_coins ?? '1',
        coin_value: coinSettings.coin_value ?? '1',
        min_redeem_coins: coinSettings.min_redeem_coins ?? '0',
        max_redeem_percent: coinSettings.max_redeem_percent ?? '50',
    });

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash.success, flash.error]);

    const submit = (event) => {
        event.preventDefault();

        const options = { preserveScroll: true };

        if (isCreating) {
            post(route('setting.coin-settings.store'), options);
        } else {
            put(route('setting.coin-settings.update'), options);
        }
    };

    const previewEarn =
        parseFloat(data.earn_spend_amount) > 0
            ? `৳${data.earn_spend_amount} spend = ${data.earn_coins} coin(s)`
            : '—';
    const previewValue = `1 coin = ৳${parseFloat(data.coin_value || 0).toFixed(2)}`;

    return (
        <>
            <Head title="Coin Settings" />

            <div className="px-2 py-1">
                <div className="mb-4 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Coins className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">
                                {isCreating ? 'Create Coin Settings' : 'Edit Coin Settings'}
                            </h1>
                            <p className="text-xs text-white/60">
                                Configure earn and redeem rules for POS sales at {coinSettings.branch_name}.
                            </p>
                        </div>
                    </div>
                    <Can permission={isCreating ? 'setting.coin-settings.create' : 'setting.coin-settings.update'}>
                        <Button type="submit" form="coin-settings-form" disabled={processing} className="bg-emerald-600 hover:bg-emerald-700">
                            <Save className="size-4" />
                            {processing ? 'Saving...' : isCreating ? 'Create Settings' : 'Save Settings'}
                        </Button>
                    </Can>
                </div>

                <form id="coin-settings-form" onSubmit={submit} className="space-y-4">
                    <section className="overflow-hidden rounded-lg border bg-card">
                        <div className="border-b bg-muted/30 px-5 py-3">
                            <h2 className="text-sm font-semibold text-foreground">General</h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">Enable or disable the coin system for this branch.</p>
                        </div>
                        <div className="px-5 py-4">
                            <label className="flex cursor-pointer items-center gap-3">
                                <input
                                    type="checkbox"
                                    checked={data.enabled}
                                    onChange={(e) => setData('enabled', e.target.checked)}
                                    className="size-4 rounded border-input"
                                />
                                <span className="text-sm font-medium">Enable coin system</span>
                            </label>
                            {errors.enabled && <p className="mt-2 text-sm text-destructive">{errors.enabled}</p>}
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-lg border bg-card">
                        <div className="border-b bg-muted/30 px-5 py-3">
                            <h2 className="text-sm font-semibold text-foreground">Earn Rules</h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">How many coins customers earn when they pay.</p>
                        </div>
                        <div className="grid gap-4 px-5 py-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="earn_spend_amount">Spend amount (৳)</Label>
                                <Input
                                    id="earn_spend_amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    value={data.earn_spend_amount}
                                    onChange={(e) => setData('earn_spend_amount', e.target.value)}
                                    className="mt-1"
                                />
                                {errors.earn_spend_amount && <p className="mt-1 text-sm text-destructive">{errors.earn_spend_amount}</p>}
                            </div>
                            <div>
                                <Label htmlFor="earn_coins">Coins earned</Label>
                                <Input
                                    id="earn_coins"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    value={data.earn_coins}
                                    onChange={(e) => setData('earn_coins', e.target.value)}
                                    className="mt-1"
                                />
                                {errors.earn_coins && <p className="mt-1 text-sm text-destructive">{errors.earn_coins}</p>}
                            </div>
                            <p className="text-xs text-muted-foreground sm:col-span-2">Preview: {previewEarn}</p>
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-lg border bg-card">
                        <div className="border-b bg-muted/30 px-5 py-3">
                            <h2 className="text-sm font-semibold text-foreground">Redeem Rules</h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">Coin value and redemption limits at POS checkout.</p>
                        </div>
                        <div className="grid gap-4 px-5 py-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="coin_value">1 coin value (৳)</Label>
                                <Input
                                    id="coin_value"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    value={data.coin_value}
                                    onChange={(e) => setData('coin_value', e.target.value)}
                                    className="mt-1"
                                />
                                {errors.coin_value && <p className="mt-1 text-sm text-destructive">{errors.coin_value}</p>}
                            </div>
                            <div>
                                <Label htmlFor="min_redeem_coins">Minimum redeem (coins)</Label>
                                <Input
                                    id="min_redeem_coins"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.min_redeem_coins}
                                    onChange={(e) => setData('min_redeem_coins', e.target.value)}
                                    className="mt-1"
                                />
                                {errors.min_redeem_coins && <p className="mt-1 text-sm text-destructive">{errors.min_redeem_coins}</p>}
                            </div>
                            <div>
                                <Label htmlFor="max_redeem_percent">Max bill % via coins</Label>
                                <Input
                                    id="max_redeem_percent"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value={data.max_redeem_percent}
                                    onChange={(e) => setData('max_redeem_percent', e.target.value)}
                                    className="mt-1"
                                />
                                {errors.max_redeem_percent && <p className="mt-1 text-sm text-destructive">{errors.max_redeem_percent}</p>}
                            </div>
                            <p className="text-xs text-muted-foreground sm:col-span-2">Preview: {previewValue}</p>
                        </div>
                    </section>
                </form>
            </div>
        </>
    );
}
