const POS_PRINT_STYLES = `
@media print {
    @page {
        size: 80mm 200mm;
        margin: 0;
    }
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color: #000 !important;
    }
}

body {
    font-family: 'Courier New', monospace;
    font-size: 11px;
    line-height: 1.25;
    margin: 0;
    padding: 5px;
    color: #000;
    background: #fff;
}

.pos-container {
    width: 100%;
    max-width: 80mm;
    margin: 0 auto;
}

.pos-header {
    text-align: center;
    border-bottom: 1px dashed #000;
    padding-bottom: 10px;
    margin-bottom: 10px;
}

.pos-title {
    font-size: 15px;
    font-weight: bold;
    margin-bottom: 5px;
    color: #000;
}

.pos-logo-wrap {
    margin-bottom: 6px;
}

.pos-logo {
    max-height: 44px;
    max-width: 120px;
    object-fit: contain;
}

.pos-subtitle {
    font-size: 9px;
    margin-bottom: 5px;
    color: #000;
    font-weight: 500;
}

.pos-info {
    font-size: 9px;
    margin-bottom: 10px;
    color: #000;
    font-weight: 500;
}

.pos-customer {
    border-bottom: 1px dashed #000;
    padding-bottom: 10px;
    margin-bottom: 10px;
}

.pos-customer-line {
    color: #000;
    font-weight: 500;
    font-size: 11px;
}

.pos-items {
    margin-bottom: 10px;
}

.pos-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 3px;
    font-size: 10px;
    color: #000;
}

.pos-item-name {
    flex: 1;
    margin-right: 5px;
}

.pos-item-qty {
    width: 24px;
    text-align: center;
}

.pos-item-price {
    width: 58px;
    text-align: right;
}

.pos-item-meta {
    margin: -1px 0 2px 0;
    font-size: 9px;
    padding-left: 2px;
    color: #000;
    font-weight: 500;
}

.pos-totals {
    border-top: 1px dashed #000;
    padding-top: 10px;
    margin-top: 10px;
}

.pos-total-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 3px;
}

.pos-total-label {
    font-weight: bold;
    font-size: 12px;
    color: #000;
}

.pos-total-value {
    font-weight: bold;
    font-size: 12px;
    color: #000;
}

.pos-footer {
    text-align: center;
    margin-top: 15px;
    font-size: 9px;
    border-top: 1px dashed #000;
    padding-top: 10px;
    color: #000;
}

.pos-thank-you {
    font-weight: bold;
    font-size: 12px;
    margin-bottom: 5px;
    color: #000;
}

.pos-divider {
    border-top: 1px dashed #000;
    margin: 10px 0;
}

.pos-center {
    text-align: center;
}

.pos-right {
    text-align: right;
}

.pos-bold {
    font-weight: bold;
    font-size: 12px;
    color: #000;
}

.pos-small {
    font-size: 9px;
    color: #000;
    font-weight: 500;
}

.pos-tiny {
    font-size: 8px;
    color: #000;
    font-weight: 500;
}

.pos-note {
    margin-bottom: 10px;
    padding: 6px 4px;
    border: 1px dashed #000;
    font-size: 10px;
}

.pos-terms {
    margin-top: 10px;
    margin-bottom: 10px;
    padding: 6px 4px;
    border-top: 1px dashed #000;
    font-size: 9px;
    line-height: 1.3;
    text-align: left;
}

.pos-terms-title {
    font-weight: bold;
    font-size: 10px;
    margin-bottom: 4px;
    text-align: center;
}

.pos-terms p {
    margin: 0 0 4px 0;
}

.pos-terms ul,
.pos-terms ol {
    margin: 0 0 4px 14px;
    padding: 0;
}
`;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatMoneyTk(value) {
    const amount = parseFloat(value ?? 0);
    return `${amount.toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} TK`;
}

function formatQty(value) {
    const qty = parseFloat(value ?? 0);
    return Number.isInteger(qty) ? String(qty) : qty.toFixed(2);
}

function truncateName(name, max = 28) {
    const text = String(name ?? '').trim();
    if (text.length <= max) {
        return text;
    }

    return `${text.slice(0, max - 1)}…`;
}

const BD_TIMEZONE = 'Asia/Dhaka';

function formatReceiptDate(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: BD_TIMEZONE,
    });
}

function formatReceiptTime(value) {
    const date = value ? new Date(value) : new Date();

    if (Number.isNaN(date.getTime())) {
        return new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: BD_TIMEZONE });
    }

    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: BD_TIMEZONE });
}

function formatPrintedAt() {
    const now = new Date();

    return `${now.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: BD_TIMEZONE,
    })} ${now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: BD_TIMEZONE })}`;
}

function groupItemsByVariant(items) {
    const groups = new Map();

    for (const item of items) {
        const key = item.variant_id ?? item.variation_id ?? `product-${item.product_id ?? item.id}`;
        const existing = groups.get(key);

        if (existing) {
            existing.quantity += parseFloat(item.quantity ?? 0);
            existing.amount += parseFloat(item.amount ?? 0);
            continue;
        }

        groups.set(key, {
            ...item,
            quantity: parseFloat(item.quantity ?? 0),
            amount: parseFloat(item.amount ?? 0),
        });
    }

    return Array.from(groups.values());
}

function buildItemName(item) {
    const baseName = truncateName(item.product?.name ?? item.name ?? '—');
    const variantLabel = item.variant?.name ?? item.variant_label ?? item.variation?.variation_data?.label ?? '';
    const variantSku = item.variant?.sku ?? item.variant_sku ?? item.product?.code ?? item.code ?? '';

    if (variantLabel || variantSku) {
        const variantText = variantSku || variantLabel;
        return `${baseName} (Variant: ${variantText})`;
    }

    return baseName;
}

function totalRow(label, value) {
    return `
        <div class="pos-total-row">
            <div class="pos-total-label">${escapeHtml(label)}:</div>
            <div class="pos-total-value">${formatMoneyTk(value)}</div>
        </div>`;
}

function formatPaymentAccountLabel(line) {
    const account = line.payment_account ?? line.paymentAccount;
    const code = account?.code ?? '';
    const name = account?.name ?? '';

    if (code && name) {
        return `${code} — ${name}`;
    }

    return code || name || 'Account';
}

function paymentBreakdownRows(payments) {
    return (payments ?? [])
        .filter((line) => parseFloat(line.amount ?? 0) > 0)
        .map(
            (line) => `
        <div class="pos-total-row pos-small">
            <div>${escapeHtml(formatPaymentAccountLabel(line))}</div>
            <div class="pos-total-value">${formatMoneyTk(line.amount)}</div>
        </div>`,
        )
        .join('');
}

export function hasRichTextContent(html) {
    if (!html) {
        return false;
    }

    return html.replace(/<[^>]*>/g, '').trim().length > 0;
}

/**
 * Transform a sell record into POS print data (similar to buildPosOrderData).
 */
export function buildSellPosPrintPayload(sell, options = {}) {
    const gross = parseFloat(sell.gross_amount ?? 0);
    const vat = parseFloat(sell.vat ?? 0);
    const invoiceDiscount = parseFloat(sell.discount ?? 0);
    const specialDiscount = parseFloat(sell.special_discount_amount ?? 0);
    const lineDiscount = (sell.products ?? []).reduce((sum, item) => sum + parseFloat(item.discount ?? 0), 0);
    const net = gross + vat - invoiceDiscount - specialDiscount - lineDiscount;
    const paid = parseFloat(sell.paid_amount ?? 0);
    const due = Math.max(0, net - paid);
    const change = parseFloat(options.change ?? 0);

    const lineItems = (sell.products ?? []).map((item) => {
        const qty = parseFloat(item.quantity ?? 0);
        const unitPrice = parseFloat(item.unit_price ?? item.sell_price ?? item.price ?? 0);
        const lineItemDiscount = parseFloat(item.discount ?? 0);
        const variantLabel = item.variation?.variation_data?.label ?? item.variation?.sku_code ?? '';

        return {
            id: item.id,
            product_id: item.product_id,
            variant_id: item.variation_id ?? null,
            quantity: qty,
            unit_price: unitPrice,
            sell_price: unitPrice,
            price: unitPrice * qty - lineItemDiscount,
            amount: unitPrice * qty - lineItemDiscount,
            line_discount: lineItemDiscount,
            product: {
                name: item.product?.name ?? '—',
                code: item.product?.code ?? '',
            },
            variant: variantLabel
                ? {
                      name: variantLabel,
                      sku: item.variation?.sku_code ?? variantLabel,
                  }
                : null,
            code: item.product?.code ?? '',
            name: item.product?.name ?? '—',
            variant_label: variantLabel,
        };
    });

    const payments = (sell.payments ?? []).map((line) => ({
        id: line.id,
        payment_account_id: line.payment_account_id,
        amount: parseFloat(line.amount ?? 0),
        payment_account: line.payment_account ?? line.paymentAccount ?? null,
    }));

    return {
        options: {
            showHeader: options.showHeader ?? true,
            showFooter: options.showFooter ?? true,
            showCustomerInfo: options.showCustomerInfo ?? true,
            showItems: options.showItems ?? true,
            showTotals: options.showTotals ?? true,
            showThankYou: options.showThankYou ?? true,
            companyName: options.companyName || options.siteName || 'Coolness Point',
            companyAddress: options.companyAddress || options.contact?.address || sell.branch?.address || '',
            companyPhone: options.companyPhone || options.contact?.phone || sell.branch?.phone || '',
            companyEmail: options.companyEmail || options.contact?.email || '',
            companyWebsite: options.companyWebsite || '',
            companyLogo: options.logoUrl || options.companyLogo || '',
            branchName: options.branchName || sell.branch?.name || '',
            termsAndConditions:
                options.termsAndConditions ?? sell.branch?.pos_terms_and_conditions ?? '',
        },
        order: {
            id: sell.id,
            invoice_no: sell.invoice_number ?? `INVS${String(sell.id).padStart(8, '0')}`,
            date: sell.date ?? '',
            comment: sell.comment ?? '',
            total_price: net,
            paid,
            due,
            change,
            customer: {
                name: sell.customer?.name ?? 'Walk-in Customer',
                phone: sell.customer?.phone ?? '',
                address: sell.customer?.address ?? '',
            },
            orderproduct: lineItems,
            payments,
            totals: {
                gross,
                vat,
                invoiceDiscount,
                specialDiscount,
                specialDiscountName: sell.special_discount?.name ?? null,
                lineDiscount,
                discount: invoiceDiscount + specialDiscount + lineDiscount,
                net,
                paid,
                due,
                change,
            },
        },
    };
}

function renderPosInvoice(data) {
    const { options, order } = data;
    const totals = order.totals ?? {};
    const groupedItems = groupItemsByVariant(order.orderproduct ?? []);

    const headerBlock = options.showHeader
        ? `
        <div class="pos-header">
            ${options.companyLogo ? `<div class="pos-logo-wrap"><img class="pos-logo" src="${escapeHtml(options.companyLogo)}" alt="" onerror="this.parentElement.style.display='none'" /></div>` : ''}
            <div class="pos-title">${escapeHtml(options.companyName)}</div>
            ${options.branchName ? `<div class="pos-subtitle">${escapeHtml(options.branchName)}</div>` : ''}
            ${options.companyAddress ? `<div class="pos-subtitle">${escapeHtml(options.companyAddress)}</div>` : ''}
            ${options.companyPhone ? `<div class="pos-info">Tel: ${escapeHtml(options.companyPhone)}</div>` : ''}
            <div class="pos-divider"></div>
            <div class="pos-bold">INVOICE</div>
            <div class="pos-small">Date: ${escapeHtml(formatReceiptDate(order.date))} | Time: ${escapeHtml(formatReceiptTime(order.date))}</div>
            <div class="pos-small">Invoice No: ${escapeHtml(order.invoice_no)}</div>
        </div>`
        : '';

    const customerBlock =
        options.showCustomerInfo
            ? `
        <div class="pos-customer">
            <div class="pos-bold">Customer Details:</div>
            <div class="pos-customer-line">Name: ${escapeHtml(order.customer?.name || 'Walk-in Customer')}</div>
            ${order.customer?.phone ? `<div class="pos-customer-line">Phone: ${escapeHtml(order.customer.phone)}</div>` : ''}
            ${order.customer?.address ? `<div class="pos-customer-line">Address: ${escapeHtml(order.customer.address)}</div>` : ''}
        </div>`
            : '';

    const itemsBlock = options.showItems
        ? `
        <div class="pos-items">
            <div class="pos-item pos-bold">
                <div class="pos-item-name">Item</div>
                <div class="pos-item-qty">Qty</div>
                <div class="pos-item-price">Price</div>
            </div>
            <div class="pos-divider"></div>
            ${groupedItems
                .map((item) => {
                    const metaParts = [];
                    if (item.product?.code || item.code) {
                        metaParts.push(`Code: ${item.product?.code ?? item.code}`);
                    }
                    if (parseFloat(item.line_discount ?? 0) > 0) {
                        metaParts.push(`Disc: ${formatMoneyTk(item.line_discount)}`);
                    }

                    return `
                <div class="pos-item">
                    <div class="pos-item-name">${escapeHtml(buildItemName(item))}</div>
                    <div class="pos-item-qty">${formatQty(item.quantity)}</div>
                    <div class="pos-item-price">${formatMoneyTk(item.amount ?? item.price)}</div>
                </div>
                ${metaParts.length ? `<div class="pos-item-meta">${escapeHtml(metaParts.join(' | '))}</div>` : ''}`;
                })
                .join('')}
        </div>`
        : '';

    const paymentRows = paymentBreakdownRows(order.payments);
    const hasPaymentBreakdown = paymentRows.length > 0;

    const totalsRows = [
        totals.gross != null ? totalRow('Subtotal', totals.gross) : '',
        parseFloat(totals.lineDiscount ?? 0) > 0 ? totalRow('Line Discount', totals.lineDiscount) : '',
        parseFloat(totals.invoiceDiscount ?? 0) > 0 ? totalRow('Invoice Discount', totals.invoiceDiscount) : '',
        parseFloat(totals.specialDiscount ?? 0) > 0
            ? totalRow(totals.specialDiscountName ? `Special (${totals.specialDiscountName})` : 'Special Discount', totals.specialDiscount)
            : '',
        parseFloat(totals.vat ?? 0) > 0 ? totalRow('VAT', totals.vat) : '',
        totalRow('Total', totals.net ?? order.total_price),
        totalRow('Paid', totals.paid ?? order.paid),
        parseFloat(totals.due ?? order.due ?? 0) > 0 ? totalRow('Due', totals.due ?? order.due) : '',
        parseFloat(totals.change ?? order.change ?? 0) > 0 ? totalRow('Change', totals.change ?? order.change) : '',
    ]
        .filter(Boolean)
        .join('');

    const totalsBlock = options.showTotals
        ? `
        <div class="pos-totals">
            <div class="pos-divider"></div>
            ${totalsRows}
            ${
                hasPaymentBreakdown
                    ? `
            <div class="pos-divider"></div>
            <div class="pos-bold pos-small">Payment Accounts</div>
            ${paymentRows}`
                    : ''
            }
        </div>`
        : '';

    const noteBlock = order.comment
        ? `<div class="pos-note"><span class="pos-bold">Note:</span> ${escapeHtml(order.comment)}</div>`
        : '';

    const termsBlock =
        hasRichTextContent(options.termsAndConditions)
            ? `<div class="pos-terms"><div class="pos-terms-title">Terms & Conditions</div>${options.termsAndConditions}</div>`
            : '';

    const footerBlock = options.showFooter
        ? `
        <div class="pos-footer">
            ${options.showThankYou ? '<div class="pos-thank-you">Thank you for your business!</div>' : ''}
            <div class="pos-small">Please keep this receipt</div>
            <div class="pos-tiny">For any queries, contact us</div>
            ${options.companyPhone ? `<div class="pos-small">Tel: ${escapeHtml(options.companyPhone)}</div>` : ''}
            ${options.companyEmail ? `<div class="pos-small">Email: ${escapeHtml(options.companyEmail)}</div>` : ''}
            ${options.companyWebsite ? `<div class="pos-small">Web: ${escapeHtml(options.companyWebsite)}</div>` : ''}
            <div class="pos-divider"></div>
            <div class="pos-tiny">Printed on: ${escapeHtml(formatPrintedAt())}</div>
        </div>`
        : '';

    return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>${escapeHtml(order.invoice_no)}</title>
    <style>${POS_PRINT_STYLES}</style>
</head>
<body>
    <div class="pos-container">
        ${headerBlock}
        ${customerBlock}
        ${noteBlock}
        ${itemsBlock}
        ${totalsBlock}
        ${termsBlock}
        ${footerBlock}
    </div>
</body>
</html>`;
}

/**
 * Print POS invoice from buildSellPosPrintPayload() output or legacy flat payload.
 */
export function posPrint(payload) {
    try {
        const data = payload?.order ? payload : buildSellPosPrintPayload(payload?.sell ?? {}, payload?.options ?? payload);
        const html = renderPosInvoice(data);

        const printWindow = window.open('', '_blank', 'width=300,height=600');

        if (!printWindow) {
            alert('Please allow popups to print the POS receipt.');
            return;
        }

        printWindow.document.open();
        printWindow.document.write(html);
        printWindow.document.close();

        let printed = false;

        const triggerPrint = () => {
            if (printed || printWindow.closed) {
                return;
            }

            printed = true;
            printWindow.focus();
            printWindow.print();
        };

        printWindow.onload = () => {
            triggerPrint();
            printWindow.onafterprint = () => printWindow.close();
        };

        // Fallback for browsers where onload already fired before assignment.
        setTimeout(triggerPrint, 500);
    } catch (error) {
        console.error('POS print failed:', error);
        alert('Failed to print POS receipt.');
    }
}

if (typeof window !== 'undefined') {
    window.printPos = (order, options = {}) => {
        const payload = order?.order ? order : buildSellPosPrintPayload(order, options);
        posPrint(payload);
    };
    window.printPosInvoice = window.printPos;
    window.PosPrint = {
        print: window.printPos,
        printInvoice: window.printPos,
        buildFromSell: buildSellPosPrintPayload,
    };
}
