import{n as e,s as t,t as n}from"./jsx-runtime-6_rQPNQF.js";import{t as r}from"./compiler-runtime-BEUpNReH.js";import{c as i,n as a,r as o,u as s}from"./dist-CcZGCiug.js";import{t as c}from"./arrow-left-C7XwQ9dW.js";import{t as l}from"./receipt-HY9fQUoA.js";import{t as ee}from"./square-pen-DnW0UXPL.js";import{t as te}from"./trash-2-CBn2aKYk.js";import{F as ne,N as re,O as u,v as d,x as f}from"./app-BZOypkbC.js";import{a as ie,i as ae,n as oe,o as se,r as ce,s as le,t as ue}from"./dialog-CoWuJo7y.js";import{t as de}from"./use-can-C7fQiB3p.js";import{t as fe}from"./can-C7pjUPWU.js";import{i as pe,n as me,r as he,t as ge}from"./invoice-show-layout--dX7F3xT.js";var _e=r();function p(e){return String(e??``).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`).replace(/'/g,`&#39;`)}function m(e){return parseFloat(e??0).toFixed(2)}function h(e){let t=parseFloat(e??0);return Number.isInteger(t)?String(t):t.toFixed(2)}function ve({companyName:e,logoUrl:t,branchName:n,invoiceNumber:r,date:i,customer:a,products:o,totals:s,footer:c=`Powered by Coolness Point`}){try{let l=a?.name?.trim()||`Walk-in Customer`,ee=a?.phone?.trim()||``,te=a?.address?.trim()||``,ne=o.map(e=>`
            <tr class="service">
                <td>${p(e.name)}</td>
                <td>${p(e.variant||``)}</td>
                <td style="text-align:right">${m(e.rate)}</td>
                <td style="text-align:right">${h(e.qty)}</td>
                <td style="text-align:right">${m(e.amount)}</td>
            </tr>`).join(``),re=t?`<img src="${p(t)}" alt="${p(e)}" style="max-height:48px;max-width:200px;margin:0 auto 6px;display:block;" />`:``,u=parseFloat(s.change??Math.max(0,parseFloat(s.paid)-parseFloat(s.net))),d=`<!DOCTYPE html>
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
        ${re}
        <p class="company-name">${p(e)}</p>
        ${n?`<p class="branch-name">${p(n)}</p>`:``}
        <table style="width:100%;">
            <tr class="tableitem2">
                <td colspan="2">
                    Name: ${p(l)}<br />
                    ${ee?`Phone: ${p(ee)}<br />`:``}
                    ${te?`Address: ${p(te)}`:``}
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
            ${ne}
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
            ${u>0?`<tr class="totals">
                <td colspan="4">Change Amount</td>
                <td>${m(u)}</td>
            </tr>`:``}
        </table>
        <p class="footer">${p(c)}</p>
    </div>
</body>
</html>`,f=window.open(``,`_blank`,`width=300,height=600`);if(!f){alert(`Please allow popups to print the POS receipt.`);return}f.document.write(d),f.document.close(),setTimeout(()=>{f.print(),setTimeout(()=>f.close(),1e3)},500)}catch(e){console.error(`POS print failed:`,e),alert(`Failed to print POS receipt.`)}}function ye(e,{companyName:t,logoUrl:n,branchName:r}){let i=parseFloat(e.gross_amount??0),a=parseFloat(e.vat??0),o=parseFloat(e.discount??0),s=i+a-o,c=parseFloat(e.paid_amount??0),l=Math.max(0,s-c);return{companyName:t||`Coolness Point`,logoUrl:n,branchName:r||e.branch?.name||``,invoiceNumber:e.invoice_number??`INVS${String(e.id).padStart(8,`0`)}`,date:e.date??``,customer:e.customer??null,products:(e.products??[]).map(e=>{let t=parseFloat(e.quantity??0),n=parseFloat(e.unit_price??0);return{name:e.product?.name??`—`,variant:e.variation?.variation_data?.label??e.variation?.sku_code??``,rate:n,qty:t,amount:n*t}}),totals:{gross:i,vat:a,discount:o,net:s,paid:c,due:l}}}var g=t(e(),1),_=n();function v(e){let t=(0,_e.c)(68),{sell:n}=e,{flash:r,logo:p}=i().props,m=u(),{can:h}=de(),[v,y]=(0,g.useState)(!1),be=(0,g.useRef)(!1),b;t[0]!==r.error||t[1]!==r.success||t[2]!==m?(b=()=>{r.success&&m.success(r.success),r.error&&m.error(r.error)},t[0]=r.error,t[1]=r.success,t[2]=m,t[3]=b):b=t[3];let x;t[4]!==r.error||t[5]!==r.success?(x=[r.success,r.error],t[4]=r.error,t[5]=r.success,t[6]=x):x=t[6],(0,g.useEffect)(b,x);let S;t[7]!==n.id||t[8]!==n.invoice_number?(S=n.invoice_number??`INVS${String(n.id).padStart(8,`0`)}`,t[7]=n.id,t[8]=n.invoice_number,t[9]=S):S=t[9];let C=S,w=parseFloat(n.gross_amount??0),T=parseFloat(n.vat??0),E=parseFloat(n.discount??0),D=w+T-E,O=parseFloat(n.paid_amount??0),k=Math.max(0,D-O),A;t[10]===Symbol.for(`react.memo_cache_sentinel`)?(A=pe(),t[10]=A):A=t[10];let j=A,M;t[11]!==p||t[12]!==n?(M=()=>{ve(ye(n,{companyName:n.branch?.name||`Coolness Point`,logoUrl:p,branchName:n.branch?.name}))},t[11]=p,t[12]=n,t[13]=M):M=t[13];let N=M,P,F;t[14]===N?(P=t[15],F=t[16]):(P=()=>{let e=new URLSearchParams(window.location.search);if(!(e.get(`pos_print`)===`1`||e.get(`pos_print`)===`true`)||be.current)return;be.current=!0;let t=setTimeout(()=>N(),500);return()=>clearTimeout(t)},F=[N],t[14]=N,t[15]=P,t[16]=F),(0,g.useEffect)(P,F);let I;t[17]===n.id?I=t[18]:(I=function(){s.delete(f(`inventory.sell.destroy`,n.id),{onSuccess:()=>y(!1)})},t[17]=n.id,t[18]=I);let xe=I,Se=`Sale — ${C}`,L;t[19]===Se?L=t[20]:(L=(0,_.jsx)(a,{title:Se}),t[19]=Se,t[20]=L);let R;t[21]===Symbol.for(`react.memo_cache_sentinel`)?(R=(0,_.jsx)(l,{className:`size-3.5`}),t[21]=R):R=t[21];let z;t[22]===N?z=t[23]:(z=(0,_.jsxs)(d,{size:`sm`,onClick:N,className:j,children:[R,`POS Print`]}),t[22]=N,t[23]=z);let B;t[24]===n.id?B=t[25]:(B=f(`inventory.sell.edit`,n.id),t[24]=n.id,t[25]=B);let V;t[26]===Symbol.for(`react.memo_cache_sentinel`)?(V=(0,_.jsx)(ee,{className:`size-3.5`}),t[26]=V):V=t[26];let H;t[27]===B?H=t[28]:(H=(0,_.jsx)(fe,{permission:`inventory.sell.update`,children:(0,_.jsx)(d,{size:`sm`,asChild:!0,className:j,children:(0,_.jsxs)(o,{href:B,children:[V,`Edit`]})})}),t[27]=B,t[28]=H);let U;t[29]===Symbol.for(`react.memo_cache_sentinel`)?(U=()=>y(!0),t[29]=U):U=t[29];let W;t[30]===Symbol.for(`react.memo_cache_sentinel`)?(W=(0,_.jsx)(fe,{permission:`inventory.sell.delete`,children:(0,_.jsxs)(d,{size:`sm`,variant:`destructive`,onClick:U,className:`border border-red-500/50 bg-red-600/90 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-600 hover:shadow-md`,children:[(0,_.jsx)(te,{className:`size-3.5`}),`Delete`]})}),t[30]=W):W=t[30];let G;t[31]===Symbol.for(`react.memo_cache_sentinel`)?(G=(0,_.jsx)(d,{size:`sm`,asChild:!0,className:j,children:(0,_.jsxs)(o,{href:f(`inventory.sell.index`),children:[(0,_.jsx)(c,{className:`size-3.5`}),`Back`]})}),t[31]=G):G=t[31];let K;t[32]!==C||t[33]!==z||t[34]!==H?(K=(0,_.jsxs)(me,{icon:ne,title:`Sale Invoice`,invoiceNumber:C,children:[z,H,W,G]}),t[32]=C,t[33]=z,t[34]=H,t[35]=K):K=t[35];let Ce=n.branch?.name,q;t[36]===n.products?q=t[37]:(q=n.products??[],t[36]=n.products,t[37]=q);let J;t[38]!==E||t[39]!==k||t[40]!==w||t[41]!==D||t[42]!==O||t[43]!==T?(J={gross:w,vat:T,discount:E,net:D,paid:O,due:k},t[38]=E,t[39]=k,t[40]=w,t[41]=D,t[42]=O,t[43]=T,t[44]=J):J=t[44];let we=n.customer?.name,Te=n.customer?.phone,Ee=n.customer?.address,Y;t[45]!==we||t[46]!==Te||t[47]!==Ee?(Y=(0,_.jsx)(he,{icon:re,label:`Customer`,name:we,phone:Te,address:Ee,emptyText:`Walk-in Customer`}),t[45]=we,t[46]=Te,t[47]=Ee,t[48]=Y):Y=t[48];let X;t[49]!==C||t[50]!==n.comment||t[51]!==n.date||t[52]!==Ce||t[53]!==q||t[54]!==J||t[55]!==Y?(X=(0,_.jsx)(ge,{docTitle:`Sale Invoice`,invoiceNumber:C,date:n.date,branchName:Ce,items:q,totals:J,comment:n.comment,partySection:Y}),t[49]=C,t[50]=n.comment,t[51]=n.date,t[52]=Ce,t[53]=q,t[54]=J,t[55]=Y,t[56]=X):X=t[56];let Z;t[57]!==h||t[58]!==v||t[59]!==xe?(Z=h(`inventory.sell.delete`)&&(0,_.jsx)(ue,{open:v,onOpenChange:y,children:(0,_.jsxs)(ce,{className:`max-w-sm`,children:[(0,_.jsxs)(se,{children:[(0,_.jsx)(le,{children:`Delete sale?`}),(0,_.jsx)(ae,{children:`This will permanently delete the sale and restore stock.`})]}),(0,_.jsxs)(ie,{className:`mt-4 gap-2`,children:[(0,_.jsx)(oe,{asChild:!0,children:(0,_.jsx)(d,{type:`button`,variant:`outline`,size:`sm`,children:`Cancel`})}),(0,_.jsx)(d,{type:`button`,variant:`destructive`,size:`sm`,onClick:xe,children:`Delete`})]})]})}),t[57]=h,t[58]=v,t[59]=xe,t[60]=Z):Z=t[60];let Q;t[61]!==K||t[62]!==X||t[63]!==Z?(Q=(0,_.jsxs)(`div`,{className:`px-2 py-1`,children:[K,X,Z]}),t[61]=K,t[62]=X,t[63]=Z,t[64]=Q):Q=t[64];let $;return t[65]!==L||t[66]!==Q?($=(0,_.jsxs)(_.Fragment,{children:[L,Q]}),t[65]=L,t[66]=Q,t[67]=$):$=t[67],$}export{v as default};