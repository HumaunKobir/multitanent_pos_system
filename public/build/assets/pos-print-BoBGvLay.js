import{n as e,r as t,t as n}from"./format-bd-date-D8J8Ea5-.js";var r=`
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
`;function i(e){return String(e??``).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`).replace(/'/g,`&#39;`)}function a(e){return`${parseFloat(e??0).toLocaleString(`en-BD`,{minimumFractionDigits:2,maximumFractionDigits:2})} TK`}function o(e){let t=parseFloat(e??0);return Number.isInteger(t)?String(t):t.toFixed(2)}function s(e,t=28){let n=String(e??``).trim();return n.length<=t?n:`${n.slice(0,t-1)}…`}function c(e){return n(e).replace(`, `,` `)}function l(e){return t(e)}function u(){return e(new Date)}function d(e){let t=new Map;for(let n of e){let e=n.variant_id??n.variation_id??`product-${n.product_id??n.id}`,r=t.get(e);if(r){r.quantity+=parseFloat(n.quantity??0),r.amount+=parseFloat(n.amount??0);continue}t.set(e,{...n,quantity:parseFloat(n.quantity??0),amount:parseFloat(n.amount??0)})}return Array.from(t.values())}function f(e){let t=e.variant_id??e.variation_id??null;if(t==null||t===``)return``;let n=(e.variant?.name??e.variant_label??e.variation?.variation_data?.label??``).trim(),r=(e.variant?.sku??e.variant_sku??e.variation?.sku_code??``).trim();return n||r}function p(e){let t=s(e.product?.name??e.name??`—`),n=f(e);return n?`${t} (Variant: ${n})`:t}function m(e,t){return`
        <div class="pos-total-row">
            <div class="pos-total-label">${i(e)}:</div>
            <div class="pos-total-value">${a(t)}</div>
        </div>`}function h(e){let t=e.payment_account??e.paymentAccount,n=t?.code??``,r=t?.name??``;return n&&r?`${n} — ${r}`:n||r||`Account`}function g(e){return(e??[]).filter(e=>parseFloat(e.amount??0)>0).map(e=>`
        <div class="pos-total-row pos-small">
            <div>${i(h(e))}</div>
            <div class="pos-total-value">${a(e.amount)}</div>
        </div>`).join(``)}function _(e){return e?e.replace(/<[^>]*>/g,``).trim().length>0:!1}function v(e,t={}){let n=parseFloat(e.gross_amount??0),r=parseFloat(e.vat??0),i=parseFloat(e.discount??0),a=parseFloat(e.special_discount_amount??0),o=parseFloat(e.round_off_amount??0),s=(e.products??[]).reduce((e,t)=>e+parseFloat(t.discount??0),0),c=n+r-i-a-o-s,l=(e.payments??[]).map(e=>({id:e.id,payment_account_id:e.payment_account_id,amount:parseFloat(e.amount??0),payment_account:e.payment_account??e.paymentAccount??null})),u=l.reduce((e,t)=>e+t.amount,0),d=parseFloat(e.paid_amount??0),f=Math.max(0,c-u),p=t.change!=null&&t.change!==``?parseFloat(t.change):Math.max(0,u-c),m=(e.products??[]).map(e=>{let t=parseFloat(e.quantity??0),n=parseFloat(e.unit_price??e.sell_price??e.price??0),r=parseFloat(e.discount??0),i=e.variation_id??null,a=i?(e.variation?.variation_data?.label??``).trim():``,o=i?(e.variation?.sku_code??``).trim():``;return{id:e.id,product_id:e.product_id,variant_id:i,quantity:t,unit_price:n,sell_price:n,price:n*t-r,amount:n*t-r,line_discount:r,product:{name:e.product?.name??`—`},variant:i&&(a||o)?{name:a||o,sku:o||a}:null,name:e.product?.name??`—`,variant_label:a||o}});return{options:{showHeader:t.showHeader??!0,showFooter:t.showFooter??!0,showCustomerInfo:t.showCustomerInfo??!0,showItems:t.showItems??!0,showTotals:t.showTotals??!0,showThankYou:t.showThankYou??!0,companyName:t.companyName||t.siteName||`Coolness Point`,companyAddress:t.companyAddress||t.contact?.address||e.branch?.address||``,companyPhone:t.companyPhone||t.contact?.phone||e.branch?.phone||``,companyEmail:t.companyEmail||t.contact?.email||``,companyWebsite:t.companyWebsite||``,companyLogo:t.logoUrl||t.companyLogo||``,branchName:t.branchName||e.branch?.name||``,termsAndConditions:t.termsAndConditions??e.branch?.pos_terms_and_conditions??``},order:{id:e.id,invoice_no:e.invoice_number??`INVS${String(e.id).padStart(8,`0`)}`,date:e.date??``,created_at:e.created_at??null,comment:e.comment??``,total_price:c,paid:d,due:f,change:p,customer:{name:e.customer?.name??`Walk-in Customer`,phone:e.customer?.phone??``,address:e.customer?.address??``},orderproduct:m,payments:l,totals:{gross:n,vat:r,invoiceDiscount:i,specialDiscount:a,specialDiscountName:e.special_discount?.name??null,roundOff:o,lineDiscount:s,discount:i+a+s,net:c,paid:d,due:f,change:p}}}}function y(e){let{options:t,order:n}=e,s=n.totals??{},f=d(n.orderproduct??[]),h=t.showHeader?`
        <div class="pos-header">
            ${t.companyLogo?`<div class="pos-logo-wrap"><img class="pos-logo" src="${i(t.companyLogo)}" alt="" onerror="this.parentElement.style.display='none'" /></div>`:``}
            <div class="pos-title">${i(t.companyName)}</div>
            ${t.branchName?`<div class="pos-subtitle">${i(t.branchName)}</div>`:``}
            ${t.companyAddress?`<div class="pos-subtitle">${i(t.companyAddress)}</div>`:``}
            ${t.companyPhone?`<div class="pos-info">Tel: ${i(t.companyPhone)}</div>`:``}
            <div class="pos-divider"></div>
            <div class="pos-bold">INVOICE</div>
            <div class="pos-small">Date: ${i(c(n.date))} | Time: ${i(l(n.created_at))}</div>
            <div class="pos-small">Invoice No: ${i(n.invoice_no)}</div>
        </div>`:``,v=t.showCustomerInfo?`
        <div class="pos-customer">
            <div class="pos-bold">Customer Details:</div>
            <div class="pos-customer-line">Name: ${i(n.customer?.name||`Walk-in Customer`)}</div>
            ${n.customer?.phone?`<div class="pos-customer-line">Phone: ${i(n.customer.phone)}</div>`:``}
            ${n.customer?.address?`<div class="pos-customer-line">Address: ${i(n.customer.address)}</div>`:``}
        </div>`:``,y=t.showItems?`
        <div class="pos-items">
            <div class="pos-item pos-bold">
                <div class="pos-item-name">Item</div>
                <div class="pos-item-qty">Qty</div>
                <div class="pos-item-price">Price</div>
            </div>
            <div class="pos-divider"></div>
            ${f.map(e=>{let t=[];return parseFloat(e.line_discount??0)>0&&t.push(`Disc: ${a(e.line_discount)}`),`
                <div class="pos-item">
                    <div class="pos-item-name">${i(p(e))}</div>
                    <div class="pos-item-qty">${o(e.quantity)}</div>
                    <div class="pos-item-price">${a(e.amount??e.price)}</div>
                </div>
                ${t.length?`<div class="pos-item-meta">${i(t.join(` | `))}</div>`:``}`}).join(``)}
        </div>`:``,b=g(n.payments),x=b.length>0,S=[s.gross==null?``:m(`Subtotal`,s.gross),parseFloat(s.lineDiscount??0)>0?m(`Line Discount`,s.lineDiscount):``,parseFloat(s.invoiceDiscount??0)>0?m(`Invoice Discount`,s.invoiceDiscount):``,parseFloat(s.specialDiscount??0)>0?m(s.specialDiscountName?`Special (${s.specialDiscountName})`:`Special Discount`,s.specialDiscount):``,parseFloat(s.roundOff??0)>0?m(`Round Off`,s.roundOff):``,parseFloat(s.vat??0)>0?m(`VAT`,s.vat):``,m(`Total`,s.net??n.total_price),m(`Paid`,s.paid??n.paid),parseFloat(s.due??n.due??0)>0?m(`Due`,s.due??n.due):``,parseFloat(s.change??n.change??0)>0?m(`Change`,s.change??n.change):``].filter(Boolean).join(``),C=t.showTotals?`
        <div class="pos-totals">
            <div class="pos-divider"></div>
            ${S}
            ${x?`
            <div class="pos-divider"></div>
            <div class="pos-bold pos-small">Payment Accounts</div>
            ${b}`:``}
        </div>`:``,w=n.comment?`<div class="pos-note"><span class="pos-bold">Note:</span> ${i(n.comment)}</div>`:``,T=_(t.termsAndConditions)?`<div class="pos-terms"><div class="pos-terms-title">Terms & Conditions</div>${t.termsAndConditions}</div>`:``,E=t.showFooter?`
        <div class="pos-footer">
            ${t.showThankYou?`<div class="pos-thank-you">Thank you for your business!</div>`:``}
            <div class="pos-small">Please keep this receipt</div>
            <div class="pos-tiny">For any queries, contact us</div>
            ${t.companyPhone?`<div class="pos-small">Tel: ${i(t.companyPhone)}</div>`:``}
            ${t.companyEmail?`<div class="pos-small">Email: ${i(t.companyEmail)}</div>`:``}
            ${t.companyWebsite?`<div class="pos-small">Web: ${i(t.companyWebsite)}</div>`:``}
            <div class="pos-divider"></div>
            <div class="pos-tiny">Printed on: ${i(u())}</div>
        </div>`:``;return`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>${i(n.invoice_no)}</title>
    <style>${r}</style>
</head>
<body>
    <div class="pos-container">
        ${h}
        ${v}
        ${w}
        ${y}
        ${C}
        ${T}
        ${E}
    </div>
</body>
</html>`}function b(e){try{let t=y(e?.order?e:v(e?.sell??{},e?.options??e)),n=window.open(``,`_blank`,`width=300,height=600`);if(!n){alert(`Please allow popups to print the POS receipt.`);return}n.document.open(),n.document.write(t),n.document.close();let r=!1,i=()=>{r||n.closed||(r=!0,n.focus(),n.print())};n.onload=()=>{i(),n.onafterprint=()=>n.close()},setTimeout(i,500)}catch(e){console.error(`POS print failed:`,e),alert(`Failed to print POS receipt.`)}}typeof window<`u`&&(window.printPos=(e,t={})=>{b(e?.order?e:v(e,t))},window.printPosInvoice=window.printPos,window.PosPrint={print:window.printPos,printInvoice:window.printPos,buildFromSell:v});export{_ as n,b as r,v as t};