import { formatProductLabel } from '@/components/inventory/inventory-form';

import DocShow from '../_shared/doc-show';

export default function ProductExchangeShow({ exchange, sellDiscounts = null }) {
    const invoiceDiscount = parseFloat(exchange.discount || 0);
    const vat = parseFloat(exchange.vat || 0);
    const roundOff = parseFloat(exchange.round_off_amount || 0);
    const specialDiscount = parseFloat(exchange.special_discount_amount || 0);
    const promotionDiscount = parseFloat(exchange.promotion_discount_total || 0);
    const coinDiscount = parseFloat(exchange.coin_discount_amount || 0);
    const coinsRedeemed = parseFloat(exchange.coins_redeemed || 0);
    const coinsEarned = parseFloat(exchange.coins_earned || 0);
    const grossAmount = parseFloat(exchange.gross_amount || 0);
    const netAmount = parseFloat(exchange.net_amount || 0);

    return (
        <DocShow
            title="Product Exchange"
            invoice={exchange.invoice_number}
            backRoute="inventory.product-exchange.index"
            editRoute="inventory.product-exchange.edit"
            destroyRoute="inventory.product-exchange.destroy"
            updatePermission="inventory.product-exchange.update"
            deletePermission="inventory.product-exchange.delete"
            id={exchange.id}
            date={exchange.date}
            comment={exchange.comment}
            extra={
                <div className="mt-1 space-y-1">
                    <p>
                        Customer: {exchange.customer?.name ?? '—'} · Sale #
                        {exchange.sell_id}
                    </p>
                    <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-xs">
                        <span>Gross: ৳{grossAmount.toFixed(2)}</span>
                        {invoiceDiscount > 0 && (
                            <span className="text-green-700">
                                Invoice discount: -৳{invoiceDiscount.toFixed(2)}
                            </span>
                        )}
                        {specialDiscount > 0 && (
                            <span className="text-amber-800">
                                Special discount: -৳{specialDiscount.toFixed(2)}
                                {exchange.special_discount?.name
                                    ? ` (${exchange.special_discount.name})`
                                    : ''}
                            </span>
                        )}
                        {promotionDiscount > 0 && (
                            <span className="text-purple-700 dark:text-purple-400">
                                Promotion discount: -৳{promotionDiscount.toFixed(2)}
                            </span>
                        )}
                        {coinDiscount > 0 && (
                            <span>
                                Coin discount: -৳{coinDiscount.toFixed(2)} ({coinsRedeemed} coins redeemed)
                            </span>
                        )}
                        {vat > 0 && <span>VAT: +৳{vat.toFixed(2)}</span>}
                        {roundOff > 0 && (
                            <span className="text-green-700">
                                Round off: -৳{roundOff.toFixed(2)}
                            </span>
                        )}
                        <span className="font-semibold">
                            Net: ৳{netAmount.toFixed(2)}
                        </span>
                        {coinsEarned > 0 && (
                            <span className="text-blue-700 dark:text-blue-400">
                                Coins earned: +{coinsEarned}
                            </span>
                        )}
                        <span>
                            Diff:{' '}
                            <strong
                                className={
                                    parseFloat(exchange.price_difference) >= 0
                                        ? 'text-primary'
                                        : 'text-destructive'
                                }
                            >
                                ৳{parseFloat(exchange.price_difference).toFixed(2)}
                            </strong>
                        </span>
                    </div>
                </div>
            }
            lines={exchange.products?.map((p) => ({
                name: `${formatProductLabel(p.old_product?.name, p.old_product?.code)} → ${formatProductLabel(p.new_product?.name, p.new_product?.code)}`,
                qty: p.new_quantity,
                price: p.new_unit_price,
            }))}
        />
    );
}
