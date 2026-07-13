import { AdminCreateButton } from '@/components/admin/row-actions';
import { Can } from '@/components/can';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAppToast } from '@/contexts/app-toast-context';
import { route } from '@/lib/route';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Coins, Pencil, Plus } from 'lucide-react';
import { useEffect } from 'react';

export default function CoinSettingsIndex({ branchName, coinSettings }) {
    const { flash } = usePage().props;
    const toast = useAppToast();

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash.success, flash.error]);

    const hasSettings = coinSettings !== null;

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
                            <h1 className="text-base font-semibold text-white">Coin Settings</h1>
                            <p className="text-xs text-white/60">
                                Configure earn and redeem rules for POS sales at {branchName}.
                            </p>
                        </div>
                    </div>
                    {!hasSettings && (
                        <AdminCreateButton
                            permission="setting.coin-settings.create"
                            onClick={() => router.visit(route('setting.coin-settings.create'))}
                            label="Create Coin Settings"
                            icon={Plus}
                            className="border border-white/30 bg-white/10 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-white/50 hover:bg-white/20 hover:shadow-md"
                        />
                    )}
                </div>

                {hasSettings ? (
                    <section className="overflow-hidden rounded-lg border bg-card">
                        <div className="border-b bg-muted/30 px-5 py-3">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-sm font-semibold text-foreground">Current configuration</h2>
                                    <p className="mt-0.5 text-xs text-muted-foreground">Branch coin rules for POS checkout.</p>
                                </div>
                                <Can permission="setting.coin-settings.update">
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={route('setting.coin-settings.edit')}>
                                            <Pencil className="size-4" />
                                            Edit Settings
                                        </Link>
                                    </Button>
                                </Can>
                            </div>
                        </div>
                        <div className="grid gap-4 px-5 py-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <p className="text-xs text-muted-foreground">Status</p>
                                <Badge
                                    className={
                                        coinSettings.enabled
                                            ? 'mt-1 bg-green-600 text-white hover:bg-green-700'
                                            : 'mt-1 bg-red-600 text-white hover:bg-red-700'
                                    }
                                >
                                    {coinSettings.enabled ? 'Enabled' : 'Disabled'}
                                </Badge>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">Earn rule</p>
                                <p className="mt-1 text-sm font-medium">
                                    ৳{coinSettings.earn_spend_amount} spend = {coinSettings.earn_coins} coin(s)
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">Coin value</p>
                                <p className="mt-1 text-sm font-medium">1 coin = ৳{parseFloat(coinSettings.coin_value).toFixed(2)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">Minimum redeem</p>
                                <p className="mt-1 text-sm font-medium">{coinSettings.min_redeem_coins} coin(s)</p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">Max bill via coins</p>
                                <p className="mt-1 text-sm font-medium">{coinSettings.max_redeem_percent}%</p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">Coin expiry</p>
                                <p className="mt-1 text-sm font-medium">
                                    {coinSettings.expiry_value && coinSettings.expiry_unit
                                        ? `${coinSettings.expiry_value} ${coinSettings.expiry_unit}${Number(coinSettings.expiry_value) === 1 ? '' : 's'}`
                                        : 'Never'}
                                </p>
                            </div>
                        </div>
                    </section>
                ) : (
                    <section className="rounded-lg border border-dashed bg-muted/20 px-5 py-10 text-center">
                        <Coins className="mx-auto size-10 text-muted-foreground/60" />
                        <h2 className="mt-3 text-sm font-semibold text-foreground">No coin settings yet</h2>
                        <p className="mx-auto mt-1 max-w-md text-xs text-muted-foreground">
                            Create coin settings for this branch to let customers earn and redeem coins during POS sales.
                        </p>
                        <Can permission="setting.coin-settings.create">
                            <Button asChild className="mt-4 bg-emerald-600 hover:bg-emerald-700">
                                <Link href={route('setting.coin-settings.create')}>Create Coin Settings</Link>
                            </Button>
                        </Can>
                    </section>
                )}
            </div>
        </>
    );
}
