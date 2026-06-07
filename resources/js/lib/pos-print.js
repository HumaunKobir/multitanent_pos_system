function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatMoney(value) {
    return parseFloat(value ?? 0).toFixed(2);
}

function formatQty(value) {
    const qty = parseFloat(value ?? 0);
    return Number.isInteger(qty) ? String(qty) : qty.toFixed(2);
}

/**
 * Open a 58mm thermal receipt in a popup and trigger the browser print dialog.
 *
 * @param {object} options
 * @param {string} options.companyName
 * @param {string|null|undefined} options.logoUrl
 * @param {string|null|undefined} options.branchName
 * @param {string} options.invoiceNumber
 * @param {string} options.date
 * @param {{ name?: string, phone?: string, address?: string }|null|undefined} options.customer
 * @param {Array<{ name: string, variant?: string, rate: number|string, qty: number|string, amount: number|string }>} options.products
 * @param {{ gross: number|string, vat: number|string, discount: number|string, net: number|string, paid: number|string, due: number|string, change?: number|string }} options.totals
 * @param {string} [options.footer]
 */
export function posPrint({
    companyName,
    logoUrl,
    branchName,
    invoiceNumber,
    date,
    customer,
    products,
    totals,
    footer = 'Powered by Coolness Point',
}) {
    try {
        const customerName = customer?.name?.trim() || 'Walk-in Customer';
        const customerPhone = customer?.phone?.trim() || '';
        const customerAddress = customer?.address?.trim() || '';

        const productRows = products
            .map(
                (product) => `
            <tr class="service">
                <td>${escapeHtml(product.name)}</td>
                <td>${escapeHtml(product.variant || '')}</td>
                <td style="text-align:right">${formatMoney(product.rate)}</td>
                <td style="text-align:right">${formatQty(product.qty)}</td>
                <td style="text-align:right">${formatMoney(product.amount)}</td>
            </tr>`,
            )
            .join('');

        const logoBlock = logoUrl
            ? `<img src="${escapeHtml(logoUrl)}" alt="${escapeHtml(companyName)}" style="max-height:48px;max-width:200px;margin:0 auto 6px;display:block;" />`
            : '';

        const changeAmount = parseFloat(totals.change ?? Math.max(0, parseFloat(totals.paid) - parseFloat(totals.net)));

        const html = `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>${escapeHtml(invoiceNumber)}</title>
    <style>
        @media print {
            body { font-family: Arial, sans-serif; font-size: 12px; width: 58mm; margin: 0; }
            .pos-receipt { width: 100%; text-align: center; padding: 0 10px; box-sizing: border-box; }
            table { width: 100%; border-collapse: collapse; }
        }
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; }
        .pos-receipt { width: 270px; margin: 0 auto; text-align: center; padding: 10px; box-sizing: border-box; }
        .company-name { font-size: 1.2rem; font-weight: bold; margin: 0; }
        .branch-name { font-size: 12px; margin: 2px 0 8px; }
        .tabletitle { font-size: 10px; font-weight: bold; border: 1px solid #000; }
        .tabletitle td { border: 1px solid #000; padding: 2px; }
        .tableitem2 { font-size: 8px !important; text-align: left; vertical-align: top; }
        .tableitem2 td { border: 1px solid #000; padding: 2px 4px; }
        .service td { font-size: 9px; border: 1px solid #000; padding: 2px; }
        .totals td { font-size: 9px; border: 1px solid #000; padding: 2px 4px; text-align: right; }
        .footer { font-size: 8px; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="pos-receipt">
        ${logoBlock}
        <p class="company-name">${escapeHtml(companyName)}</p>
        ${branchName ? `<p class="branch-name">${escapeHtml(branchName)}</p>` : ''}
        <table style="width:100%;">
            <tr class="tableitem2">
                <td colspan="2">
                    Name: ${escapeHtml(customerName)}<br />
                    ${customerPhone ? `Phone: ${escapeHtml(customerPhone)}<br />` : ''}
                    ${customerAddress ? `Address: ${escapeHtml(customerAddress)}` : ''}
                </td>
                <td colspan="3" style="text-align:right;">
                    Inv: ${escapeHtml(invoiceNumber)}<br />
                    Date: ${escapeHtml(date)}
                </td>
            </tr>
            <tr class="tabletitle">
                <td>Product</td>
                <td>Variant</td>
                <td style="text-align:right">Rate</td>
                <td style="text-align:right">Qty</td>
                <td style="text-align:right">Amount</td>
            </tr>
            ${productRows}
            <tr class="totals">
                <td colspan="4">Subtotal</td>
                <td>${formatMoney(totals.gross)}</td>
            </tr>
            <tr class="totals">
                <td colspan="4">Vat</td>
                <td>${formatMoney(totals.vat)}</td>
            </tr>
            <tr class="totals">
                <td colspan="4">Discount</td>
                <td>${formatMoney(totals.discount)}</td>
            </tr>
            <tr class="totals">
                <td colspan="4">Payable Amount</td>
                <td>${formatMoney(totals.net)}</td>
            </tr>
            <tr class="totals">
                <td colspan="4">Total Paid</td>
                <td>${formatMoney(totals.paid)}</td>
            </tr>
            <tr class="totals">
                <td colspan="4">Due</td>
                <td>${formatMoney(totals.due)}</td>
            </tr>
            ${
                changeAmount > 0
                    ? `<tr class="totals">
                <td colspan="4">Change Amount</td>
                <td>${formatMoney(changeAmount)}</td>
            </tr>`
                    : ''
            }
        </table>
        <p class="footer">${escapeHtml(footer)}</p>
    </div>
</body>
</html>`;

        const printWindow = window.open('', '_blank', 'width=300,height=600');

        if (!printWindow) {
            alert('Please allow popups to print the POS receipt.');
            return;
        }

        printWindow.document.write(html);
        printWindow.document.close();

        setTimeout(() => {
            printWindow.print();
            setTimeout(() => printWindow.close(), 1000);
        }, 500);
    } catch (error) {
        console.error('POS print failed:', error);
        alert('Failed to print POS receipt.');
    }
}

export function buildSellPosPrintPayload(sell, { companyName, logoUrl, branchName }) {
    const gross = parseFloat(sell.gross_amount ?? 0);
    const vat = parseFloat(sell.vat ?? 0);
    const discount = parseFloat(sell.discount ?? 0);
    const lineDiscount = (sell.products ?? []).reduce((sum, item) => sum + parseFloat(item.discount ?? 0), 0);
    const net = gross + vat - discount - lineDiscount;
    const paid = parseFloat(sell.paid_amount ?? 0);
    const due = Math.max(0, net - paid);

    return {
        companyName: companyName || 'Coolness Point',
        logoUrl,
        branchName: branchName || sell.branch?.name || '',
        invoiceNumber: sell.invoice_number ?? `INVS${String(sell.id).padStart(8, '0')}`,
        date: sell.date ?? '',
        customer: sell.customer ?? null,
        products: (sell.products ?? []).map((item) => {
            const qty = parseFloat(item.quantity ?? 0);
            const rate = parseFloat(item.unit_price ?? 0);
            const lineItemDiscount = parseFloat(item.discount ?? 0);

            return {
                name: item.product?.name ?? '—',
                variant: item.variation?.variation_data?.label ?? item.variation?.sku_code ?? '',
                rate,
                qty,
                amount: rate * qty - lineItemDiscount,
            };
        }),
        totals: {
            gross,
            vat,
            discount: discount + lineDiscount,
            net,
            paid,
            due,
        },
    };
}
