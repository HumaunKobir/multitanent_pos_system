import{a as e,r as t,t as n}from"./format-bd-date-ARckZ9II.js";import{n as r}from"./pos-discount-BirJKFet.js";function i(e){let t=String(e??`—`).trim();return t?t.split(`/`)[0].trim()||t:`—`}function a(e){let t=String(e??``).trim();return t.includes(`/`)?t.split(`/`).slice(1).join(`/`).trim():``}function o(e){let t=e.variant_id??e.variation_id??null;if(t==null||t===``)return``;let n=(e.variant?.name??e.variant_label??e.variation?.variation_data?.label??``).trim(),r=(e.variant?.sku??e.variant_sku??e.variation?.sku_code??``).trim();return n||r}function s(e){let t=i(e.product?.name??e.name??`—`),n=o(e),r=n?`${t} (Variant: ${n})`:t;return e.is_free_row?`${r} (FREE)`:r}function c(e){return a(e.product?.name??e.name??``)||(e.product?.code??e.product_code??``).trim()}var l=`
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
    grid-template-columns: minmax(0, 1fr) 34px 14px 44px 48px;
    column-gap: 4px;
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
    word-break: break-word;
    line-height: 1.15;
}

.pos-item-code {
    min-width: 0;
    overflow-wrap: anywhere;
    word-break: break-word;
    line-height: 1.15;
    font-size: 8px;
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
`;function u(e){return String(e??``).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`).replace(/'/g,`&#39;`)}function d(e){if(!e)return``;try{return new URL(e,window.location.href).href}catch{return String(e)}}async function f(e){let t=d(e);if(!t)return``;if(t.startsWith(`data:`))return t;try{let e=await fetch(t,{credentials:`same-origin`});if(!e.ok)return t;let n=await e.blob();return await new Promise(e=>{let r=new FileReader;r.onloadend=()=>e(String(r.result||t)),r.onerror=()=>e(t),r.readAsDataURL(n)})}catch{return t}}async function p(e){let t=e?.order?e:E(e?.sell??{},e?.options??e);return t.options.companyLogo&&(t.options.companyLogo=await f(t.options.companyLogo)),t}function m(e,t){let n=Array.from(e.document.images??[]);if(n.length===0){t();return}let r=n.length,i=()=>{--r,r<=0&&t()};for(let e of n){if(e.complete){i();continue}e.addEventListener(`load`,i,{once:!0}),e.addEventListener(`error`,i,{once:!0})}}function h(e){return`${parseFloat(e??0).toLocaleString(`en-BD`,{minimumFractionDigits:2,maximumFractionDigits:2})} TK`}function g(e){return parseFloat(e??0).toLocaleString(`en-BD`,{minimumFractionDigits:2,maximumFractionDigits:2})}function _(e){let t=parseFloat(e??0);return Number.isInteger(t)?String(t):t.toFixed(2)}function v(e){return n(e).replace(`, `,` `)}function y(t){return e(t)}function b(){return t(new Date)}function x(e){let t=new Map;for(let n of e){let e=`${n.is_free_row?`free`:`paid`}-${n.variant_id??n.variation_id??`product-${n.product_id??n.id}`}`,r=t.get(e);if(r){r.quantity+=parseFloat(n.quantity??0),r.amount+=parseFloat(n.amount??0),r.line_discount=parseFloat(r.line_discount??0)+parseFloat(n.line_discount??0),r.promotion_discount=parseFloat(r.promotion_discount??0)+parseFloat(n.promotion_discount??0);continue}t.set(e,{...n,quantity:parseFloat(n.quantity??0),amount:parseFloat(n.amount??0),line_discount:parseFloat(n.line_discount??0),promotion_discount:parseFloat(n.promotion_discount??0)})}return Array.from(t.values())}function S(e,t){return`
        <div class="pos-total-row">
            <div class="pos-total-label">${u(e)}:</div>
            <div class="pos-total-value">${h(t)}</div>
        </div>`}function C(e){let t=e.payment_account??e.paymentAccount,n=t?.code??``,r=t?.name??``;return n&&r?`${n} — ${r}`:n||r||`Account`}function w(e){return(e??[]).filter(e=>parseFloat(e.amount??0)>0).map(e=>`
        <div class="pos-total-row pos-small">
            <div>${u(C(e))}</div>
            <div class="pos-total-value">${h(e.amount)}</div>
        </div>`).join(``)}function T(e){return e?e.replace(/<[^>]*>/g,``).trim().length>0:!1}function E(e,t={}){let n=parseFloat(e.gross_amount??0),i=parseFloat(e.vat??0),a=parseFloat(e.discount??0),o=parseFloat(e.special_discount_amount??0),s=parseFloat(e.promotion_discount_total??0),c=parseFloat(e.coin_discount_amount??0),l=parseFloat(e.round_off_amount??0),u=(e.products??[]).reduce((e,t)=>e+parseFloat(t.discount??0),0),d=r(n,s,e.products??[]),f=n+i-a-o-c-l-u,p=(e.payments??[]).map(e=>({id:e.id,payment_account_id:e.payment_account_id,amount:parseFloat(e.amount??0),payment_account:e.payment_account??e.paymentAccount??null})),m=p.reduce((e,t)=>e+t.amount,0),h=parseFloat(e.paid_amount??0),g=Math.max(0,f-m),_=t.change!=null&&t.change!==``?parseFloat(t.change):Math.max(0,m-f),v=(e.products??[]).flatMap(e=>{let t=parseFloat(e.quantity??0),n=parseFloat(e.free_quantity??0),r=parseFloat(e.unit_price??e.sell_price??e.price??0),i=parseFloat(e.discount??0),a=parseFloat(e.promotion_discount??0),o=e.variation_id??null,s=o?(e.variation?.variation_data?.label??``).trim():``,c=o?(e.variation?.sku_code??``).trim():``,l={id:e.id,product_id:e.product_id,variant_id:o,unit_price:r,sell_price:r,line_discount:i,promotion_discount:a,promotion_label:e.promotion?.name??e.promotion_label??null,product:{name:e.product?.name??`—`,code:e.product?.code??e.product_code??``},product_code:e.product?.code??e.product_code??``,variant:o&&(s||c)?{name:s||c,sku:c||s}:null,name:e.product?.name??`—`,variant_label:s||c},u=[];return t>0&&u.push({...l,quantity:t,price:r*t-i,amount:r*t-i,is_free_row:!1}),n>0&&u.push({...l,id:`${e.id}-free`,quantity:n,unit_price:0,sell_price:0,price:0,amount:0,line_discount:0,is_free_row:!0}),u});return{options:{showHeader:t.showHeader??!0,showFooter:t.showFooter??!0,showCustomerInfo:t.showCustomerInfo??!0,showItems:t.showItems??!0,showTotals:t.showTotals??!0,showThankYou:t.showThankYou??!0,companyName:t.companyName||t.siteName||`Coolness Point`,companyAddress:t.companyAddress||t.contact?.address||e.branch?.address||``,companyPhone:t.companyPhone||t.contact?.phone||e.branch?.phone||``,companyEmail:t.companyEmail||t.contact?.email||``,companyWebsite:t.companyWebsite||``,companyLogo:t.logoUrl||t.companyLogo||``,branchName:t.branchName||e.branch?.name||``,termsAndConditions:t.termsAndConditions??e.branch?.pos_terms_and_conditions??``},order:{id:e.id,invoice_no:e.invoice_number??`INVS${String(e.id).padStart(8,`0`)}`,date:e.date??``,created_at:e.created_at??null,comment:e.comment??``,total_price:f,paid:h,due:g,change:_,customer:{name:e.customer?.name??`Walk-in Customer`,phone:e.customer?.phone??``,address:e.customer?.address??``},orderproduct:v,payments:p,totals:{gross:d,vat:i,invoiceDiscount:a,specialDiscount:o,specialDiscountName:e.special_discount?.name??null,coinDiscount:c,coinsRedeemed:parseFloat(e.coins_redeemed??0),coinsEarned:parseFloat(e.coins_earned??0),roundOff:l,lineDiscount:u,promotionDiscount:s,discount:a+o+s+u,net:f,paid:h,due:g,change:_}}}}function D(e){let{options:t,order:n}=e,r=n.totals??{},i=x(n.orderproduct??[]),a=t.showHeader?`
        <div class="pos-header">
            ${t.companyLogo?`<div class="pos-logo-wrap"><img class="pos-logo" src="${u(t.companyLogo)}" alt="${u(t.companyName)}" /></div>`:``}
            <div class="pos-title">${u(t.companyName)}</div>
            ${t.branchName&&t.branchName.trim().toLowerCase()!==String(t.companyName??``).trim().toLowerCase()?`<div class="pos-subtitle">${u(t.branchName)}</div>`:``}
            ${t.companyAddress?`<div class="pos-subtitle">${u(t.companyAddress)}</div>`:``}
            ${t.companyPhone?`<div class="pos-info">Tel: ${u(t.companyPhone)}</div>`:``}
            <div class="pos-divider"></div>
            <div class="pos-bold">INVOICE</div>
            <div class="pos-small">Date: ${u(v(n.date))} | Time: ${u(y(n.created_at))}</div>
            <div class="pos-small">Invoice No: ${u(n.invoice_no)}</div>
        </div>`:``,o=t.showCustomerInfo?`
        <div class="pos-customer">
            <div class="pos-bold">Customer Details:</div>
            <div class="pos-customer-line">Name: ${u(n.customer?.name||`Walk-in Customer`)}</div>
            ${n.customer?.phone?`<div class="pos-customer-line">Phone: ${u(n.customer.phone)}</div>`:``}
            ${n.customer?.address?`<div class="pos-customer-line">Address: ${u(n.customer.address)}</div>`:``}
        </div>`:``,d=t.showItems?`
        <div class="pos-items">
            <div class="pos-item pos-bold">
                <div class="pos-item-name">Item</div>
                <div class="pos-item-code">Code</div>
                <div class="pos-item-qty">Qty</div>
                <div class="pos-item-unit-price">U.Price</div>
                <div class="pos-item-price">Price</div>
            </div>
            <div class="pos-divider"></div>
            ${i.map(e=>{let t=[],n=parseFloat(e.amount??e.price??0)+parseFloat(e.promotion_discount??0)+parseFloat(e.line_discount??0),r=parseFloat(e.quantity??0),i=r>0?n/r:0;return`
                <div class="pos-item">
                    <div class="pos-item-name">${u(s(e))}</div>
                    <div class="pos-item-code">${u(c(e))}</div>
                    <div class="pos-item-qty">${_(e.quantity)}</div>
                    <div class="pos-item-unit-price">${g(i)}</div>
                    <div class="pos-item-price">${g(n)}</div>
                </div>
                ${t.length?`<div class="pos-item-meta">${u(t.join(` | `))}</div>`:``}`}).join(``)}
        </div>`:``,f=w(n.payments),p=f.length>0,m=[r.gross==null?``:S(`Subtotal`,r.gross),parseFloat(r.lineDiscount??0)>0?S(`Line Discount`,r.lineDiscount):``,parseFloat(r.promotionDiscount??0)>0?S(`Promotion Discount`,r.promotionDiscount):``,parseFloat(r.invoiceDiscount??0)>0?S(`Invoice Discount`,r.invoiceDiscount):``,parseFloat(r.specialDiscount??0)>0?S(r.specialDiscountName?`Special (${r.specialDiscountName})`:`Special Discount`,r.specialDiscount):``,parseFloat(r.coinDiscount??0)>0?S(`Coin Discount`,r.coinDiscount):``,parseFloat(r.roundOff??0)>0?S(`Round Off`,r.roundOff):``,parseFloat(r.vat??0)>0?S(`VAT`,r.vat):``,S(`Total`,r.net??n.total_price),S(`Paid`,r.paid??n.paid),parseFloat(r.due??n.due??0)>0?S(`Due`,r.due??n.due):``,parseFloat(r.change??n.change??0)>0?S(`Change`,r.change??n.change):``].filter(Boolean).join(``),h=t.showTotals?`
        <div class="pos-totals">
            <div class="pos-divider"></div>
            ${m}
            ${p?`
            <div class="pos-divider"></div>
            <div class="pos-bold pos-small">Payment Accounts</div>
            ${f}`:``}
        </div>`:``,C=n.comment?`<div class="pos-note"><span class="pos-bold">Note:</span> ${u(n.comment)}</div>`:``,E=T(t.termsAndConditions)?`<div class="pos-terms"><div class="pos-terms-title">Terms & Conditions</div>${t.termsAndConditions}</div>`:``,D=t.showFooter?`
        <div class="pos-footer">
            ${t.showThankYou?`<div class="pos-thank-you">Thank you for your business!</div>`:``}
            <div class="pos-small">Please keep this receipt</div>
            <div class="pos-tiny">For any queries, contact us</div>
            ${t.companyPhone?`<div class="pos-small">Tel: ${u(t.companyPhone)}</div>`:``}
            ${t.companyEmail?`<div class="pos-small">Email: ${u(t.companyEmail)}</div>`:``}
            ${t.companyWebsite?`<div class="pos-small">Web: ${u(t.companyWebsite)}</div>`:``}
            <div class="pos-divider"></div>
            <div class="pos-tiny">Printed on: ${u(b())}</div>
        </div>`:``;return`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>${u(n.invoice_no)}</title>
    <style>${l}</style>
</head>
<body>
    <div class="pos-container">
        ${a}
        ${o}
        ${C}
        ${d}
        ${h}
        ${E}
        ${D}
    </div>
</body>
</html>`}async function O(e){try{let t=D(await p(e)),n=window.open(``,`_blank`,`width=320,height=700`);if(!n){alert(`Please allow popups to print the POS receipt.`);return}n.document.open(),n.document.write(t),n.document.close();let r=!1,i=()=>{r||n.closed||(r=!0,n.focus(),n.print(),n.onafterprint=()=>n.close())},a=()=>{m(n,()=>{requestAnimationFrame(()=>i())})};n.onload=a,setTimeout(a,300)}catch(e){console.error(`POS print failed:`,e),alert(`Failed to print POS receipt.`)}}typeof window<`u`&&(window.printPos=(e,t={})=>{O(e?.order?e:E(e,t))},window.printPosInvoice=window.printPos,window.PosPrint={print:window.printPos,printInvoice:window.printPos,buildFromSell:E});export{T as n,O as r,E as t};