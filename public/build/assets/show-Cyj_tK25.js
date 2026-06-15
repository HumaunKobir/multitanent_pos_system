import{c as e,r as t,t as n}from"./jsx-runtime-Tg1yNRQt.js";import{c as r,n as i,r as a,u as o}from"./dist-BX2C9IAz.js";import{t as s}from"./arrow-left-DJFeL8xY.js";import{t as c}from"./receipt-Bp8EvaAj.js";import{t as l}from"./square-pen-BvEWnYib.js";import{t as u}from"./trash-2-B1s2mon4.js";import{F as d,N as f,O as p,v as m,x as h}from"./app-CSHkBuDh.js";import{a as g,i as _,n as v,o as y,r as b,s as x,t as S}from"./dialog-D6X3ucgO.js";import{t as C}from"./use-can-79PxEZAG.js";import{t as w}from"./can-rPLb_YiC.js";import{i as T,n as E,r as D,t as O}from"./invoice-show-layout-Cq07kw5s.js";var k=`
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
`;function A(e){return String(e??``).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`).replace(/'/g,`&#39;`)}function j(e){return`${parseFloat(e??0).toLocaleString(`en-BD`,{minimumFractionDigits:2,maximumFractionDigits:2})} TK`}function M(e){let t=parseFloat(e??0);return Number.isInteger(t)?String(t):t.toFixed(2)}function N(e,t=28){let n=String(e??``).trim();return n.length<=t?n:`${n.slice(0,t-1)}…`}function P(e){if(!e)return`—`;let t=new Date(e);return Number.isNaN(t.getTime())?String(e):t.toLocaleDateString(`en-GB`,{day:`2-digit`,month:`short`,year:`numeric`})}function F(e){let t=e?new Date(e):new Date;return Number.isNaN(t.getTime())?new Date().toLocaleTimeString(`en-GB`,{hour:`2-digit`,minute:`2-digit`}):t.toLocaleTimeString(`en-GB`,{hour:`2-digit`,minute:`2-digit`})}function I(){let e=new Date;return`${e.toLocaleDateString(`en-GB`,{day:`2-digit`,month:`short`,year:`numeric`})} ${e.toLocaleTimeString(`en-GB`,{hour:`2-digit`,minute:`2-digit`})}`}function L(e){let t=new Map;for(let n of e){let e=n.variant_id??n.variation_id??`product-${n.product_id??n.id}`,r=t.get(e);if(r){r.quantity+=parseFloat(n.quantity??0),r.amount+=parseFloat(n.amount??0);continue}t.set(e,{...n,quantity:parseFloat(n.quantity??0),amount:parseFloat(n.amount??0)})}return Array.from(t.values())}function R(e){let t=N(e.product?.name??e.name??`—`),n=e.variant?.name??e.variant_label??e.variation?.variation_data?.label??``,r=e.variant?.sku??e.variant_sku??e.product?.code??e.code??``;return n||r?`${t} (Variant: ${r||n})`:t}function z(e,t){return`
        <div class="pos-total-row">
            <div class="pos-total-label">${A(e)}:</div>
            <div class="pos-total-value">${j(t)}</div>
        </div>`}function B(e,t={}){let n=parseFloat(e.gross_amount??0),r=parseFloat(e.vat??0),i=parseFloat(e.discount??0),a=parseFloat(e.special_discount_amount??0),o=(e.products??[]).reduce((e,t)=>e+parseFloat(t.discount??0),0),s=n+r-i-a-o,c=parseFloat(e.paid_amount??0),l=Math.max(0,s-c),u=parseFloat(t.change??0),d=(e.products??[]).map(e=>{let t=parseFloat(e.quantity??0),n=parseFloat(e.unit_price??e.sell_price??e.price??0),r=parseFloat(e.discount??0),i=e.variation?.variation_data?.label??e.variation?.sku_code??``;return{id:e.id,product_id:e.product_id,variant_id:e.variation_id??null,quantity:t,unit_price:n,sell_price:n,price:n*t-r,amount:n*t-r,line_discount:r,product:{name:e.product?.name??`—`,code:e.product?.code??``},variant:i?{name:i,sku:e.variation?.sku_code??i}:null,code:e.product?.code??``,name:e.product?.name??`—`,variant_label:i}});return{options:{showHeader:t.showHeader??!0,showFooter:t.showFooter??!0,showCustomerInfo:t.showCustomerInfo??!0,showItems:t.showItems??!0,showTotals:t.showTotals??!0,showThankYou:t.showThankYou??!0,companyName:t.companyName||t.siteName||`Coolness Point`,companyAddress:t.companyAddress||t.contact?.address||e.branch?.address||``,companyPhone:t.companyPhone||t.contact?.phone||e.branch?.phone||``,companyEmail:t.companyEmail||t.contact?.email||``,companyWebsite:t.companyWebsite||``,companyLogo:t.logoUrl||t.companyLogo||``,branchName:t.branchName||e.branch?.name||``},order:{id:e.id,invoice_no:e.invoice_number??`INVS${String(e.id).padStart(8,`0`)}`,date:e.date??``,comment:e.comment??``,total_price:s,paid:c,due:l,change:u,customer:{name:e.customer?.name??`Walk-in Customer`,phone:e.customer?.phone??``,address:e.customer?.address??``},orderproduct:d,totals:{gross:n,vat:r,invoiceDiscount:i,specialDiscount:a,specialDiscountName:e.special_discount?.name??null,lineDiscount:o,discount:i+a+o,net:s,paid:c,due:l,change:u}}}}function V(e){let{options:t,order:n}=e,r=n.totals??{},i=L(n.orderproduct??[]),a=t.showHeader?`
        <div class="pos-header">
            ${t.companyLogo?`<div class="pos-logo-wrap"><img class="pos-logo" src="${A(t.companyLogo)}" alt="" onerror="this.parentElement.style.display='none'" /></div>`:``}
            <div class="pos-title">${A(t.companyName)}</div>
            ${t.branchName?`<div class="pos-subtitle">${A(t.branchName)}</div>`:``}
            ${t.companyAddress?`<div class="pos-subtitle">${A(t.companyAddress)}</div>`:``}
            ${t.companyPhone?`<div class="pos-info">Tel: ${A(t.companyPhone)}</div>`:``}
            <div class="pos-divider"></div>
            <div class="pos-bold">INVOICE</div>
            <div class="pos-small">Date: ${A(P(n.date))} | Time: ${A(F(n.date))}</div>
            <div class="pos-small">Invoice No: ${A(n.invoice_no)}</div>
        </div>`:``,o=t.showCustomerInfo?`
        <div class="pos-customer">
            <div class="pos-bold">Customer Details:</div>
            <div class="pos-customer-line">Name: ${A(n.customer?.name||`Walk-in Customer`)}</div>
            ${n.customer?.phone?`<div class="pos-customer-line">Phone: ${A(n.customer.phone)}</div>`:``}
            ${n.customer?.address?`<div class="pos-customer-line">Address: ${A(n.customer.address)}</div>`:``}
        </div>`:``,s=t.showItems?`
        <div class="pos-items">
            <div class="pos-item pos-bold">
                <div class="pos-item-name">Item</div>
                <div class="pos-item-qty">Qty</div>
                <div class="pos-item-price">Price</div>
            </div>
            <div class="pos-divider"></div>
            ${i.map(e=>{let t=[];return(e.product?.code||e.code)&&t.push(`Code: ${e.product?.code??e.code}`),parseFloat(e.line_discount??0)>0&&t.push(`Disc: ${j(e.line_discount)}`),`
                <div class="pos-item">
                    <div class="pos-item-name">${A(R(e))}</div>
                    <div class="pos-item-qty">${M(e.quantity)}</div>
                    <div class="pos-item-price">${j(e.amount??e.price)}</div>
                </div>
                ${t.length?`<div class="pos-item-meta">${A(t.join(` | `))}</div>`:``}`}).join(``)}
        </div>`:``,c=[r.gross==null?``:z(`Subtotal`,r.gross),parseFloat(r.lineDiscount??0)>0?z(`Line Discount`,r.lineDiscount):``,parseFloat(r.invoiceDiscount??0)>0?z(`Invoice Discount`,r.invoiceDiscount):``,parseFloat(r.specialDiscount??0)>0?z(r.specialDiscountName?`Special (${r.specialDiscountName})`:`Special Discount`,r.specialDiscount):``,parseFloat(r.vat??0)>0?z(`VAT`,r.vat):``,z(`Total`,r.net??n.total_price),z(`Paid`,r.paid??n.paid),parseFloat(r.due??n.due??0)>0?z(`Due`,r.due??n.due):``,parseFloat(r.change??n.change??0)>0?z(`Change`,r.change??n.change):``].filter(Boolean).join(``),l=t.showTotals?`
        <div class="pos-totals">
            <div class="pos-divider"></div>
            ${c}
        </div>`:``,u=n.comment?`<div class="pos-note"><span class="pos-bold">Note:</span> ${A(n.comment)}</div>`:``,d=t.showFooter?`
        <div class="pos-footer">
            ${t.showThankYou?`<div class="pos-thank-you">Thank you for your business!</div>`:``}
            <div class="pos-small">Please keep this receipt</div>
            <div class="pos-tiny">For any queries, contact us</div>
            ${t.companyPhone?`<div class="pos-small">Tel: ${A(t.companyPhone)}</div>`:``}
            ${t.companyEmail?`<div class="pos-small">Email: ${A(t.companyEmail)}</div>`:``}
            ${t.companyWebsite?`<div class="pos-small">Web: ${A(t.companyWebsite)}</div>`:``}
            <div class="pos-divider"></div>
            <div class="pos-tiny">Printed on: ${A(I())}</div>
        </div>`:``;return`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>${A(n.invoice_no)}</title>
    <style>${k}</style>
</head>
<body>
    <div class="pos-container">
        ${a}
        ${o}
        ${u}
        ${s}
        ${l}
        ${d}
    </div>
</body>
</html>`}function H(e){try{let t=V(e?.order?e:B(e?.sell??{},e?.options??e)),n=window.open(``,`_blank`,`width=300,height=600`);if(!n){alert(`Please allow popups to print the POS receipt.`);return}n.document.open(),n.document.write(t),n.document.close();let r=!1,i=()=>{r||n.closed||(r=!0,n.focus(),n.print())};n.onload=()=>{i(),n.onafterprint=()=>n.close()},setTimeout(i,500)}catch(e){console.error(`POS print failed:`,e),alert(`Failed to print POS receipt.`)}}typeof window<`u`&&(window.printPos=(e,t={})=>{H(e?.order?e:B(e,t))},window.printPosInvoice=window.printPos,window.PosPrint={print:window.printPos,printInvoice:window.printPos,buildFromSell:B});var U=e(t(),1),W=n(),G=`inline-flex h-8 flex-shrink-0 items-center justify-center gap-1.5 rounded-none border border-cyan-200 bg-white/95 px-2.5 text-xs font-medium text-cyan-700 shadow-sm backdrop-blur-[1px] transition-all duration-200 hover:-translate-y-0.5 hover:bg-cyan-50 hover:shadow-md`;function K({sell:e}){let{flash:t,logo:n,siteName:k,contact:A}=r().props,j=p(),{can:M}=C(),[N,P]=(0,U.useState)(!1),F=(0,U.useRef)(!1);(0,U.useEffect)(()=>{t.success&&j.success(t.success),t.error&&j.error(t.error)},[t.success,t.error]);let I=e.invoice_number??`INVS${String(e.id).padStart(8,`0`)}`,L=(e.products??[]).reduce((e,t)=>e+parseFloat(t.discount??0),0),R=parseFloat(e.gross_amount??0),z=parseFloat(e.vat??0),V=parseFloat(e.discount??0),K=parseFloat(e.special_discount_amount??0),q=R+z-V-K-L,J=parseFloat(e.paid_amount??0),Y=Math.max(0,q-J),X=e.payments??[],Z=T(),Q=(0,U.useCallback)(()=>{H(B(e,{companyName:e.branch?.name||k||`Coolness Point`,companyAddress:e.branch?.address||A?.address||``,companyPhone:e.branch?.phone||A?.phone||``,companyEmail:A?.email||``,logoUrl:n,branchName:e.branch?.name||``,change:t?.pos_change??0}))},[e,n,k,A,t?.pos_change]);(0,U.useEffect)(()=>{let e=new URLSearchParams(window.location.search);if(!(e.get(`pos_print`)===`1`||e.get(`pos_print`)===`true`)||F.current)return;F.current=!0;let t=setTimeout(()=>Q(),500);return()=>clearTimeout(t)},[Q]);function $(){o.delete(h(`inventory.sell.destroy`,e.id),{onSuccess:()=>P(!1)})}return(0,W.jsxs)(W.Fragment,{children:[(0,W.jsx)(i,{title:`Sale — ${I}`}),(0,W.jsxs)(`div`,{className:`px-2 py-1`,children:[(0,W.jsxs)(E,{icon:d,title:`Sale Invoice`,invoiceNumber:I,children:[(0,W.jsxs)(`button`,{type:`button`,onClick:Q,className:G,title:`POS print`,children:[(0,W.jsx)(c,{className:`size-3.5`}),`POS Print`]}),(0,W.jsx)(w,{permission:`inventory.sell.update`,children:(0,W.jsx)(m,{size:`sm`,asChild:!0,className:Z,children:(0,W.jsxs)(a,{href:h(`inventory.sell.edit`,e.id),children:[(0,W.jsx)(l,{className:`size-3.5`}),`Edit`]})})}),(0,W.jsx)(w,{permission:`inventory.sell.delete`,children:(0,W.jsxs)(m,{size:`sm`,variant:`destructive`,onClick:()=>P(!0),className:`border border-red-500/50 bg-red-600/90 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-600 hover:shadow-md`,children:[(0,W.jsx)(u,{className:`size-3.5`}),`Delete`]})}),(0,W.jsx)(m,{size:`sm`,asChild:!0,className:Z,children:(0,W.jsxs)(a,{href:h(`inventory.sell.index`),children:[(0,W.jsx)(s,{className:`size-3.5`}),`Back`]})})]}),(0,W.jsx)(O,{docTitle:`Sale Invoice`,invoiceNumber:I,date:e.date,branchName:e.branch?.name,items:e.products??[],totals:{gross:R,vat:z,discount:V,discountType:e.discount_type,discountValue:e.discount_value,specialDiscount:K,specialDiscountName:e.special_discount?.name,specialDiscountType:e.special_discount?.discount_type,specialDiscountValue:e.special_discount?.discount_value,lineDiscount:L,net:q,paid:J,due:Y},comment:e.comment,partySection:(0,W.jsx)(D,{icon:f,label:`Customer`,name:e.customer?.name,phone:e.customer?.phone,address:e.customer?.address,emptyText:`Walk-in Customer`})}),X.length>0&&(0,W.jsxs)(`div`,{className:`mx-auto mt-3 max-w-4xl border border-blue-200 bg-white p-3 shadow-sm`,children:[(0,W.jsx)(`h3`,{className:`mb-2 text-xs font-semibold uppercase tracking-wide text-blue-950`,children:`Payment Breakdown`}),(0,W.jsx)(`div`,{className:`space-y-1 text-sm`,children:X.map(e=>(0,W.jsxs)(`div`,{className:`flex items-center justify-between gap-3 border-b border-border/60 py-1 last:border-0`,children:[(0,W.jsxs)(`span`,{className:`text-muted-foreground`,children:[e.payment_account?.code,` — `,e.payment_account?.name]}),(0,W.jsxs)(`span`,{className:`font-medium tabular-nums`,children:[`৳`,parseFloat(e.amount??0).toFixed(2)]})]},e.id??`${e.payment_account_id}-${e.amount}`))})]}),M(`inventory.sell.delete`)&&(0,W.jsx)(S,{open:N,onOpenChange:P,children:(0,W.jsxs)(b,{className:`max-w-sm`,children:[(0,W.jsxs)(y,{children:[(0,W.jsx)(x,{children:`Delete sale?`}),(0,W.jsx)(_,{children:`This will permanently delete the sale and restore stock.`})]}),(0,W.jsxs)(g,{className:`mt-4 gap-2`,children:[(0,W.jsx)(v,{asChild:!0,children:(0,W.jsx)(m,{type:`button`,variant:`outline`,size:`sm`,children:`Cancel`})}),(0,W.jsx)(m,{type:`button`,variant:`destructive`,size:`sm`,onClick:$,children:`Delete`})]})]})})]})]})}export{K as default};