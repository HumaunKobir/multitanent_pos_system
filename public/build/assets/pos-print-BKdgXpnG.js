import{n as e,r as t,t as n}from"./format-bd-date-CIESujto.js";import{n as r}from"./pos-discount-C1IgA2Cw.js";var i=`
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
`;function a(e){return String(e??``).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`).replace(/'/g,`&#39;`)}function o(e){if(!e)return``;try{return new URL(e,window.location.href).href}catch{return String(e)}}async function s(e){let t=o(e);if(!t)return``;if(t.startsWith(`data:`))return t;try{let e=await fetch(t,{credentials:`same-origin`});if(!e.ok)return t;let n=await e.blob();return await new Promise(e=>{let r=new FileReader;r.onloadend=()=>e(String(r.result||t)),r.onerror=()=>e(t),r.readAsDataURL(n)})}catch{return t}}async function c(e){let t=e?.order?e:w(e?.sell??{},e?.options??e);return t.options.companyLogo&&(t.options.companyLogo=await s(t.options.companyLogo)),t}function l(e,t){let n=Array.from(e.document.images??[]);if(n.length===0){t();return}let r=n.length,i=()=>{--r,r<=0&&t()};for(let e of n){if(e.complete){i();continue}e.addEventListener(`load`,i,{once:!0}),e.addEventListener(`error`,i,{once:!0})}}function u(e){return`${parseFloat(e??0).toLocaleString(`en-BD`,{minimumFractionDigits:2,maximumFractionDigits:2})} TK`}function d(e){return parseFloat(e??0).toLocaleString(`en-BD`,{minimumFractionDigits:2,maximumFractionDigits:2})}function f(e){let t=parseFloat(e??0);return Number.isInteger(t)?String(t):t.toFixed(2)}function p(e,t=22){let n=String(e??``).trim();return n.length<=t?n:`${n.slice(0,t-1)}…`}function m(e){return n(e).replace(`, `,` `)}function h(e){return t(e)}function g(){return e(new Date)}function _(e){let t=new Map;for(let n of e){let e=`${n.is_free_row?`free`:`paid`}-${n.variant_id??n.variation_id??`product-${n.product_id??n.id}`}`,r=t.get(e);if(r){r.quantity+=parseFloat(n.quantity??0),r.amount+=parseFloat(n.amount??0),r.line_discount=parseFloat(r.line_discount??0)+parseFloat(n.line_discount??0),r.promotion_discount=parseFloat(r.promotion_discount??0)+parseFloat(n.promotion_discount??0);continue}t.set(e,{...n,quantity:parseFloat(n.quantity??0),amount:parseFloat(n.amount??0),line_discount:parseFloat(n.line_discount??0),promotion_discount:parseFloat(n.promotion_discount??0)})}return Array.from(t.values())}function v(e){let t=e.variant_id??e.variation_id??null;if(t==null||t===``)return``;let n=(e.variant?.name??e.variant_label??e.variation?.variation_data?.label??``).trim(),r=(e.variant?.sku??e.variant_sku??e.variation?.sku_code??``).trim();return n||r}function y(e){let t=p(e.product?.name??e.name??`—`),n=v(e),r=n?`${t} (Variant: ${n})`:t;return e.is_free_row?`${r} (FREE)`:r}function b(e,t){return`
        <div class="pos-total-row">
            <div class="pos-total-label">${a(e)}:</div>
            <div class="pos-total-value">${u(t)}</div>
        </div>`}function x(e){let t=e.payment_account??e.paymentAccount,n=t?.code??``,r=t?.name??``;return n&&r?`${n} — ${r}`:n||r||`Account`}function S(e){return(e??[]).filter(e=>parseFloat(e.amount??0)>0).map(e=>`
        <div class="pos-total-row pos-small">
            <div>${a(x(e))}</div>
            <div class="pos-total-value">${u(e.amount)}</div>
        </div>`).join(``)}function C(e){return e?e.replace(/<[^>]*>/g,``).trim().length>0:!1}function w(e,t={}){let n=parseFloat(e.gross_amount??0),i=parseFloat(e.vat??0),a=parseFloat(e.discount??0),o=parseFloat(e.special_discount_amount??0),s=parseFloat(e.promotion_discount_total??0),c=parseFloat(e.coin_discount_amount??0),l=parseFloat(e.round_off_amount??0),u=(e.products??[]).reduce((e,t)=>e+parseFloat(t.discount??0),0),d=r(n,s,e.products??[]),f=n+i-a-o-c-l-u,p=(e.payments??[]).map(e=>({id:e.id,payment_account_id:e.payment_account_id,amount:parseFloat(e.amount??0),payment_account:e.payment_account??e.paymentAccount??null})),m=p.reduce((e,t)=>e+t.amount,0),h=parseFloat(e.paid_amount??0),g=Math.max(0,f-m),_=t.change!=null&&t.change!==``?parseFloat(t.change):Math.max(0,m-f),v=(e.products??[]).flatMap(e=>{let t=parseFloat(e.quantity??0),n=parseFloat(e.free_quantity??0),r=parseFloat(e.unit_price??e.sell_price??e.price??0),i=parseFloat(e.discount??0),a=parseFloat(e.promotion_discount??0),o=e.variation_id??null,s=o?(e.variation?.variation_data?.label??``).trim():``,c=o?(e.variation?.sku_code??``).trim():``,l={id:e.id,product_id:e.product_id,variant_id:o,unit_price:r,sell_price:r,line_discount:i,promotion_discount:a,promotion_label:e.promotion?.name??e.promotion_label??null,product:{name:e.product?.name??`—`},variant:o&&(s||c)?{name:s||c,sku:c||s}:null,name:e.product?.name??`—`,variant_label:s||c},u=[];return t>0&&u.push({...l,quantity:t,price:r*t-i,amount:r*t-i,is_free_row:!1}),n>0&&u.push({...l,id:`${e.id}-free`,quantity:n,unit_price:0,sell_price:0,price:0,amount:0,line_discount:0,is_free_row:!0}),u});return{options:{showHeader:t.showHeader??!0,showFooter:t.showFooter??!0,showCustomerInfo:t.showCustomerInfo??!0,showItems:t.showItems??!0,showTotals:t.showTotals??!0,showThankYou:t.showThankYou??!0,companyName:t.companyName||t.siteName||`Coolness Point`,companyAddress:t.companyAddress||t.contact?.address||e.branch?.address||``,companyPhone:t.companyPhone||t.contact?.phone||e.branch?.phone||``,companyEmail:t.companyEmail||t.contact?.email||``,companyWebsite:t.companyWebsite||``,companyLogo:t.logoUrl||t.companyLogo||``,branchName:t.branchName||e.branch?.name||``,termsAndConditions:t.termsAndConditions??e.branch?.pos_terms_and_conditions??``},order:{id:e.id,invoice_no:e.invoice_number??`INVS${String(e.id).padStart(8,`0`)}`,date:e.date??``,created_at:e.created_at??null,comment:e.comment??``,total_price:f,paid:h,due:g,change:_,customer:{name:e.customer?.name??`Walk-in Customer`,phone:e.customer?.phone??``,address:e.customer?.address??``},orderproduct:v,payments:p,totals:{gross:d,vat:i,invoiceDiscount:a,specialDiscount:o,specialDiscountName:e.special_discount?.name??null,coinDiscount:c,coinsRedeemed:parseFloat(e.coins_redeemed??0),coinsEarned:parseFloat(e.coins_earned??0),roundOff:l,lineDiscount:u,promotionDiscount:s,discount:a+o+s+u,net:f,paid:h,due:g,change:_}}}}function T(e){let{options:t,order:n}=e,r=n.totals??{},o=_(n.orderproduct??[]),s=t.showHeader?`
        <div class="pos-header">
            ${t.companyLogo?`<div class="pos-logo-wrap"><img class="pos-logo" src="${a(t.companyLogo)}" alt="${a(t.companyName)}" /></div>`:``}
            <div class="pos-title">${a(t.companyName)}</div>
            ${t.branchName&&t.branchName.trim().toLowerCase()!==String(t.companyName??``).trim().toLowerCase()?`<div class="pos-subtitle">${a(t.branchName)}</div>`:``}
            ${t.companyAddress?`<div class="pos-subtitle">${a(t.companyAddress)}</div>`:``}
            ${t.companyPhone?`<div class="pos-info">Tel: ${a(t.companyPhone)}</div>`:``}
            <div class="pos-divider"></div>
            <div class="pos-bold">INVOICE</div>
            <div class="pos-small">Date: ${a(m(n.date))} | Time: ${a(h(n.created_at))}</div>
            <div class="pos-small">Invoice No: ${a(n.invoice_no)}</div>
        </div>`:``,c=t.showCustomerInfo?`
        <div class="pos-customer">
            <div class="pos-bold">Customer Details:</div>
            <div class="pos-customer-line">Name: ${a(n.customer?.name||`Walk-in Customer`)}</div>
            ${n.customer?.phone?`<div class="pos-customer-line">Phone: ${a(n.customer.phone)}</div>`:``}
            ${n.customer?.address?`<div class="pos-customer-line">Address: ${a(n.customer.address)}</div>`:``}
        </div>`:``,l=t.showItems?`
        <div class="pos-items">
            <div class="pos-item pos-bold">
                <div class="pos-item-name">Item</div>
                <div class="pos-item-qty">Qty</div>
                <div class="pos-item-unit-price">U.Price</div>
                <div class="pos-item-price">Price</div>
            </div>
            <div class="pos-divider"></div>
            ${o.map(e=>{let t=[],n=parseFloat(e.amount??e.price??0)+parseFloat(e.promotion_discount??0)+parseFloat(e.line_discount??0),r=parseFloat(e.quantity??0),i=r>0?n/r:0;return`
                <div class="pos-item">
                    <div class="pos-item-name">${a(y(e))}</div>
                    <div class="pos-item-qty">${f(e.quantity)}</div>
                    <div class="pos-item-unit-price">${d(i)}</div>
                    <div class="pos-item-price">${d(n)}</div>
                </div>
                ${t.length?`<div class="pos-item-meta">${a(t.join(` | `))}</div>`:``}`}).join(``)}
        </div>`:``,u=S(n.payments),p=u.length>0,v=[r.gross==null?``:b(`Subtotal`,r.gross),parseFloat(r.lineDiscount??0)>0?b(`Line Discount`,r.lineDiscount):``,parseFloat(r.promotionDiscount??0)>0?b(`Promotion Discount`,r.promotionDiscount):``,parseFloat(r.invoiceDiscount??0)>0?b(`Invoice Discount`,r.invoiceDiscount):``,parseFloat(r.specialDiscount??0)>0?b(r.specialDiscountName?`Special (${r.specialDiscountName})`:`Special Discount`,r.specialDiscount):``,parseFloat(r.coinDiscount??0)>0?b(`Coin Discount`,r.coinDiscount):``,parseFloat(r.roundOff??0)>0?b(`Round Off`,r.roundOff):``,parseFloat(r.vat??0)>0?b(`VAT`,r.vat):``,b(`Total`,r.net??n.total_price),b(`Paid`,r.paid??n.paid),parseFloat(r.due??n.due??0)>0?b(`Due`,r.due??n.due):``,parseFloat(r.change??n.change??0)>0?b(`Change`,r.change??n.change):``].filter(Boolean).join(``),x=t.showTotals?`
        <div class="pos-totals">
            <div class="pos-divider"></div>
            ${v}
            ${p?`
            <div class="pos-divider"></div>
            <div class="pos-bold pos-small">Payment Accounts</div>
            ${u}`:``}
        </div>`:``,w=n.comment?`<div class="pos-note"><span class="pos-bold">Note:</span> ${a(n.comment)}</div>`:``,T=C(t.termsAndConditions)?`<div class="pos-terms"><div class="pos-terms-title">Terms & Conditions</div>${t.termsAndConditions}</div>`:``,E=t.showFooter?`
        <div class="pos-footer">
            ${t.showThankYou?`<div class="pos-thank-you">Thank you for your business!</div>`:``}
            <div class="pos-small">Please keep this receipt</div>
            <div class="pos-tiny">For any queries, contact us</div>
            ${t.companyPhone?`<div class="pos-small">Tel: ${a(t.companyPhone)}</div>`:``}
            ${t.companyEmail?`<div class="pos-small">Email: ${a(t.companyEmail)}</div>`:``}
            ${t.companyWebsite?`<div class="pos-small">Web: ${a(t.companyWebsite)}</div>`:``}
            <div class="pos-divider"></div>
            <div class="pos-tiny">Printed on: ${a(g())}</div>
        </div>`:``;return`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>${a(n.invoice_no)}</title>
    <style>${i}</style>
</head>
<body>
    <div class="pos-container">
        ${s}
        ${c}
        ${w}
        ${l}
        ${x}
        ${T}
        ${E}
    </div>
</body>
</html>`}async function E(e){try{let t=T(await c(e)),n=window.open(``,`_blank`,`width=320,height=700`);if(!n){alert(`Please allow popups to print the POS receipt.`);return}n.document.open(),n.document.write(t),n.document.close();let r=!1,i=()=>{r||n.closed||(r=!0,n.focus(),n.print(),n.onafterprint=()=>n.close())},a=()=>{l(n,()=>{requestAnimationFrame(()=>i())})};n.onload=a,setTimeout(a,300)}catch(e){console.error(`POS print failed:`,e),alert(`Failed to print POS receipt.`)}}typeof window<`u`&&(window.printPos=(e,t={})=>{E(e?.order?e:w(e,t))},window.printPosInvoice=window.printPos,window.PosPrint={print:window.printPos,printInvoice:window.printPos,buildFromSell:w});export{C as n,E as r,w as t};