import { computeCoinDiscount, computeCoinsEarned, isCoinSystemActive, maxRedeemableCoins, previewCoinSale } from '@/lib/pos-coin';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Coins } from 'lucide-react';
import { useEffect } from 'react';

function canRedeemCoins(balance, settings, netBeforeCoin) {
    const maxRedeemable = maxRedeemableCoins(balance, settings, netBeforeCoin);
    const minRedeem = parseFloat(settings?.min_redeem_coins) || 0;

    return maxRedeemable > 0 && maxRedeemable >= minRedeem;
}

export function SellCoinFields({
    customerId,
    walkInCustomerId,
    coinSettings,
    coinInfo,
    coinInfoLoading = false,
    coinsRedeemed,
    onCoinsRedeemedChange,
    netBeforeCoin,
    earnBase,
    error,
    inputClassName = '',
    balanceOffset = 0,
}) {
    const isWalkIn =
        !customerId ||
        String(customerId) === String(walkInCustomerId ?? '') ||
        coinInfo?.is_default;
    const activeSettings = coinInfo?.settings?.enabled ? coinInfo.settings : coinSettings;
    const showCoins = isCoinSystemActive(activeSettings) && !isWalkIn && customerId;
    const effectiveBalance = showCoins ? (coinInfo?.balance ?? 0) + (parseFloat(balanceOffset) || 0) : 0;
    const maxRedeemable = showCoins ? maxRedeemableCoins(effectiveBalance, activeSettings, netBeforeCoin) : 0;
    const showRedeemField = showCoins ? canRedeemCoins(effectiveBalance, activeSettings, netBeforeCoin) : false;
    const redeemed = showRedeemField
        ? Math.min(Math.max(0, parseFloat(coinsRedeemed) || 0), maxRedeemable)
        : 0;
    const coinDiscount = showCoins ? computeCoinDiscount(redeemed, activeSettings, netBeforeCoin) : 0;
    const preview = showCoins
        ? previewCoinSale({
              balance: effectiveBalance,
              coinsToRedeem: redeemed,
              netBeforeCoin,
              earnBase,
              settings: activeSettings,
              isWalkIn: false,
          })
        : { coinsEarned: 0, remainingBalance: 0 };

    const displayAvailable = Math.max(0, effectiveBalance - redeemed);

    const handleRedeemChange = (rawValue) => {
        if (rawValue === '') {
            onCoinsRedeemedChange('');

            return;
        }

        const parsed = parseFloat(rawValue);

        if (Number.isNaN(parsed)) {
            onCoinsRedeemedChange('0');

            return;
        }

        const clamped = Math.min(Math.max(0, parsed), maxRedeemable);
        onCoinsRedeemedChange(String(clamped));
    };

    useEffect(() => {
        if (!showRedeemField) {
            return;
        }

        const current = parseFloat(coinsRedeemed);

        if (!Number.isNaN(current) && current > maxRedeemable) {
            onCoinsRedeemedChange(String(maxRedeemable));
        }
    }, [showRedeemField, maxRedeemable, coinsRedeemed, onCoinsRedeemedChange]);

    useEffect(() => {
        if (showCoins && !showRedeemField && parseFloat(coinsRedeemed) > 0) {
            onCoinsRedeemedChange('0');
        }
    }, [showCoins, showRedeemField, coinsRedeemed, onCoinsRedeemedChange]);

    if (!showCoins) {
        return null;
    }

    if (!showRedeemField && preview.coinsEarned <= 0) {
        return null;
    }

    return (
        <div className="space-y-1.5 border border-violet-200 bg-violet-50/70 px-1.5 py-1.5 lg:px-2 lg:py-2">
            <div className="flex items-center gap-1.5 text-violet-900">
                <Coins className="size-3.5" />
                <span className="text-[10px] font-semibold uppercase tracking-wide lg:text-[11px]">Coins</span>
                {coinInfoLoading && <span className="text-[10px] text-violet-700/70">Loading...</span>}
            </div>

            <div className="flex items-center justify-between gap-2 text-[11px] lg:text-xs">
                <span className="text-violet-800">Available</span>
                <span className="font-semibold tabular-nums text-violet-950">{displayAvailable.toFixed(2)}</span>
            </div>

            {showRedeemField && (
                <div>
                    <Label className="mb-0.5 block text-[9px] text-muted-foreground lg:text-[10px]">
                        Redeem coins (max {maxRedeemable.toFixed(0)})
                    </Label>
                    <Input
                        type="number"
                        min="0"
                        max={maxRedeemable}
                        step="1"
                        value={coinsRedeemed}
                        onChange={(e) => handleRedeemChange(e.target.value)}
                        onBlur={() => {
                            if (coinsRedeemed === '') {
                                onCoinsRedeemedChange('0');
                            }
                        }}
                        className={inputClassName}
                    />
                    {error && <p className="mt-0.5 text-[10px] text-destructive">{error}</p>}
                </div>
            )}

            {showRedeemField && redeemed > 0 && (
                <div className="flex items-center justify-between gap-2 text-[11px] lg:text-xs">
                    <span className="text-violet-800">Coin discount</span>
                    <span className="font-medium tabular-nums text-green-700">-৳{coinDiscount.toFixed(2)}</span>
                </div>
            )}

            {preview.coinsEarned > 0 && (
                <div className="flex items-center justify-between gap-2 text-[11px] lg:text-xs">
                    <span className="text-violet-800">Coins to earn</span>
                    <span className="font-medium tabular-nums text-violet-950">+{preview.coinsEarned.toFixed(2)}</span>
                </div>
            )}

            {preview.coinsEarned > 0 && (
                <div className="flex items-center justify-between gap-2 border-t border-violet-200/80 pt-1 text-[11px] lg:text-xs">
                    <span className="text-violet-800">Balance after sale</span>
                    <span className="font-semibold tabular-nums text-violet-950">{preview.remainingBalance.toFixed(2)}</span>
                </div>
            )}
        </div>
    );
}

export { computeCoinDiscount, computeCoinsEarned, maxRedeemableCoins, previewCoinSale };
