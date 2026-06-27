import DocShow from '../_shared/doc-show';

export default function PurchaseReturnShow({ purchaseReturn }) {
    const gross = parseFloat(purchaseReturn.gross_amount || 0);
    const discount = parseFloat(purchaseReturn.discount || 0);
    const vat = parseFloat(purchaseReturn.vat || 0);
    const vatPercent = parseFloat(purchaseReturn.vat_percent || 0);
    const net = parseFloat(purchaseReturn.net_amount || 0);
    const paid = parseFloat(purchaseReturn.paid_amount || 0);
    const due = parseFloat(purchaseReturn.due_amount || 0);

    return (
        <DocShow
            title="Purchase Return"
            invoice={purchaseReturn.invoice_number}
            backRoute="inventory.purchase-return.index"
            editRoute="inventory.purchase-return.edit"
            destroyRoute="inventory.purchase-return.destroy"
            updatePermission="inventory.purchase-return.update"
            deletePermission="inventory.purchase-return.delete"
            id={purchaseReturn.id}
            date={purchaseReturn.date}
            comment={purchaseReturn.comment}
            extra={
                <div className="mt-1 space-y-1 text-xs">
                    <p>Supplier: {purchaseReturn.supplier?.name} · Purchase #{purchaseReturn.purchase_id}</p>
                    <div className="flex flex-wrap gap-x-3 text-muted-foreground">
                        <span>Gross ৳{gross.toFixed(2)}</span>
                        {discount > 0.009 && <span>Discount -৳{discount.toFixed(2)}</span>}
                        {vat > 0.009 && (
                            <span>
                                VAT {vatPercent > 0.009 ? `(${vatPercent.toFixed(2)}%)` : ''} +৳{vat.toFixed(2)}
                            </span>
                        )}
                        <span>Net ৳{net.toFixed(2)}</span>
                        <span>Paid ৳{paid.toFixed(2)}</span>
                        <span>Due ৳{due.toFixed(2)}</span>
                    </div>
                </div>
            }
            lines={purchaseReturn.products?.map((p) => ({
                name: p.product?.name,
                code: p.product?.code,
                qty: p.quantity,
                price: p.unit_price,
            }))}
        />
    );
}
