import{n as e,s as t,t as n}from"./jsx-runtime-6_rQPNQF.js";import{t as r}from"./compiler-runtime-BEUpNReH.js";import{c as i,n as a,r as o,u as s}from"./dist-CcZGCiug.js";import{t as c}from"./arrow-left-C7XwQ9dW.js";import{t as l}from"./receipt-BpVg1Kna.js";import{t as u}from"./square-pen-B6sN6SA7.js";import{t as ee}from"./trash-2-BcWSvuxo.js";import{F as te,N as ne,O as re,v as d,x as f}from"./app-Cd4LwrBJ.js";import{a as ie,i as ae,n as oe,o as se,r as ce,s as le,t as ue}from"./dialog-D1jERjW-.js";import{t as de}from"./use-can-DOrCKzqZ.js";import{t as fe}from"./can-DDflHVwp.js";import{i as pe,n as me,r as he,t as ge}from"./invoice-show-layout-DzuyxuH7.js";var _e=r();function p(e){return String(e??``).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`).replace(/'/g,`&#39;`)}function m(e){return parseFloat(e??0).toFixed(2)}function h(e){let t=parseFloat(e??0);return Number.isInteger(t)?String(t):t.toFixed(2)}function ve({companyName:e,logoUrl:t,branchName:n,invoiceNumber:r,date:i,customer:a,products:o,totals:s,footer:c=`Powered by Coolness Point`}){try{let l=a?.name?.trim()||`Walk-in Customer`,u=a?.phone?.trim()||``,ee=a?.address?.trim()||``,te=o.map(e=>`
            <tr class="service">
                <td>${p(e.name)}</td>
                <td>${p(e.variant||``)}</td>
                <td style="text-align:right">${m(e.rate)}</td>
                <td style="text-align:right">${h(e.qty)}</td>
                <td style="text-align:right">${m(e.amount)}</td>
            </tr>`).join(``),ne=t?`<img src="${p(t)}" alt="${p(e)}" style="max-height:48px;max-width:200px;margin:0 auto 6px;display:block;" />`:``,re=parseFloat(s.change??Math.max(0,parseFloat(s.paid)-parseFloat(s.net))),d=`<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>${p(r)}</title>
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
        ${ne}
        <p class="company-name">${p(e)}</p>
        ${n?`<p class="branch-name">${p(n)}</p>`:``}
        <table style="width:100%;">
            <tr class="tableitem2">
                <td colspan="2">
                    Name: ${p(l)}<br />
                    ${u?`Phone: ${p(u)}<br />`:``}
                    ${ee?`Address: ${p(ee)}`:``}
                </td>
                <td colspan="3" style="text-align:right;">
                    Inv: ${p(r)}<br />
                    Date: ${p(i)}
                </td>
            </tr>
            <tr class="tabletitle">
                <td>Product</td>
                <td>Variant</td>
                <td style="text-align:right">Rate</td>
                <td style="text-align:right">Qty</td>
                <td style="text-align:right">Amount</td>
            </tr>
            ${te}
            <tr class="totals">
                <td colspan="4">Subtotal</td>
                <td>${m(s.gross)}</td>
            </tr>
            <tr class="totals">
                <td colspan="4">Vat</td>
                <td>${m(s.vat)}</td>
            </tr>
            <tr class="totals">
                <td colspan="4">Discount</td>
                <td>${m(s.discount)}</td>
            </tr>
            <tr class="totals">
                <td colspan="4">Payable Amount</td>
                <td>${m(s.net)}</td>
            </tr>
            <tr class="totals">
                <td colspan="4">Total Paid</td>
                <td>${m(s.paid)}</td>
            </tr>
            <tr class="totals">
                <td colspan="4">Due</td>
                <td>${m(s.due)}</td>
            </tr>
            ${re>0?`<tr class="totals">
                <td colspan="4">Change Amount</td>
                <td>${m(re)}</td>
            </tr>`:``}
        </table>
        <p class="footer">${p(c)}</p>
    </div>
</body>
</html>`,f=window.open(``,`_blank`,`width=300,height=600`);if(!f){alert(`Please allow popups to print the POS receipt.`);return}f.document.write(d),f.document.close(),setTimeout(()=>{f.print(),setTimeout(()=>f.close(),1e3)},500)}catch(e){console.error(`POS print failed:`,e),alert(`Failed to print POS receipt.`)}}function ye(e,{companyName:t,logoUrl:n,branchName:r}){let i=parseFloat(e.gross_amount??0),a=parseFloat(e.vat??0),o=parseFloat(e.discount??0),s=(e.products??[]).reduce((e,t)=>e+parseFloat(t.discount??0),0),c=i+a-o-s,l=parseFloat(e.paid_amount??0),u=Math.max(0,c-l);return{companyName:t||`Coolness Point`,logoUrl:n,branchName:r||e.branch?.name||``,invoiceNumber:e.invoice_number??`INVS${String(e.id).padStart(8,`0`)}`,date:e.date??``,customer:e.customer??null,products:(e.products??[]).map(e=>{let t=parseFloat(e.quantity??0),n=parseFloat(e.unit_price??0),r=parseFloat(e.discount??0);return{name:e.product?.name??`—`,variant:e.variation?.variation_data?.label??e.variation?.sku_code??``,rate:n,qty:t,amount:n*t-r}}),totals:{gross:i,vat:a,discount:o+s,net:c,paid:l,due:u}}}var g=t(e(),1),_=n();function v(e){let t=(0,_e.c)(71),{sell:n}=e,{flash:r,logo:p}=i().props,m=re(),{can:h}=de(),[v,xe]=(0,g.useState)(!1),Se=(0,g.useRef)(!1),y;t[0]!==r.error||t[1]!==r.success||t[2]!==m?(y=()=>{r.success&&m.success(r.success),r.error&&m.error(r.error)},t[0]=r.error,t[1]=r.success,t[2]=m,t[3]=y):y=t[3];let b;t[4]!==r.error||t[5]!==r.success?(b=[r.success,r.error],t[4]=r.error,t[5]=r.success,t[6]=b):b=t[6],(0,g.useEffect)(y,b);let x;t[7]!==n.id||t[8]!==n.invoice_number?(x=n.invoice_number??`INVS${String(n.id).padStart(8,`0`)}`,t[7]=n.id,t[8]=n.invoice_number,t[9]=x):x=t[9];let S=x,C;t[10]===n.products?C=t[11]:(C=n.products??[],t[10]=n.products,t[11]=C);let w=C.reduce(be,0),T=parseFloat(n.gross_amount??0),E=parseFloat(n.vat??0),D=parseFloat(n.discount??0),O=T+E-D-w,k=parseFloat(n.paid_amount??0),A=Math.max(0,O-k),j;t[12]===Symbol.for(`react.memo_cache_sentinel`)?(j=pe(),t[12]=j):j=t[12];let Ce=j,M;t[13]!==p||t[14]!==n?(M=()=>{ve(ye(n,{companyName:n.branch?.name||`Coolness Point`,logoUrl:p,branchName:n.branch?.name}))},t[13]=p,t[14]=n,t[15]=M):M=t[15];let N=M,P,F;t[16]===N?(P=t[17],F=t[18]):(P=()=>{let e=new URLSearchParams(window.location.search);if(!(e.get(`pos_print`)===`1`||e.get(`pos_print`)===`true`)||Se.current)return;Se.current=!0;let t=setTimeout(()=>N(),500);return()=>clearTimeout(t)},F=[N],t[16]=N,t[17]=P,t[18]=F),(0,g.useEffect)(P,F);let I;t[19]===n.id?I=t[20]:(I=function(){s.delete(f(`inventory.sell.destroy`,n.id),{onSuccess:()=>xe(!1)})},t[19]=n.id,t[20]=I);let we=I,Te=`Sale — ${S}`,L;t[21]===Te?L=t[22]:(L=(0,_.jsx)(a,{title:Te}),t[21]=Te,t[22]=L);let R;t[23]===Symbol.for(`react.memo_cache_sentinel`)?(R=(0,_.jsx)(l,{className:`size-3.5`}),t[23]=R):R=t[23];let z;t[24]===N?z=t[25]:(z=(0,_.jsxs)(d,{size:`sm`,onClick:N,className:Ce,children:[R,`POS Print`]}),t[24]=N,t[25]=z);let B;t[26]===n.id?B=t[27]:(B=f(`inventory.sell.edit`,n.id),t[26]=n.id,t[27]=B);let V;t[28]===Symbol.for(`react.memo_cache_sentinel`)?(V=(0,_.jsx)(u,{className:`size-3.5`}),t[28]=V):V=t[28];let H;t[29]===B?H=t[30]:(H=(0,_.jsx)(fe,{permission:`inventory.sell.update`,children:(0,_.jsx)(d,{size:`sm`,asChild:!0,className:Ce,children:(0,_.jsxs)(o,{href:B,children:[V,`Edit`]})})}),t[29]=B,t[30]=H);let U;t[31]===Symbol.for(`react.memo_cache_sentinel`)?(U=()=>xe(!0),t[31]=U):U=t[31];let W;t[32]===Symbol.for(`react.memo_cache_sentinel`)?(W=(0,_.jsx)(fe,{permission:`inventory.sell.delete`,children:(0,_.jsxs)(d,{size:`sm`,variant:`destructive`,onClick:U,className:`border border-red-500/50 bg-red-600/90 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-600 hover:shadow-md`,children:[(0,_.jsx)(ee,{className:`size-3.5`}),`Delete`]})}),t[32]=W):W=t[32];let G;t[33]===Symbol.for(`react.memo_cache_sentinel`)?(G=(0,_.jsx)(d,{size:`sm`,asChild:!0,className:Ce,children:(0,_.jsxs)(o,{href:f(`inventory.sell.index`),children:[(0,_.jsx)(c,{className:`size-3.5`}),`Back`]})}),t[33]=G):G=t[33];let K;t[34]!==S||t[35]!==z||t[36]!==H?(K=(0,_.jsxs)(me,{icon:te,title:`Sale Invoice`,invoiceNumber:S,children:[z,H,W,G]}),t[34]=S,t[35]=z,t[36]=H,t[37]=K):K=t[37];let Ee=n.branch?.name,q;t[38]===n.products?q=t[39]:(q=n.products??[],t[38]=n.products,t[39]=q);let J;t[40]!==D||t[41]!==A||t[42]!==T||t[43]!==w||t[44]!==O||t[45]!==k||t[46]!==E?(J={gross:T,vat:E,discount:D,lineDiscount:w,net:O,paid:k,due:A},t[40]=D,t[41]=A,t[42]=T,t[43]=w,t[44]=O,t[45]=k,t[46]=E,t[47]=J):J=t[47];let De=n.customer?.name,Oe=n.customer?.phone,ke=n.customer?.address,Y;t[48]!==De||t[49]!==Oe||t[50]!==ke?(Y=(0,_.jsx)(he,{icon:ne,label:`Customer`,name:De,phone:Oe,address:ke,emptyText:`Walk-in Customer`}),t[48]=De,t[49]=Oe,t[50]=ke,t[51]=Y):Y=t[51];let X;t[52]!==S||t[53]!==n.comment||t[54]!==n.date||t[55]!==Ee||t[56]!==q||t[57]!==J||t[58]!==Y?(X=(0,_.jsx)(ge,{docTitle:`Sale Invoice`,invoiceNumber:S,date:n.date,branchName:Ee,items:q,totals:J,comment:n.comment,partySection:Y}),t[52]=S,t[53]=n.comment,t[54]=n.date,t[55]=Ee,t[56]=q,t[57]=J,t[58]=Y,t[59]=X):X=t[59];let Z;t[60]!==h||t[61]!==v||t[62]!==we?(Z=h(`inventory.sell.delete`)&&(0,_.jsx)(ue,{open:v,onOpenChange:xe,children:(0,_.jsxs)(ce,{className:`max-w-sm`,children:[(0,_.jsxs)(se,{children:[(0,_.jsx)(le,{children:`Delete sale?`}),(0,_.jsx)(ae,{children:`This will permanently delete the sale and restore stock.`})]}),(0,_.jsxs)(ie,{className:`mt-4 gap-2`,children:[(0,_.jsx)(oe,{asChild:!0,children:(0,_.jsx)(d,{type:`button`,variant:`outline`,size:`sm`,children:`Cancel`})}),(0,_.jsx)(d,{type:`button`,variant:`destructive`,size:`sm`,onClick:we,children:`Delete`})]})]})}),t[60]=h,t[61]=v,t[62]=we,t[63]=Z):Z=t[63];let Q;t[64]!==K||t[65]!==X||t[66]!==Z?(Q=(0,_.jsxs)(`div`,{className:`px-2 py-1`,children:[K,X,Z]}),t[64]=K,t[65]=X,t[66]=Z,t[67]=Q):Q=t[67];let $;return t[68]!==L||t[69]!==Q?($=(0,_.jsxs)(_.Fragment,{children:[L,Q]}),t[68]=L,t[69]=Q,t[70]=$):$=t[70],$}function be(e,t){return e+parseFloat(t.discount??0)}export{v as default};