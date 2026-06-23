var e=`
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
`;function t(e){return String(e??``).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`).replace(/'/g,`&#39;`)}function n(e){return`${parseFloat(e??0).toLocaleString(`en-BD`,{minimumFractionDigits:2,maximumFractionDigits:2})} TK`}function r(e){let t=parseFloat(e??0);return Number.isInteger(t)?String(t):t.toFixed(2)}function i(e,t=28){let n=String(e??``).trim();return n.length<=t?n:`${n.slice(0,t-1)}…`}var a=`Asia/Dhaka`;function o(e){if(!e)return`—`;let t=new Date(e);return Number.isNaN(t.getTime())?String(e):t.toLocaleDateString(`en-GB`,{day:`2-digit`,month:`short`,year:`numeric`,timeZone:a})}function s(e){let t=e?new Date(e):new Date;return Number.isNaN(t.getTime())?new Date().toLocaleTimeString(`en-US`,{hour:`2-digit`,minute:`2-digit`,hour12:!0,timeZone:a}):t.toLocaleTimeString(`en-US`,{hour:`2-digit`,minute:`2-digit`,hour12:!0,timeZone:a})}function c(){let e=new Date;return`${e.toLocaleDateString(`en-GB`,{day:`2-digit`,month:`short`,year:`numeric`,timeZone:a})} ${e.toLocaleTimeString(`en-US`,{hour:`2-digit`,minute:`2-digit`,hour12:!0,timeZone:a})}`}function l(e){let t=new Map;for(let n of e){let e=n.variant_id??n.variation_id??`product-${n.product_id??n.id}`,r=t.get(e);if(r){r.quantity+=parseFloat(n.quantity??0),r.amount+=parseFloat(n.amount??0);continue}t.set(e,{...n,quantity:parseFloat(n.quantity??0),amount:parseFloat(n.amount??0)})}return Array.from(t.values())}function u(e){let t=i(e.product?.name??e.name??`—`),n=e.variant?.name??e.variant_label??e.variation?.variation_data?.label??``,r=e.variant?.sku??e.variant_sku??e.product?.code??e.code??``;return n||r?`${t} (Variant: ${r||n})`:t}function d(e,r){return`
        <div class="pos-total-row">
            <div class="pos-total-label">${t(e)}:</div>
            <div class="pos-total-value">${n(r)}</div>
        </div>`}function f(e){let t=e.payment_account??e.paymentAccount,n=t?.code??``,r=t?.name??``;return n&&r?`${n} — ${r}`:n||r||`Account`}function p(e){return(e??[]).filter(e=>parseFloat(e.amount??0)>0).map(e=>`
        <div class="pos-total-row pos-small">
            <div>${t(f(e))}</div>
            <div class="pos-total-value">${n(e.amount)}</div>
        </div>`).join(``)}function m(e){return e?e.replace(/<[^>]*>/g,``).trim().length>0:!1}function h(e,t={}){let n=parseFloat(e.gross_amount??0),r=parseFloat(e.vat??0),i=parseFloat(e.discount??0),a=parseFloat(e.special_discount_amount??0),o=parseFloat(e.round_off_amount??0),s=(e.products??[]).reduce((e,t)=>e+parseFloat(t.discount??0),0),c=n+r-i-a-o-s,l=parseFloat(e.paid_amount??0),u=Math.max(0,c-l),d=parseFloat(t.change??0),f=(e.products??[]).map(e=>{let t=parseFloat(e.quantity??0),n=parseFloat(e.unit_price??e.sell_price??e.price??0),r=parseFloat(e.discount??0),i=e.variation?.variation_data?.label??e.variation?.sku_code??``;return{id:e.id,product_id:e.product_id,variant_id:e.variation_id??null,quantity:t,unit_price:n,sell_price:n,price:n*t-r,amount:n*t-r,line_discount:r,product:{name:e.product?.name??`—`,code:e.product?.code??``},variant:i?{name:i,sku:e.variation?.sku_code??i}:null,code:e.product?.code??``,name:e.product?.name??`—`,variant_label:i}}),p=(e.payments??[]).map(e=>({id:e.id,payment_account_id:e.payment_account_id,amount:parseFloat(e.amount??0),payment_account:e.payment_account??e.paymentAccount??null}));return{options:{showHeader:t.showHeader??!0,showFooter:t.showFooter??!0,showCustomerInfo:t.showCustomerInfo??!0,showItems:t.showItems??!0,showTotals:t.showTotals??!0,showThankYou:t.showThankYou??!0,companyName:t.companyName||t.siteName||`Coolness Point`,companyAddress:t.companyAddress||t.contact?.address||e.branch?.address||``,companyPhone:t.companyPhone||t.contact?.phone||e.branch?.phone||``,companyEmail:t.companyEmail||t.contact?.email||``,companyWebsite:t.companyWebsite||``,companyLogo:t.logoUrl||t.companyLogo||``,branchName:t.branchName||e.branch?.name||``,termsAndConditions:t.termsAndConditions??e.branch?.pos_terms_and_conditions??``},order:{id:e.id,invoice_no:e.invoice_number??`INVS${String(e.id).padStart(8,`0`)}`,date:e.date??``,comment:e.comment??``,total_price:c,paid:l,due:u,change:d,customer:{name:e.customer?.name??`Walk-in Customer`,phone:e.customer?.phone??``,address:e.customer?.address??``},orderproduct:f,payments:p,totals:{gross:n,vat:r,invoiceDiscount:i,specialDiscount:a,specialDiscountName:e.special_discount?.name??null,roundOff:o,lineDiscount:s,discount:i+a+s,net:c,paid:l,due:u,change:d}}}}function g(i){let{options:a,order:f}=i,h=f.totals??{},g=l(f.orderproduct??[]),_=a.showHeader?`
        <div class="pos-header">
            ${a.companyLogo?`<div class="pos-logo-wrap"><img class="pos-logo" src="${t(a.companyLogo)}" alt="" onerror="this.parentElement.style.display='none'" /></div>`:``}
            <div class="pos-title">${t(a.companyName)}</div>
            ${a.branchName?`<div class="pos-subtitle">${t(a.branchName)}</div>`:``}
            ${a.companyAddress?`<div class="pos-subtitle">${t(a.companyAddress)}</div>`:``}
            ${a.companyPhone?`<div class="pos-info">Tel: ${t(a.companyPhone)}</div>`:``}
            <div class="pos-divider"></div>
            <div class="pos-bold">INVOICE</div>
            <div class="pos-small">Date: ${t(o(f.date))} | Time: ${t(s(f.date))}</div>
            <div class="pos-small">Invoice No: ${t(f.invoice_no)}</div>
        </div>`:``,v=a.showCustomerInfo?`
        <div class="pos-customer">
            <div class="pos-bold">Customer Details:</div>
            <div class="pos-customer-line">Name: ${t(f.customer?.name||`Walk-in Customer`)}</div>
            ${f.customer?.phone?`<div class="pos-customer-line">Phone: ${t(f.customer.phone)}</div>`:``}
            ${f.customer?.address?`<div class="pos-customer-line">Address: ${t(f.customer.address)}</div>`:``}
        </div>`:``,y=a.showItems?`
        <div class="pos-items">
            <div class="pos-item pos-bold">
                <div class="pos-item-name">Item</div>
                <div class="pos-item-qty">Qty</div>
                <div class="pos-item-price">Price</div>
            </div>
            <div class="pos-divider"></div>
            ${g.map(e=>{let i=[];return(e.product?.code||e.code)&&i.push(`Code: ${e.product?.code??e.code}`),parseFloat(e.line_discount??0)>0&&i.push(`Disc: ${n(e.line_discount)}`),`
                <div class="pos-item">
                    <div class="pos-item-name">${t(u(e))}</div>
                    <div class="pos-item-qty">${r(e.quantity)}</div>
                    <div class="pos-item-price">${n(e.amount??e.price)}</div>
                </div>
                ${i.length?`<div class="pos-item-meta">${t(i.join(` | `))}</div>`:``}`}).join(``)}
        </div>`:``,b=p(f.payments),x=b.length>0,S=[h.gross==null?``:d(`Subtotal`,h.gross),parseFloat(h.lineDiscount??0)>0?d(`Line Discount`,h.lineDiscount):``,parseFloat(h.invoiceDiscount??0)>0?d(`Invoice Discount`,h.invoiceDiscount):``,parseFloat(h.specialDiscount??0)>0?d(h.specialDiscountName?`Special (${h.specialDiscountName})`:`Special Discount`,h.specialDiscount):``,parseFloat(h.roundOff??0)>0?d(`Round Off`,h.roundOff):``,parseFloat(h.vat??0)>0?d(`VAT`,h.vat):``,d(`Total`,h.net??f.total_price),d(`Paid`,h.paid??f.paid),parseFloat(h.due??f.due??0)>0?d(`Due`,h.due??f.due):``,parseFloat(h.change??f.change??0)>0?d(`Change`,h.change??f.change):``].filter(Boolean).join(``),C=a.showTotals?`
        <div class="pos-totals">
            <div class="pos-divider"></div>
            ${S}
            ${x?`
            <div class="pos-divider"></div>
            <div class="pos-bold pos-small">Payment Accounts</div>
            ${b}`:``}
        </div>`:``,w=f.comment?`<div class="pos-note"><span class="pos-bold">Note:</span> ${t(f.comment)}</div>`:``,T=m(a.termsAndConditions)?`<div class="pos-terms"><div class="pos-terms-title">Terms & Conditions</div>${a.termsAndConditions}</div>`:``,E=a.showFooter?`
        <div class="pos-footer">
            ${a.showThankYou?`<div class="pos-thank-you">Thank you for your business!</div>`:``}
            <div class="pos-small">Please keep this receipt</div>
            <div class="pos-tiny">For any queries, contact us</div>
            ${a.companyPhone?`<div class="pos-small">Tel: ${t(a.companyPhone)}</div>`:``}
            ${a.companyEmail?`<div class="pos-small">Email: ${t(a.companyEmail)}</div>`:``}
            ${a.companyWebsite?`<div class="pos-small">Web: ${t(a.companyWebsite)}</div>`:``}
            <div class="pos-divider"></div>
            <div class="pos-tiny">Printed on: ${t(c())}</div>
        </div>`:``;return`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>${t(f.invoice_no)}</title>
    <style>${e}</style>
</head>
<body>
    <div class="pos-container">
        ${_}
        ${v}
        ${w}
        ${y}
        ${C}
        ${T}
        ${E}
    </div>
</body>
</html>`}function _(e){try{let t=g(e?.order?e:h(e?.sell??{},e?.options??e)),n=window.open(``,`_blank`,`width=300,height=600`);if(!n){alert(`Please allow popups to print the POS receipt.`);return}n.document.open(),n.document.write(t),n.document.close();let r=!1,i=()=>{r||n.closed||(r=!0,n.focus(),n.print())};n.onload=()=>{i(),n.onafterprint=()=>n.close()},setTimeout(i,500)}catch(e){console.error(`POS print failed:`,e),alert(`Failed to print POS receipt.`)}}typeof window<`u`&&(window.printPos=(e,t={})=>{_(e?.order?e:h(e,t))},window.printPosInvoice=window.printPos,window.PosPrint={print:window.printPos,printInvoice:window.printPos,buildFromSell:h});export{m as n,_ as r,h as t};