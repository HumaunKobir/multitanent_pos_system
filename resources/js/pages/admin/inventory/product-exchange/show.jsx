import { formatProductLabel } from '@/components/inventory/inventory-form';

import DocShow from '../_shared/doc-show';

export default function ProductExchangeShow({ exchange }) {
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
                <p className="mt-1">
                    Customer: {exchange.customer?.name ?? '—'} · Sale #{exchange.sell_id} · Diff ৳
                    {parseFloat(exchange.price_difference).toFixed(2)}
                    {parseFloat(exchange.special_discount_amount || 0) > 0 && (
                        <>
                            {' '}
                            · Special discount: -৳{parseFloat(exchange.special_discount_amount).toFixed(2)}
                            {exchange.special_discount?.name ? ` (${exchange.special_discount.name})` : ''}
                        </>
                    )}
                </p>
            }
            lines={exchange.products?.map((p) => ({
                name: `${formatProductLabel(p.old_product?.name, p.old_product?.code)} → ${formatProductLabel(p.new_product?.name, p.new_product?.code)}`,
                qty: p.new_quantity,
                price: p.new_unit_price,
            }))}
        />
    );
}
