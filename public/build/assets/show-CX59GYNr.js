import{n as e,s as t,t as n}from"./jsx-runtime-6_rQPNQF.js";import{t as r}from"./compiler-runtime-BEUpNReH.js";import{c as i,n as a,r as o,u as s}from"./dist-CcZGCiug.js";import{t as c}from"./arrow-left-C7XwQ9dW.js";import{t as l}from"./receipt-BcSRNyav.js";import{t as u}from"./square-pen-B5eICVUN.js";import{t as d}from"./trash-2-CLN4SPLY.js";import{F as ee,N as te,O as ne,v as f,x as p}from"./app-v0D_6OAV.js";import{a as re,i as ie,n as ae,o as oe,r as se,s as ce,t as le}from"./dialog-CjFBgyPQ.js";import{t as ue}from"./use-can-CVMgppTm.js";import{t as de}from"./can-CyxJvpxN.js";import{i as fe,n as pe,r as me,t as he}from"./invoice-show-layout-CN0hGxG7.js";var ge=r();function m(e){return String(e??``).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`).replace(/'/g,`&#39;`)}function h(e){return parseFloat(e??0).toFixed(2)}function g(e){let t=parseFloat(e??0);return Number.isInteger(t)?String(t):t.toFixed(2)}function _e({companyName:e,logoUrl:t,branchName:n,invoiceNumber:r,date:i,customer:a,products:o,totals:s,footer:c=`Powered by Coolness Point`}){try{let l=a?.name?.trim()||`Walk-in Customer`,u=a?.phone?.trim()||``,d=a?.address?.trim()||``,ee=o.map(e=>`
            <tr class="service">
                <td>${m(e.name)}</td>
                <td>${m(e.variant||``)}</td>
                <td style="text-align:right">${h(e.rate)}</td>
                <td style="text-align:right">${g(e.qty)}</td>
                <td style="text-align:right">${h(e.amount)}</td>
            </tr>`).join(``),te=t?`<img src="${m(t)}" alt="${m(e)}" style="max-height:48px;max-width:200px;margin:0 auto 6px;display:block;" />`:``,ne=parseFloat(s.change??Math.max(0,parseFloat(s.paid)-parseFloat(s.net))),f=`<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>${m(r)}</title>
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
        ${te}
        <p class="company-name">${m(e)}</p>
        ${n?`<p class="branch-name">${m(n)}</p>`:``}
        <table style="width:100%;">
            <tr class="tableitem2">
                <td colspan="2">
                    Name: ${m(l)}<br />
                    ${u?`Phone: ${m(u)}<br />`:``}
                    ${d?`Address: ${m(d)}`:``}
                </td>
                <td colspan="3" style="text-align:right;">
                    Inv: ${m(r)}<br />
                    Date: ${m(i)}
                </td>
            </tr>
            <tr class="tabletitle">
                <td>Product</td>
                <td>Variant</td>
                <td style="text-align:right">Rate</td>
                <td style="text-align:right">Qty</td>
                <td style="text-align:right">Amount</td>
            </tr>
            ${ee}
            <tr class="totals">
                <td colspan="4">Subtotal</td>
                <td>${h(s.gross)}</td>
            </tr>
            <tr class="totals">
                <td colspan="4">Vat</td>
                <td>${h(s.vat)}</td>
            </tr>
            <tr class="totals">
                <td colspan="4">Discount</td>
                <td>${h(s.discount)}</td>
            </tr>
            <tr class="totals">
                <td colspan="4">Payable Amount</td>
                <td>${h(s.net)}</td>
            </tr>
            <tr class="totals">
                <td colspan="4">Total Paid</td>
                <td>${h(s.paid)}</td>
            </tr>
            <tr class="totals">
                <td colspan="4">Due</td>
                <td>${h(s.due)}</td>
            </tr>
            ${ne>0?`<tr class="totals">
                <td colspan="4">Change Amount</td>
                <td>${h(ne)}</td>
            </tr>`:``}
        </table>
        <p class="footer">${m(c)}</p>
    </div>
</body>
</html>`,p=window.open(``,`_blank`,`width=300,height=600`);if(!p){alert(`Please allow popups to print the POS receipt.`);return}p.document.write(f),p.document.close(),setTimeout(()=>{p.print(),setTimeout(()=>p.close(),1e3)},500)}catch(e){console.error(`POS print failed:`,e),alert(`Failed to print POS receipt.`)}}function ve(e,{companyName:t,logoUrl:n,branchName:r}){let i=parseFloat(e.gross_amount??0),a=parseFloat(e.vat??0),o=parseFloat(e.discount??0),s=parseFloat(e.special_discount_amount??0),c=(e.products??[]).reduce((e,t)=>e+parseFloat(t.discount??0),0),l=i+a-o-s-c,u=parseFloat(e.paid_amount??0),d=Math.max(0,l-u);return{companyName:t||`Coolness Point`,logoUrl:n,branchName:r||e.branch?.name||``,invoiceNumber:e.invoice_number??`INVS${String(e.id).padStart(8,`0`)}`,date:e.date??``,customer:e.customer??null,products:(e.products??[]).map(e=>{let t=parseFloat(e.quantity??0),n=parseFloat(e.unit_price??0),r=parseFloat(e.discount??0);return{name:e.product?.name??`—`,variant:e.variation?.variation_data?.label??e.variation?.sku_code??``,rate:n,qty:t,amount:n*t-r}}),totals:{gross:i,vat:a,discount:o+s+c,net:l,paid:u,due:d,specialDiscountName:e.special_discount?.name??null}}}var _=t(e(),1),v=n();function y(e){let t=(0,ge.c)(77),{sell:n}=e,{flash:r,logo:m}=i().props,h=ne(),{can:g}=ue(),[y,be]=(0,_.useState)(!1),xe=(0,_.useRef)(!1),b;t[0]!==r.error||t[1]!==r.success||t[2]!==h?(b=()=>{r.success&&h.success(r.success),r.error&&h.error(r.error)},t[0]=r.error,t[1]=r.success,t[2]=h,t[3]=b):b=t[3];let x;t[4]!==r.error||t[5]!==r.success?(x=[r.success,r.error],t[4]=r.error,t[5]=r.success,t[6]=x):x=t[6],(0,_.useEffect)(b,x);let S;t[7]!==n.id||t[8]!==n.invoice_number?(S=n.invoice_number??`INVS${String(n.id).padStart(8,`0`)}`,t[7]=n.id,t[8]=n.invoice_number,t[9]=S):S=t[9];let C=S,w;t[10]===n.products?w=t[11]:(w=n.products??[],t[10]=n.products,t[11]=w);let T=w.reduce(ye,0),E=parseFloat(n.gross_amount??0),D=parseFloat(n.vat??0),O=parseFloat(n.discount??0),k=parseFloat(n.special_discount_amount??0),A=E+D-O-k-T,j=parseFloat(n.paid_amount??0),Se=Math.max(0,A-j),M;t[12]===Symbol.for(`react.memo_cache_sentinel`)?(M=fe(),t[12]=M):M=t[12];let Ce=M,N;t[13]!==m||t[14]!==n?(N=()=>{_e(ve(n,{companyName:n.branch?.name||`Coolness Point`,logoUrl:m,branchName:n.branch?.name}))},t[13]=m,t[14]=n,t[15]=N):N=t[15];let P=N,F,I;t[16]===P?(F=t[17],I=t[18]):(F=()=>{let e=new URLSearchParams(window.location.search);if(!(e.get(`pos_print`)===`1`||e.get(`pos_print`)===`true`)||xe.current)return;xe.current=!0;let t=setTimeout(()=>P(),500);return()=>clearTimeout(t)},I=[P],t[16]=P,t[17]=F,t[18]=I),(0,_.useEffect)(F,I);let L;t[19]===n.id?L=t[20]:(L=function(){s.delete(p(`inventory.sell.destroy`,n.id),{onSuccess:()=>be(!1)})},t[19]=n.id,t[20]=L);let we=L,Te=`Sale — ${C}`,R;t[21]===Te?R=t[22]:(R=(0,v.jsx)(a,{title:Te}),t[21]=Te,t[22]=R);let z;t[23]===Symbol.for(`react.memo_cache_sentinel`)?(z=(0,v.jsx)(l,{className:`size-3.5`}),t[23]=z):z=t[23];let B;t[24]===P?B=t[25]:(B=(0,v.jsxs)(f,{size:`sm`,onClick:P,className:Ce,children:[z,`POS Print`]}),t[24]=P,t[25]=B);let V;t[26]===n.id?V=t[27]:(V=p(`inventory.sell.edit`,n.id),t[26]=n.id,t[27]=V);let H;t[28]===Symbol.for(`react.memo_cache_sentinel`)?(H=(0,v.jsx)(u,{className:`size-3.5`}),t[28]=H):H=t[28];let U;t[29]===V?U=t[30]:(U=(0,v.jsx)(de,{permission:`inventory.sell.update`,children:(0,v.jsx)(f,{size:`sm`,asChild:!0,className:Ce,children:(0,v.jsxs)(o,{href:V,children:[H,`Edit`]})})}),t[29]=V,t[30]=U);let W;t[31]===Symbol.for(`react.memo_cache_sentinel`)?(W=()=>be(!0),t[31]=W):W=t[31];let G;t[32]===Symbol.for(`react.memo_cache_sentinel`)?(G=(0,v.jsx)(de,{permission:`inventory.sell.delete`,children:(0,v.jsxs)(f,{size:`sm`,variant:`destructive`,onClick:W,className:`border border-red-500/50 bg-red-600/90 text-white backdrop-blur-sm transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-600 hover:shadow-md`,children:[(0,v.jsx)(d,{className:`size-3.5`}),`Delete`]})}),t[32]=G):G=t[32];let Ee;t[33]===Symbol.for(`react.memo_cache_sentinel`)?(Ee=(0,v.jsx)(f,{size:`sm`,asChild:!0,className:Ce,children:(0,v.jsxs)(o,{href:p(`inventory.sell.index`),children:[(0,v.jsx)(c,{className:`size-3.5`}),`Back`]})}),t[33]=Ee):Ee=t[33];let K;t[34]!==C||t[35]!==B||t[36]!==U?(K=(0,v.jsxs)(pe,{icon:ee,title:`Sale Invoice`,invoiceNumber:C,children:[B,U,G,Ee]}),t[34]=C,t[35]=B,t[36]=U,t[37]=K):K=t[37];let De=n.branch?.name,q;t[38]===n.products?q=t[39]:(q=n.products??[],t[38]=n.products,t[39]=q);let Oe=n.special_discount?.name,ke=n.special_discount?.discount_type,Ae=n.special_discount?.discount_value,J;t[40]!==O||t[41]!==Se||t[42]!==E||t[43]!==T||t[44]!==A||t[45]!==j||t[46]!==n.discount_type||t[47]!==n.discount_value||t[48]!==k||t[49]!==Oe||t[50]!==ke||t[51]!==Ae||t[52]!==D?(J={gross:E,vat:D,discount:O,discountType:n.discount_type,discountValue:n.discount_value,specialDiscount:k,specialDiscountName:Oe,specialDiscountType:ke,specialDiscountValue:Ae,lineDiscount:T,net:A,paid:j,due:Se},t[40]=O,t[41]=Se,t[42]=E,t[43]=T,t[44]=A,t[45]=j,t[46]=n.discount_type,t[47]=n.discount_value,t[48]=k,t[49]=Oe,t[50]=ke,t[51]=Ae,t[52]=D,t[53]=J):J=t[53];let je=n.customer?.name,Me=n.customer?.phone,Ne=n.customer?.address,Y;t[54]!==je||t[55]!==Me||t[56]!==Ne?(Y=(0,v.jsx)(me,{icon:te,label:`Customer`,name:je,phone:Me,address:Ne,emptyText:`Walk-in Customer`}),t[54]=je,t[55]=Me,t[56]=Ne,t[57]=Y):Y=t[57];let X;t[58]!==C||t[59]!==n.comment||t[60]!==n.date||t[61]!==De||t[62]!==q||t[63]!==J||t[64]!==Y?(X=(0,v.jsx)(he,{docTitle:`Sale Invoice`,invoiceNumber:C,date:n.date,branchName:De,items:q,totals:J,comment:n.comment,partySection:Y}),t[58]=C,t[59]=n.comment,t[60]=n.date,t[61]=De,t[62]=q,t[63]=J,t[64]=Y,t[65]=X):X=t[65];let Z;t[66]!==g||t[67]!==y||t[68]!==we?(Z=g(`inventory.sell.delete`)&&(0,v.jsx)(le,{open:y,onOpenChange:be,children:(0,v.jsxs)(se,{className:`max-w-sm`,children:[(0,v.jsxs)(oe,{children:[(0,v.jsx)(ce,{children:`Delete sale?`}),(0,v.jsx)(ie,{children:`This will permanently delete the sale and restore stock.`})]}),(0,v.jsxs)(re,{className:`mt-4 gap-2`,children:[(0,v.jsx)(ae,{asChild:!0,children:(0,v.jsx)(f,{type:`button`,variant:`outline`,size:`sm`,children:`Cancel`})}),(0,v.jsx)(f,{type:`button`,variant:`destructive`,size:`sm`,onClick:we,children:`Delete`})]})]})}),t[66]=g,t[67]=y,t[68]=we,t[69]=Z):Z=t[69];let Q;t[70]!==K||t[71]!==X||t[72]!==Z?(Q=(0,v.jsxs)(`div`,{className:`px-2 py-1`,children:[K,X,Z]}),t[70]=K,t[71]=X,t[72]=Z,t[73]=Q):Q=t[73];let $;return t[74]!==R||t[75]!==Q?($=(0,v.jsxs)(v.Fragment,{children:[R,Q]}),t[74]=R,t[75]=Q,t[76]=$):$=t[76],$}function ye(e,t){return e+parseFloat(t.discount??0)}export{y as default};