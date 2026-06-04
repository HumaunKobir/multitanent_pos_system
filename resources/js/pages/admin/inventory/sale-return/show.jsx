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
            extra={<p className="mt-1">Customer: {saleReturn.customer?.name ?? '—'} · Sale #{saleReturn.sell_id} · Total ৳{parseFloat(saleReturn.gross_amount).toFixed(2)}</p>}
            lines={saleReturn.products?.map((p) => ({
                name: p.product?.name,
                qty: p.quantity,
                price: p.unit_price,
            }))}
        />
    );
}
