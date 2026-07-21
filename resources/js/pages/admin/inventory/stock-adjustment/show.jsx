import DocShow from '../_shared/doc-show';

export default function StockAdjustmentShow({ adjustment }) {
    const type = typeof adjustment.type === 'object' ? adjustment.type?.value ?? adjustment.type : adjustment.type;

    return (
        <DocShow
            title="Stock Adjustment"
            invoice={adjustment.invoice_number}
            backRoute="inventory.stock-adjustment.index"
            destroyRoute="inventory.stock-adjustment.destroy"
            deletePermission="inventory.stock-adjustment.delete"
            id={adjustment.id}
            date={adjustment.date}
            comment={`${type === 'increase' ? 'Increase' : 'Decrease'}${adjustment.comment ? ` — ${adjustment.comment}` : ''}`}
            lines={adjustment.products?.map((p) => ({
                name: `${p.product?.name ?? ''}${p.variation?.variation_data?.label ? ` (${p.variation.variation_data.label})` : ''}`,
                code: p.product?.code,
                qty: p.quantity,
                price: null,
            }))}
        />
    );
}
