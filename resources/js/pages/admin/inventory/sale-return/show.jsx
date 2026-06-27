import DocShow from '../_shared/doc-show';

export default function SaleReturnShow({ saleReturn }) {
    return (
        <DocShow
            title="Sale Return"
            invoice={saleReturn.invoice_number}
            backRoute="inventory.sale-return.index"
            editRoute="inventory.sale-return.edit"
            destroyRoute="inventory.sale-return.destroy"
            updatePermission="inventory.sale-return.update"
            deletePermission="inventory.sale-return.delete"
            id={saleReturn.id}
            date={saleReturn.date}
            comment={saleReturn.comment}
            extra={
                <p className="mt-1">
                    Customer: {saleReturn.customer?.name ?? '—'} · Sale #{saleReturn.sell_id} · Gross ৳
                    {parseFloat(saleReturn.gross_amount).toFixed(2)}
                    {parseFloat(saleReturn.discount_amount ?? 0) > 0
                        ? ` · Discount ৳${parseFloat(saleReturn.discount_amount).toFixed(2)}`
                        : ''}
                    {parseFloat(saleReturn.vat_amount ?? 0) > 0
                        ? ` · VAT ৳${parseFloat(saleReturn.vat_amount).toFixed(2)}`
                        : ''}{' '}
                    · Net ৳{parseFloat(saleReturn.net_amount ?? saleReturn.gross_amount).toFixed(2)} · Refund ৳
                    {parseFloat(saleReturn.paid_amount).toFixed(2)}
                </p>
            }
            lines={saleReturn.products?.map((p) => ({
                name: p.product?.name,
                code: p.product?.code,
                qty: p.quantity,
                price: p.unit_price,
            }))}
        />
    );
}
