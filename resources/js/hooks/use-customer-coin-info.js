import { isCoinSystemActive } from '@/lib/pos-coin';
import { route } from '@/lib/route';
import { useEffect, useRef, useState } from 'react';

function shouldFetchCustomerCoins(customerId, walkInCustomerId) {
    return Boolean(customerId) && String(customerId) !== String(walkInCustomerId ?? '');
}

function hasProvidedBalance(initialBalance) {
    return initialBalance !== null && initialBalance !== undefined && initialBalance !== '';
}

/**
 * @param {object} params
 * @param {string|number|null|undefined} params.customerId
 * @param {string|number|null|undefined} params.walkInCustomerId
 * @param {object|null|undefined} params.coinSettings
 * @param {number|string|null|undefined} [params.initialBalance]
 * @param {boolean} [params.initialIsDefault]
 * @param {() => void} [params.onCustomerChange]
 */
export function useCustomerCoinInfo({
    customerId,
    walkInCustomerId,
    coinSettings,
    initialBalance,
    initialIsDefault = false,
    onCustomerChange,
}) {
    const needsFetch = shouldFetchCustomerCoins(customerId, walkInCustomerId);
    const seedBalance = hasProvidedBalance(initialBalance);

    const [coinInfo, setCoinInfo] = useState({
        balance: seedBalance ? parseFloat(initialBalance) || 0 : 0,
        is_default: initialIsDefault,
        settings: coinSettings ?? { enabled: false },
    });
    const [loading, setLoading] = useState(needsFetch && !seedBalance);
    const previousCustomerIdRef = useRef(undefined);
    const onCustomerChangeRef = useRef(onCustomerChange);
    const coinSettingsRef = useRef(coinSettings);

    onCustomerChangeRef.current = onCustomerChange;
    coinSettingsRef.current = coinSettings;

    const isWalkIn =
        !customerId ||
        String(customerId) === String(walkInCustomerId ?? '') ||
        coinInfo.is_default;
    const activeSettings = coinInfo.settings?.enabled ? coinInfo.settings : coinSettings;
    const canShowCoins = isCoinSystemActive(activeSettings) && !isWalkIn && Boolean(customerId);

    useEffect(() => {
        const customerChanged =
            previousCustomerIdRef.current !== undefined && previousCustomerIdRef.current !== customerId;
        previousCustomerIdRef.current = customerId;

        if (!needsFetch) {
            setCoinInfo({ balance: 0, is_default: true, settings: coinSettingsRef.current ?? { enabled: false } });
            setLoading(false);

            if (customerChanged) {
                onCustomerChangeRef.current?.();
            }

            return;
        }

        let cancelled = false;
        const showLoadingState = !seedBalance;

        async function fetchCoins() {
            if (showLoadingState) {
                setLoading(true);
            }

            try {
                const res = await fetch(route('api.customers.coins', { customer: customerId }), {
                    credentials: 'include',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (cancelled) {
                    return;
                }

                if (!res.ok) {
                    return;
                }

                const payload = await res.json();

                setCoinInfo({
                    balance: payload.balance ?? 0,
                    is_default: Boolean(payload.is_default),
                    settings: payload.settings ?? coinSettingsRef.current,
                });

                if (customerChanged) {
                    onCustomerChangeRef.current?.();
                }
            } catch {
                if (!cancelled && showLoadingState) {
                    setCoinInfo({
                        balance: 0,
                        is_default: false,
                        settings: coinSettingsRef.current ?? { enabled: false },
                    });
                }
            } finally {
                if (!cancelled && showLoadingState) {
                    setLoading(false);
                }
            }
        }

        fetchCoins();

        return () => {
            cancelled = true;
        };
    }, [customerId, walkInCustomerId, needsFetch, seedBalance]);

    return {
        coinInfo,
        loading,
        isWalkIn,
        activeSettings,
        canShowCoins,
    };
}
