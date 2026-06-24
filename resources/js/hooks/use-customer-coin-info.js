import { isCoinSystemActive } from '@/lib/pos-coin';
import { route } from '@/lib/route';
import { useEffect, useRef, useState } from 'react';

/**
 * @param {object} params
 * @param {string|number|null|undefined} params.customerId
 * @param {string|number|null|undefined} params.walkInCustomerId
 * @param {object|null|undefined} params.coinSettings
 * @param {() => void} [params.onCustomerChange]
 */
export function useCustomerCoinInfo({ customerId, walkInCustomerId, coinSettings, onCustomerChange }) {
    const [coinInfo, setCoinInfo] = useState({
        balance: 0,
        is_default: false,
        settings: coinSettings ?? { enabled: false },
    });
    const [loading, setLoading] = useState(false);
    const previousCustomerIdRef = useRef(undefined);

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

        if (!customerId || String(customerId) === String(walkInCustomerId ?? '')) {
            setCoinInfo({ balance: 0, is_default: true, settings: coinSettings ?? { enabled: false } });

            if (customerChanged) {
                onCustomerChange?.();
            }

            return;
        }

        let cancelled = false;

        async function fetchCoins() {
            setLoading(true);

            try {
                const res = await fetch(route('api.customers.coins', { customer: customerId }), {
                    credentials: 'include',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!res.ok || cancelled) {
                    return;
                }

                const payload = await res.json();

                if (!cancelled) {
                    setCoinInfo({
                        balance: payload.balance ?? 0,
                        is_default: Boolean(payload.is_default),
                        settings: payload.settings ?? coinSettings,
                    });

                    if (customerChanged) {
                        onCustomerChange?.();
                    }
                }
            } catch {
                if (!cancelled) {
                    setCoinInfo({ balance: 0, is_default: false, settings: coinSettings ?? { enabled: false } });
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        }

        fetchCoins();

        return () => {
            cancelled = true;
        };
    }, [customerId, walkInCustomerId, coinSettings]);

    return {
        coinInfo,
        loading,
        isWalkIn,
        activeSettings,
        canShowCoins,
    };
}
