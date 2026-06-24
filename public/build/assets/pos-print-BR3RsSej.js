import{n as e,r as t,t as n}from"./format-bd-date-D8J8Ea5-.js";var r=`
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
`;function i(e){return String(e??``).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`).replace(/'/g,`&#39;`)}function a(e){if(!e)return``;try{return new URL(e,window.location.href).href}catch{return String(e)}}async function o(e){let t=a(e);if(!t)return``;if(t.startsWith(`data:`))return t;try{let e=await fetch(t,{credentials:`same-origin`});if(!e.ok)return t;let n=await e.blob();return await new Promise(e=>{let r=new FileReader;r.onloadend=()=>e(String(r.result||t)),r.onerror=()=>e(t),r.readAsDataURL(n)})}catch{return t}}async function s(e){let t=e?.order?e:S(e?.sell??{},e?.options??e);return t.options.companyLogo&&(t.options.companyLogo=await o(t.options.companyLogo)),t}function c(e,t){let n=Array.from(e.document.images??[]);if(n.length===0){t();return}let r=n.length,i=()=>{--r,r<=0&&t()};for(let e of n){if(e.complete){i();continue}e.addEventListener(`load`,i,{once:!0}),e.addEventListener(`error`,i,{once:!0})}}function l(e){return`${parseFloat(e??0).toLocaleString(`en-BD`,{minimumFractionDigits:2,maximumFractionDigits:2})} TK`}function u(e){let t=parseFloat(e??0);return Number.isInteger(t)?String(t):t.toFixed(2)}function d(e,t=28){let n=String(e??``).trim();return n.length<=t?n:`${n.slice(0,t-1)}…`}function f(e){return n(e).replace(`, `,` `)}function p(e){return t(e)}function m(){return e(new Date)}function h(e){let t=new Map;for(let n of e){let e=`${n.is_free_row?`free`:`paid`}-${n.variant_id??n.variation_id??`product-${n.product_id??n.id}`}`,r=t.get(e);if(r){r.quantity+=parseFloat(n.quantity??0),r.amount+=parseFloat(n.amount??0);continue}t.set(e,{...n,quantity:parseFloat(n.quantity??0),amount:parseFloat(n.amount??0)})}return Array.from(t.values())}function g(e){let t=e.variant_id??e.variation_id??null;if(t==null||t===``)return``;let n=(e.variant?.name??e.variant_label??e.variation?.variation_data?.label??``).trim(),r=(e.variant?.sku??e.variant_sku??e.variation?.sku_code??``).trim();return n||r}function _(e){let t=d(e.product?.name??e.name??`—`),n=g(e),r=n?`${t} (Variant: ${n})`:t;return e.is_free_row?`${r} (FREE)`:r}function v(e,t){return`
        <div class="pos-total-row">
            <div class="pos-total-label">${i(e)}:</div>
            <div class="pos-total-value">${l(t)}</div>
        </div>`}function y(e){let t=e.payment_account??e.paymentAccount,n=t?.code??``,r=t?.name??``;return n&&r?`${n} — ${r}`:n||r||`Account`}function b(e){return(e??[]).filter(e=>parseFloat(e.amount??0)>0).map(e=>`
        <div class="pos-total-row pos-small">
            <div>${i(y(e))}</div>
            <div class="pos-total-value">${l(e.amount)}</div>
        </div>`).join(``)}function x(e){return e?e.replace(/<[^>]*>/g,``).trim().length>0:!1}function S(e,t={}){let n=parseFloat(e.gross_amount??0),r=parseFloat(e.vat??0),i=parseFloat(e.discount??0),a=parseFloat(e.special_discount_amount??0),o=parseFloat(e.promotion_discount_total??0),s=parseFloat(e.round_off_amount??0),c=(e.products??[]).reduce((e,t)=>e+parseFloat(t.discount??0),0),l=n+r-i-a-s-c,u=(e.payments??[]).map(e=>({id:e.id,payment_account_id:e.payment_account_id,amount:parseFloat(e.amount??0),payment_account:e.payment_account??e.paymentAccount??null})),d=u.reduce((e,t)=>e+t.amount,0),f=parseFloat(e.paid_amount??0),p=Math.max(0,l-d),m=t.change!=null&&t.change!==``?parseFloat(t.change):Math.max(0,d-l),h=(e.products??[]).flatMap(e=>{let t=parseFloat(e.quantity??0),n=parseFloat(e.free_quantity??0),r=parseFloat(e.unit_price??e.sell_price??e.price??0),i=parseFloat(e.discount??0),a=e.variation_id??null,o=a?(e.variation?.variation_data?.label??``).trim():``,s=a?(e.variation?.sku_code??``).trim():``,c={id:e.id,product_id:e.product_id,variant_id:a,unit_price:r,sell_price:r,line_discount:i,promotion_label:e.promotion?.name??null,product:{name:e.product?.name??`—`},variant:a&&(o||s)?{name:o||s,sku:s||o}:null,name:e.product?.name??`—`,variant_label:o||s},l=[];return t>0&&l.push({...c,quantity:t,price:r*t-i,amount:r*t-i,is_free_row:!1}),n>0&&l.push({...c,id:`${e.id}-free`,quantity:n,unit_price:0,sell_price:0,price:0,amount:0,line_discount:0,is_free_row:!0}),l});return{options:{showHeader:t.showHeader??!0,showFooter:t.showFooter??!0,showCustomerInfo:t.showCustomerInfo??!0,showItems:t.showItems??!0,showTotals:t.showTotals??!0,showThankYou:t.showThankYou??!0,companyName:t.companyName||t.siteName||`Coolness Point`,companyAddress:t.companyAddress||t.contact?.address||e.branch?.address||``,companyPhone:t.companyPhone||t.contact?.phone||e.branch?.phone||``,companyEmail:t.companyEmail||t.contact?.email||``,companyWebsite:t.companyWebsite||``,companyLogo:t.logoUrl||t.companyLogo||``,branchName:t.branchName||e.branch?.name||``,termsAndConditions:t.termsAndConditions??e.branch?.pos_terms_and_conditions??``},order:{id:e.id,invoice_no:e.invoice_number??`INVS${String(e.id).padStart(8,`0`)}`,date:e.date??``,created_at:e.created_at??null,comment:e.comment??``,total_price:l,paid:f,due:p,change:m,customer:{name:e.customer?.name??`Walk-in Customer`,phone:e.customer?.phone??``,address:e.customer?.address??``},orderproduct:h,payments:u,totals:{gross:n,vat:r,invoiceDiscount:i,specialDiscount:a,specialDiscountName:e.special_discount?.name??null,roundOff:s,lineDiscount:c,discount:i+a+o+c,net:l,paid:f,due:p,change:m}}}}function C(e){let{options:t,order:n}=e,a=n.totals??{},o=h(n.orderproduct??[]),s=t.showHeader?`
        <div class="pos-header">
            ${t.companyLogo?`<div class="pos-logo-wrap"><img class="pos-logo" src="${i(t.companyLogo)}" alt="${i(t.companyName)}" /></div>`:``}
            <div class="pos-title">${i(t.companyName)}</div>
            ${t.branchName&&t.branchName.trim().toLowerCase()!==String(t.companyName??``).trim().toLowerCase()?`<div class="pos-subtitle">${i(t.branchName)}</div>`:``}
            ${t.companyAddress?`<div class="pos-subtitle">${i(t.companyAddress)}</div>`:``}
            ${t.companyPhone?`<div class="pos-info">Tel: ${i(t.companyPhone)}</div>`:``}
            <div class="pos-divider"></div>
            <div class="pos-bold">INVOICE</div>
            <div class="pos-small">Date: ${i(f(n.date))} | Time: ${i(p(n.created_at))}</div>
            <div class="pos-small">Invoice No: ${i(n.invoice_no)}</div>
        </div>`:``,c=t.showCustomerInfo?`
        <div class="pos-customer">
            <div class="pos-bold">Customer Details:</div>
            <div class="pos-customer-line">Name: ${i(n.customer?.name||`Walk-in Customer`)}</div>
            ${n.customer?.phone?`<div class="pos-customer-line">Phone: ${i(n.customer.phone)}</div>`:``}
            ${n.customer?.address?`<div class="pos-customer-line">Address: ${i(n.customer.address)}</div>`:``}
        </div>`:``,d=t.showItems?`
        <div class="pos-items">
            <div class="pos-item pos-bold">
                <div class="pos-item-name">Item</div>
                <div class="pos-item-qty">Qty</div>
                <div class="pos-item-price">Price</div>
            </div>
            <div class="pos-divider"></div>
            ${o.map(e=>{let t=[];return parseFloat(e.line_discount??0)>0&&t.push(`Disc: ${l(e.line_discount)}`),`
                <div class="pos-item">
                    <div class="pos-item-name">${i(_(e))}</div>
                    <div class="pos-item-qty">${u(e.quantity)}</div>
                    <div class="pos-item-price">${l(e.amount??e.price)}</div>
                </div>
                ${t.length?`<div class="pos-item-meta">${i(t.join(` | `))}</div>`:``}`}).join(``)}
        </div>`:``,g=b(n.payments),y=g.length>0,S=[a.gross==null?``:v(`Subtotal`,a.gross),parseFloat(a.lineDiscount??0)>0?v(`Line Discount`,a.lineDiscount):``,parseFloat(a.invoiceDiscount??0)>0?v(`Invoice Discount`,a.invoiceDiscount):``,parseFloat(a.specialDiscount??0)>0?v(a.specialDiscountName?`Special (${a.specialDiscountName})`:`Special Discount`,a.specialDiscount):``,parseFloat(a.roundOff??0)>0?v(`Round Off`,a.roundOff):``,parseFloat(a.vat??0)>0?v(`VAT`,a.vat):``,v(`Total`,a.net??n.total_price),v(`Paid`,a.paid??n.paid),parseFloat(a.due??n.due??0)>0?v(`Due`,a.due??n.due):``,parseFloat(a.change??n.change??0)>0?v(`Change`,a.change??n.change):``].filter(Boolean).join(``),C=t.showTotals?`
        <div class="pos-totals">
            <div class="pos-divider"></div>
            ${S}
            ${y?`
            <div class="pos-divider"></div>
            <div class="pos-bold pos-small">Payment Accounts</div>
            ${g}`:``}
        </div>`:``,w=n.comment?`<div class="pos-note"><span class="pos-bold">Note:</span> ${i(n.comment)}</div>`:``,T=x(t.termsAndConditions)?`<div class="pos-terms"><div class="pos-terms-title">Terms & Conditions</div>${t.termsAndConditions}</div>`:``,E=t.showFooter?`
        <div class="pos-footer">
            ${t.showThankYou?`<div class="pos-thank-you">Thank you for your business!</div>`:``}
            <div class="pos-small">Please keep this receipt</div>
            <div class="pos-tiny">For any queries, contact us</div>
            ${t.companyPhone?`<div class="pos-small">Tel: ${i(t.companyPhone)}</div>`:``}
            ${t.companyEmail?`<div class="pos-small">Email: ${i(t.companyEmail)}</div>`:``}
            ${t.companyWebsite?`<div class="pos-small">Web: ${i(t.companyWebsite)}</div>`:``}
            <div class="pos-divider"></div>
            <div class="pos-tiny">Printed on: ${i(m())}</div>
        </div>`:``;return`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>${i(n.invoice_no)}</title>
    <style>${r}</style>
</head>
<body>
    <div class="pos-container">
        ${s}
        ${c}
        ${w}
        ${d}
        ${C}
        ${T}
        ${E}
    </div>
</body>
</html>`}async function w(e){try{let t=C(await s(e)),n=window.open(``,`_blank`,`width=320,height=700`);if(!n){alert(`Please allow popups to print the POS receipt.`);return}n.document.open(),n.document.write(t),n.document.close();let r=!1,i=()=>{r||n.closed||(r=!0,n.focus(),n.print(),n.onafterprint=()=>n.close())},a=()=>{c(n,()=>{requestAnimationFrame(()=>i())})};n.onload=a,setTimeout(a,300)}catch(e){console.error(`POS print failed:`,e),alert(`Failed to print POS receipt.`)}}typeof window<`u`&&(window.printPos=(e,t={})=>{w(e?.order?e:S(e,t))},window.printPosInvoice=window.printPos,window.PosPrint={print:window.printPos,printInvoice:window.printPos,buildFromSell:S});export{x as n,w as r,S as t};