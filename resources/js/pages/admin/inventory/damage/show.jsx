import DocShow from '../_shared/doc-show';

export default function DamageShow({ damage }) {
    return (
        <DocShow
            title="Damage"
            invoice={damage.invoice_number}
            backRoute="inventory.damage.index"
            editRoute="inventory.damage.edit"
            destroyRoute="inventory.damage.destroy"
            updatePermission="inventory.damage.update"
            deletePermission="inventory.damage.delete"
            id={damage.id}
            date={damage.date}
            comment={damage.comment}
            lines={damage.products?.map((p) => ({
                name: `${p.product?.name ?? ''}${p.variation?.variation_data?.label ? ` (${p.variation.variation_data.label})` : ''}`,
                qty: p.quantity,
                price: null,
            }))}
        />
    );
}
