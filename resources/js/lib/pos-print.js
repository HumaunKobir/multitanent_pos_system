import { computeSellDisplayGross } from '@/lib/pos-discount';
import { formatBdDate, formatBdDateTime, formatBdTime } from '@/lib/format-bd-date';

const POS_PRINT_STYLES = `
* {
    box-sizing: border-box;
}

html, body {
    margin: 0;
    padding: 0;
}

@media print {
    @page {
        size: 80mm auto;
        margin: 0;
    }
    html, body {
        margin: 0 !important;
        padding: 0 !important;
    }
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color: #000 !important;
    }
}

body {
    font-family: 'Courier New', 'Liberation Mono', monospace;
    font-size: 11px;
    line-height: 1.2;
    margin: 0;
    padding: 0;
    color: #000;
    background: #fff;
    font-weight: 700;
}

.pos-container {
    width: 100%;
    max-width: 80mm;
    margin: 0;
    padding: 2px 4px 4px;
}

.pos-header {
    text-align: center;
    border-bottom: 1px dashed #000;
    padding-bottom: 4px;
    margin-bottom: 6px;
    padding-top: 0;
}

.pos-title {
    font-size: 15px;
    font-weight: 900;
    margin-bottom: 2px;
    color: #000;
}

.pos-logo-wrap {
    margin: 0 0 3px;
    line-height: 0;
    min-height: 0;
}

.pos-logo {
    display: block;
    margin: 0 auto;
    max-height: 48px;
    max-width: 72mm;
    width: auto;
    height: auto;
    object-fit: contain;
}

.pos-subtitle {
    font-size: 9px;
    margin-bottom: 2px;
    color: #000;
    font-weight: 700;
}

.pos-info {
    font-size: 9px;
    margin-bottom: 4px;
    color: #000;
    font-weight: 700;
}

.pos-customer {
    border-bottom: 1px dashed #000;
    padding-bottom: 6px;
    margin-bottom: 6px;
}

.pos-customer-line {
    color: #000;
    font-weight: 700;
    font-size: 11px;
}

.pos-items {
    margin-bottom: 10px;
}

.pos-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 16px 56px 62px;
    column-gap: 6px;
    align-items: start;
    margin-bottom: 3px;
    font-size: 9px;
    color: #000;
}

.pos-items .pos-item.pos-bold {
    font-size: 9px;
}

.pos-item-name {
    min-width: 0;
    overflow-wrap: anywhere;
    line-height: 1.15;
}

.pos-item-qty {
    text-align: center;
    white-space: nowrap;
}

.pos-item-unit-price,
.pos-item-price {
    text-align: right;
    white-space: nowrap;
}

.pos-item-meta {
    margin: -1px 0 2px 0;
    font-size: 9px;
    padding-left: 2px;
    color: #000;
    font-weight: 700;
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
    margin: 4px 0;
}

.pos-center {
    text-align: center;
}

.pos-right {
    text-align: right;
}

.pos-bold {
    font-weight: 900;
    font-size: 12px;
    color: #000;
}

.pos-small {
    font-size: 9px;
    color: #000;
    font-weight: 700;
}

.pos-tiny {
    font-size: 8px;
    color: #000;
    font-weight: 700;
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

.pos-terms,
.pos-terms * {
    color: #000 !important;
    font-weight: 700;
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

function resolveAbsoluteUrl(url) {
    if (!url) {
        return '';
    }

    try {
        return new URL(url, window.location.href).href;
    } catch {
        return String(url);
    }
}

/**
 * Embed logo as a data URL so thermal printers and popup print windows load it reliably.
 */
async function embedLogoDataUrl(url) {
    const absoluteUrl = resolveAbsoluteUrl(url);

    if (!absoluteUrl) {
        return '';
    }

    if (absoluteUrl.startsWith('data:')) {
        return absoluteUrl;
    }

    try {
        const response = await fetch(absoluteUrl, { credentials: 'same-origin' });

        if (!response.ok) {
            return absoluteUrl;
        }

        const blob = await response.blob();

        return await new Promise((resolve) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(String(reader.result || absoluteUrl));
            reader.onerror = () => resolve(absoluteUrl);
            reader.readAsDataURL(blob);
        });
    } catch {
        return absoluteUrl;
    }
}

async function preparePrintData(payload) {
    const data = payload?.order ? payload : buildSellPosPrintPayload(payload?.sell ?? {}, payload?.options ?? payload);

    if (data.options.companyLogo) {
        data.options.companyLogo = await embedLogoDataUrl(data.options.companyLogo);
    }

    return data;
}

function waitForPrintImages(printWindow, onReady) {
    const images = Array.from(printWindow.document.images ?? []);

    if (images.length === 0) {
        onReady();
        return;
    }

    let pending = images.length;

    const markLoaded = () => {
        pending -= 1;

        if (pending <= 0) {
            onReady();
        }
    };

    for (const image of images) {
        if (image.complete) {
            markLoaded();
            continue;
        }

        image.addEventListener('load', markLoaded, { once: true });
        image.addEventListener('error', markLoaded, { once: true });
    }
}

function formatMoneyTk(value) {
    const amount = parseFloat(value ?? 0);
    return `${amount.toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} TK`;
}

function formatMoneyAmount(value) {
    const amount = parseFloat(value ?? 0);
    return amount.toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatQty(value) {
    const qty = parseFloat(value ?? 0);
    return Number.isInteger(qty) ? String(qty) : qty.toFixed(2);
}

function truncateName(name, max = 22) {
    const text = String(name ?? '').trim();
    if (text.length <= max) {
        return text;
    }

    return `${text.slice(0, max - 1)}…`;
}

function formatPosReceiptDate(value) {
    return formatBdDate(value).replace(', ', ' ');
}

function formatPosReceiptTime(value) {
    return formatBdTime(value);
}

function formatPrintedAt() {
    return formatBdDateTime(new Date());
}

function groupItemsByVariant(items) {
    const groups = new Map();

    for (const item of items) {
        const key = `${item.is_free_row ? 'free' : 'paid'}-${item.variant_id ?? item.variation_id ?? `product-${item.product_id ?? item.id}`}`;
        const existing = groups.get(key);

        if (existing) {
            existing.quantity += parseFloat(item.quantity ?? 0);
            existing.amount += parseFloat(item.amount ?? 0);
            existing.line_discount = parseFloat(existing.line_discount ?? 0) + parseFloat(item.line_discount ?? 0);
            existing.promotion_discount =
                parseFloat(existing.promotion_discount ?? 0) + parseFloat(item.promotion_discount ?? 0);
            continue;
        }

        groups.set(key, {
            ...item,
            quantity: parseFloat(item.quantity ?? 0),
            amount: parseFloat(item.amount ?? 0),
            line_discount: parseFloat(item.line_discount ?? 0),
            promotion_discount: parseFloat(item.promotion_discount ?? 0),
        });
    }

    return Array.from(groups.values());
}

function getVariantDisplayText(item) {
    const variantId = item.variant_id ?? item.variation_id ?? null;

    if (variantId == null || variantId === '') {
        return '';
    }

    const variantLabel = (item.variant?.name ?? item.variant_label ?? item.variation?.variation_data?.label ?? '').trim();
    const variantSku = (item.variant?.sku ?? item.variant_sku ?? item.variation?.sku_code ?? '').trim();

    return variantLabel || variantSku;
}

function buildItemName(item) {
    const baseName = truncateName(item.product?.name ?? item.name ?? '—');
    const variantText = getVariantDisplayText(item);
    const name = !variantText ? baseName : `${baseName} (Variant: ${variantText})`;

    return item.is_free_row ? `${name} (FREE)` : name;
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
    const grossAmount = parseFloat(sell.gross_amount ?? 0);
    const vat = parseFloat(sell.vat ?? 0);
    const invoiceDiscount = parseFloat(sell.discount ?? 0);
    const specialDiscount = parseFloat(sell.special_discount_amount ?? 0);
    const promotionDiscount = parseFloat(sell.promotion_discount_total ?? 0);
    const coinDiscount = parseFloat(sell.coin_discount_amount ?? 0);
    const roundOff = parseFloat(sell.round_off_amount ?? 0);
    const lineDiscount = (sell.products ?? []).reduce((sum, item) => sum + parseFloat(item.discount ?? 0), 0);
    const gross = computeSellDisplayGross(grossAmount, promotionDiscount, sell.products ?? []);
    const net = grossAmount + vat - invoiceDiscount - specialDiscount - coinDiscount - roundOff - lineDiscount;
    const payments = (sell.payments ?? []).map((line) => ({
        id: line.id,
        payment_account_id: line.payment_account_id,
        amount: parseFloat(line.amount ?? 0),
        payment_account: line.payment_account ?? line.paymentAccount ?? null,
    }));
    const totalTendered = payments.reduce((sum, line) => sum + line.amount, 0);
    const paid = parseFloat(sell.paid_amount ?? 0);
    const due = Math.max(0, net - totalTendered);
    const change =
        options.change != null && options.change !== ''
            ? parseFloat(options.change)
            : Math.max(0, totalTendered - net);

    const lineItems = (sell.products ?? []).flatMap((item) => {
        const paidQty = parseFloat(item.quantity ?? 0);
        const freeQty = parseFloat(item.free_quantity ?? 0);
        const unitPrice = parseFloat(item.unit_price ?? item.sell_price ?? item.price ?? 0);
        const lineItemDiscount = parseFloat(item.discount ?? 0);
        const promotionDiscount = parseFloat(item.promotion_discount ?? 0);
        const variationId = item.variation_id ?? null;
        const variantLabel = variationId
            ? (item.variation?.variation_data?.label ?? '').trim()
            : '';
        const variantSku = variationId ? (item.variation?.sku_code ?? '').trim() : '';

        const baseRow = {
            id: item.id,
            product_id: item.product_id,
            variant_id: variationId,
            unit_price: unitPrice,
            sell_price: unitPrice,
            line_discount: lineItemDiscount,
            promotion_discount: promotionDiscount,
            promotion_label: item.promotion?.name ?? item.promotion_label ?? null,
            product: {
                name: item.product?.name ?? '—',
            },
            variant:
                variationId && (variantLabel || variantSku)
                    ? {
                          name: variantLabel || variantSku,
                          sku: variantSku || variantLabel,
                      }
                    : null,
            name: item.product?.name ?? '—',
            variant_label: variantLabel || variantSku,
        };

        const rows = [];

        if (paidQty > 0) {
            rows.push({
                ...baseRow,
                quantity: paidQty,
                price: unitPrice * paidQty - lineItemDiscount,
                amount: unitPrice * paidQty - lineItemDiscount,
                is_free_row: false,
            });
        }

        if (freeQty > 0) {
            rows.push({
                ...baseRow,
                id: `${item.id}-free`,
                quantity: freeQty,
                unit_price: 0,
                sell_price: 0,
                price: 0,
                amount: 0,
                line_discount: 0,
                is_free_row: true,
            });
        }

        return rows;
    });

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
            created_at: sell.created_at ?? null,
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
                coinDiscount,
                coinsRedeemed: parseFloat(sell.coins_redeemed ?? 0),
                coinsEarned: parseFloat(sell.coins_earned ?? 0),
                roundOff,
                lineDiscount,
                promotionDiscount,
                discount: invoiceDiscount + specialDiscount + promotionDiscount + lineDiscount,
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
            ${options.companyLogo ? `<div class="pos-logo-wrap"><img class="pos-logo" src="${escapeHtml(options.companyLogo)}" alt="${escapeHtml(options.companyName)}" /></div>` : ''}
            <div class="pos-title">${escapeHtml(options.companyName)}</div>
            ${options.branchName && options.branchName.trim().toLowerCase() !== String(options.companyName ?? '').trim().toLowerCase() ? `<div class="pos-subtitle">${escapeHtml(options.branchName)}</div>` : ''}
            ${options.companyAddress ? `<div class="pos-subtitle">${escapeHtml(options.companyAddress)}</div>` : ''}
            ${options.companyPhone ? `<div class="pos-info">Tel: ${escapeHtml(options.companyPhone)}</div>` : ''}
            <div class="pos-divider"></div>
            <div class="pos-bold">INVOICE</div>
            <div class="pos-small">Date: ${escapeHtml(formatPosReceiptDate(order.date))} | Time: ${escapeHtml(formatPosReceiptTime(order.created_at))}</div>
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
                <div class="pos-item-unit-price">U.Price</div>
                <div class="pos-item-price">Price</div>
            </div>
            <div class="pos-divider"></div>
            ${groupedItems
                .map((item) => {
                    const metaParts = [];

                    const originalAmount =
                        parseFloat(item.amount ?? item.price ?? 0) +
                        parseFloat(item.promotion_discount ?? 0) +
                        parseFloat(item.line_discount ?? 0);
                    const qty = parseFloat(item.quantity ?? 0);
                    const unitPrice = qty > 0 ? originalAmount / qty : 0;

                    return `
                <div class="pos-item">
                    <div class="pos-item-name">${escapeHtml(buildItemName(item))}</div>
                    <div class="pos-item-qty">${formatQty(item.quantity)}</div>
                    <div class="pos-item-unit-price">${formatMoneyAmount(unitPrice)}</div>
                    <div class="pos-item-price">${formatMoneyAmount(originalAmount)}</div>
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
        parseFloat(totals.promotionDiscount ?? 0) > 0 ? totalRow('Promotion Discount', totals.promotionDiscount) : '',
        parseFloat(totals.invoiceDiscount ?? 0) > 0 ? totalRow('Invoice Discount', totals.invoiceDiscount) : '',
        parseFloat(totals.specialDiscount ?? 0) > 0
            ? totalRow(totals.specialDiscountName ? `Special (${totals.specialDiscountName})` : 'Special Discount', totals.specialDiscount)
            : '',
        parseFloat(totals.coinDiscount ?? 0) > 0 ? totalRow('Coin Discount', totals.coinDiscount) : '',
        parseFloat(totals.roundOff ?? 0) > 0 ? totalRow('Round Off', totals.roundOff) : '',
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
export async function posPrint(payload) {
    try {
        const data = await preparePrintData(payload);
        const html = renderPosInvoice(data);

        const printWindow = window.open('', '_blank', 'width=320,height=700');

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
            printWindow.onafterprint = () => printWindow.close();
        };

        const schedulePrint = () => {
            waitForPrintImages(printWindow, () => {
                requestAnimationFrame(() => triggerPrint());
            });
        };

        printWindow.onload = schedulePrint;

        // Fallback for browsers where onload already fired before assignment.
        setTimeout(schedulePrint, 300);
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
