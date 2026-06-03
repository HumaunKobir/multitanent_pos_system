import DocShow from '../_shared/doc-show';

export default function PurchaseReturnShow({ purchaseReturn }) {
    return (
        <DocShow
            title="Purchase Return"
            invoice={purchaseReturn.invoice_number}
            backRoute="inventory.purchase-return.index"
            editRoute="inventory.purchase-return.edit"
            destroyRoute="inventory.purchase-return.destroy"
            id={purchaseReturn.id}
            date={purchaseReturn.date}
            comment={purchaseReturn.comment}
            extra={<p className="mt-1">Supplier: {purchaseReturn.supplier?.name} · Purchase #{purchaseReturn.purchase_id} · Total ৳{parseFloat(purchaseReturn.gross_amount).toFixed(2)}</p>}
            lines={purchaseReturn.products?.map((p) => ({
                name: p.product?.name,
                qty: p.quantity,
                price: p.unit_price,
            }))}
        />
    );
}
