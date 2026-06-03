import DocShow from '../_shared/doc-show';

export default function ProductExchangeShow({ exchange }) {
    return (
        <DocShow
            title="Product Exchange"
            invoice={exchange.invoice_number}
            backRoute="inventory.product-exchange.index"
            editRoute="inventory.product-exchange.edit"
            destroyRoute="inventory.product-exchange.destroy"
            id={exchange.id}
            date={exchange.date}
            comment={exchange.comment}
            extra={<p className="mt-1">Customer: {exchange.customer?.name ?? '—'} · Sale #{exchange.sell_id} · Diff ৳{parseFloat(exchange.price_difference).toFixed(2)}</p>}
            lines={exchange.products?.map((p) => ({
                name: `${p.old_product?.name} → ${p.new_product?.name}`,
                qty: p.new_quantity,
                price: p.new_unit_price,
            }))}
        />
    );
}
