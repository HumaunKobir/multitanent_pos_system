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
`;function t(e){return String(e??``).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`).replace(/'/g,`&#39;`)}function n(e){return`${parseFloat(e??0).toLocaleString(`en-BD`,{minimumFractionDigits:2,maximumFractionDigits:2})} TK`}function r(e){let t=parseFloat(e??0);return Number.isInteger(t)?String(t):t.toFixed(2)}function i(e,t=28){let n=String(e??``).trim();return n.length<=t?n:`${n.slice(0,t-1)}…`}function a(e){if(!e)return`—`;let t=new Date(e);return Number.isNaN(t.getTime())?String(e):t.toLocaleDateString(`en-GB`,{day:`2-digit`,month:`short`,year:`numeric`})}function o(e){let t=e?new Date(e):new Date;return Number.isNaN(t.getTime())?new Date().toLocaleTimeString(`en-GB`,{hour:`2-digit`,minute:`2-digit`}):t.toLocaleTimeString(`en-GB`,{hour:`2-digit`,minute:`2-digit`})}function s(){let e=new Date;return`${e.toLocaleDateString(`en-GB`,{day:`2-digit`,month:`short`,year:`numeric`})} ${e.toLocaleTimeString(`en-GB`,{hour:`2-digit`,minute:`2-digit`})}`}function c(e){let t=new Map;for(let n of e){let e=n.variant_id??n.variation_id??`product-${n.product_id??n.id}`,r=t.get(e);if(r){r.quantity+=parseFloat(n.quantity??0),r.amount+=parseFloat(n.amount??0);continue}t.set(e,{...n,quantity:parseFloat(n.quantity??0),amount:parseFloat(n.amount??0)})}return Array.from(t.values())}function l(e){let t=i(e.product?.name??e.name??`—`),n=e.variant?.name??e.variant_label??e.variation?.variation_data?.label??``,r=e.variant?.sku??e.variant_sku??e.product?.code??e.code??``;return n||r?`${t} (Variant: ${r||n})`:t}function u(e,r){return`
        <div class="pos-total-row">
            <div class="pos-total-label">${t(e)}:</div>
            <div class="pos-total-value">${n(r)}</div>
        </div>`}function d(e){return e?e.replace(/<[^>]*>/g,``).trim().length>0:!1}function f(e,t={}){let n=parseFloat(e.gross_amount??0),r=parseFloat(e.vat??0),i=parseFloat(e.discount??0),a=parseFloat(e.special_discount_amount??0),o=(e.products??[]).reduce((e,t)=>e+parseFloat(t.discount??0),0),s=n+r-i-a-o,c=parseFloat(e.paid_amount??0),l=Math.max(0,s-c),u=parseFloat(t.change??0),d=(e.products??[]).map(e=>{let t=parseFloat(e.quantity??0),n=parseFloat(e.unit_price??e.sell_price??e.price??0),r=parseFloat(e.discount??0),i=e.variation?.variation_data?.label??e.variation?.sku_code??``;return{id:e.id,product_id:e.product_id,variant_id:e.variation_id??null,quantity:t,unit_price:n,sell_price:n,price:n*t-r,amount:n*t-r,line_discount:r,product:{name:e.product?.name??`—`,code:e.product?.code??``},variant:i?{name:i,sku:e.variation?.sku_code??i}:null,code:e.product?.code??``,name:e.product?.name??`—`,variant_label:i}});return{options:{showHeader:t.showHeader??!0,showFooter:t.showFooter??!0,showCustomerInfo:t.showCustomerInfo??!0,showItems:t.showItems??!0,showTotals:t.showTotals??!0,showThankYou:t.showThankYou??!0,companyName:t.companyName||t.siteName||`Coolness Point`,companyAddress:t.companyAddress||t.contact?.address||e.branch?.address||``,companyPhone:t.companyPhone||t.contact?.phone||e.branch?.phone||``,companyEmail:t.companyEmail||t.contact?.email||``,companyWebsite:t.companyWebsite||``,companyLogo:t.logoUrl||t.companyLogo||``,branchName:t.branchName||e.branch?.name||``,termsAndConditions:t.termsAndConditions??e.branch?.pos_terms_and_conditions??``},order:{id:e.id,invoice_no:e.invoice_number??`INVS${String(e.id).padStart(8,`0`)}`,date:e.date??``,comment:e.comment??``,total_price:s,paid:c,due:l,change:u,customer:{name:e.customer?.name??`Walk-in Customer`,phone:e.customer?.phone??``,address:e.customer?.address??``},orderproduct:d,totals:{gross:n,vat:r,invoiceDiscount:i,specialDiscount:a,specialDiscountName:e.special_discount?.name??null,lineDiscount:o,discount:i+a+o,net:s,paid:c,due:l,change:u}}}}function p(i){let{options:f,order:p}=i,m=p.totals??{},h=c(p.orderproduct??[]),g=f.showHeader?`
        <div class="pos-header">
            ${f.companyLogo?`<div class="pos-logo-wrap"><img class="pos-logo" src="${t(f.companyLogo)}" alt="" onerror="this.parentElement.style.display='none'" /></div>`:``}
            <div class="pos-title">${t(f.companyName)}</div>
            ${f.branchName?`<div class="pos-subtitle">${t(f.branchName)}</div>`:``}
            ${f.companyAddress?`<div class="pos-subtitle">${t(f.companyAddress)}</div>`:``}
            ${f.companyPhone?`<div class="pos-info">Tel: ${t(f.companyPhone)}</div>`:``}
            <div class="pos-divider"></div>
            <div class="pos-bold">INVOICE</div>
            <div class="pos-small">Date: ${t(a(p.date))} | Time: ${t(o(p.date))}</div>
            <div class="pos-small">Invoice No: ${t(p.invoice_no)}</div>
        </div>`:``,_=f.showCustomerInfo?`
        <div class="pos-customer">
            <div class="pos-bold">Customer Details:</div>
            <div class="pos-customer-line">Name: ${t(p.customer?.name||`Walk-in Customer`)}</div>
            ${p.customer?.phone?`<div class="pos-customer-line">Phone: ${t(p.customer.phone)}</div>`:``}
            ${p.customer?.address?`<div class="pos-customer-line">Address: ${t(p.customer.address)}</div>`:``}
        </div>`:``,v=f.showItems?`
        <div class="pos-items">
            <div class="pos-item pos-bold">
                <div class="pos-item-name">Item</div>
                <div class="pos-item-qty">Qty</div>
                <div class="pos-item-price">Price</div>
            </div>
            <div class="pos-divider"></div>
            ${h.map(e=>{let i=[];return(e.product?.code||e.code)&&i.push(`Code: ${e.product?.code??e.code}`),parseFloat(e.line_discount??0)>0&&i.push(`Disc: ${n(e.line_discount)}`),`
                <div class="pos-item">
                    <div class="pos-item-name">${t(l(e))}</div>
                    <div class="pos-item-qty">${r(e.quantity)}</div>
                    <div class="pos-item-price">${n(e.amount??e.price)}</div>
                </div>
                ${i.length?`<div class="pos-item-meta">${t(i.join(` | `))}</div>`:``}`}).join(``)}
        </div>`:``,y=[m.gross==null?``:u(`Subtotal`,m.gross),parseFloat(m.lineDiscount??0)>0?u(`Line Discount`,m.lineDiscount):``,parseFloat(m.invoiceDiscount??0)>0?u(`Invoice Discount`,m.invoiceDiscount):``,parseFloat(m.specialDiscount??0)>0?u(m.specialDiscountName?`Special (${m.specialDiscountName})`:`Special Discount`,m.specialDiscount):``,parseFloat(m.vat??0)>0?u(`VAT`,m.vat):``,u(`Total`,m.net??p.total_price),u(`Paid`,m.paid??p.paid),parseFloat(m.due??p.due??0)>0?u(`Due`,m.due??p.due):``,parseFloat(m.change??p.change??0)>0?u(`Change`,m.change??p.change):``].filter(Boolean).join(``),b=f.showTotals?`
        <div class="pos-totals">
            <div class="pos-divider"></div>
            ${y}
        </div>`:``,x=p.comment?`<div class="pos-note"><span class="pos-bold">Note:</span> ${t(p.comment)}</div>`:``,S=d(f.termsAndConditions)?`<div class="pos-terms"><div class="pos-terms-title">Terms & Conditions</div>${f.termsAndConditions}</div>`:``,C=f.showFooter?`
        <div class="pos-footer">
            ${f.showThankYou?`<div class="pos-thank-you">Thank you for your business!</div>`:``}
            <div class="pos-small">Please keep this receipt</div>
            <div class="pos-tiny">For any queries, contact us</div>
            ${f.companyPhone?`<div class="pos-small">Tel: ${t(f.companyPhone)}</div>`:``}
            ${f.companyEmail?`<div class="pos-small">Email: ${t(f.companyEmail)}</div>`:``}
            ${f.companyWebsite?`<div class="pos-small">Web: ${t(f.companyWebsite)}</div>`:``}
            <div class="pos-divider"></div>
            <div class="pos-tiny">Printed on: ${t(s())}</div>
        </div>`:``;return`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>${t(p.invoice_no)}</title>
    <style>${e}</style>
</head>
<body>
    <div class="pos-container">
        ${g}
        ${_}
        ${x}
        ${v}
        ${b}
        ${S}
        ${C}
    </div>
</body>
</html>`}function m(e){try{let t=p(e?.order?e:f(e?.sell??{},e?.options??e)),n=window.open(``,`_blank`,`width=300,height=600`);if(!n){alert(`Please allow popups to print the POS receipt.`);return}n.document.open(),n.document.write(t),n.document.close();let r=!1,i=()=>{r||n.closed||(r=!0,n.focus(),n.print())};n.onload=()=>{i(),n.onafterprint=()=>n.close()},setTimeout(i,500)}catch(e){console.error(`POS print failed:`,e),alert(`Failed to print POS receipt.`)}}typeof window<`u`&&(window.printPos=(e,t={})=>{m(e?.order?e:f(e,t))},window.printPosInvoice=window.printPos,window.PosPrint={print:window.printPos,printInvoice:window.printPos,buildFromSell:f});export{d as n,m as r,f as t};