import{c as e,n as t,r as n,t as r}from"./jsx-runtime-Tg1yNRQt.js";import{t as i}from"./createLucideIcon-DKh52u_-.js";var a=i(`Printer`,[[`path`,{d:`M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2`,key:`143wyd`}],[`path`,{d:`M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6`,key:`1itne7`}],[`rect`,{x:`6`,y:`14`,width:`12`,height:`8`,rx:`1`,key:`1ue0tg`}]]),o=e(n(),1),s=t();function c(e){return Math.max(6,Math.ceil(e*.5))}function l(){return 1}function u(e){if(e?.variation?.price!=null)return parseFloat(e.variation.price);if(!e?.product)return null;let t=parseFloat(e.product.discount_price??0),n=parseFloat(e.product.sale_price??0);return t>0?t:n}function d(e){return`Price: ${(e==null?0:Number(e)).toFixed(2)}`}function f(e){return e?.product?.name??e?.name??`Product Name`}function p(e){let t=f(e);return e?.code?`${t} - ${e.code}`:t}function m(e){let{height:t,fontSize:n}=e,r=t*96,i=Math.ceil(n*1.2)+1,a=c(n),o=n+l(),s=r-8-i-a-o,u=Math.max(n*3,20);return Math.max(12,Math.min(u,s))}function h(e,t,n){if(!e||!t)return n;let r=t.clientWidth;e.style.transform=`none`,e.style.width=`auto`,e.style.display=`inline-block`,e.style.fontSize=`${n}px`;let i=e.scrollWidth;if(i>r&&r>0){let t=r/i,a=Math.max(10,Math.floor(n*t));e.style.fontSize=`${a}px`}return e.offsetHeight||n}function g(e,t){let{width:n,height:r,fontSize:i,fontWeight:a,copies:o}=t,s=a===`bold`?700:400,f=m(t);return`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Barcode Labels</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { width: 100%; background: white; }
    .page { display: flex; flex-wrap: wrap; }
    .label {
      width: ${n}in;
      height: ${r}in;
      border: 1px solid #ccc;
      display: flex;
      align-items: stretch;
      justify-content: center;
      padding: 4px 6px;
      overflow: hidden;
      page-break-inside: avoid;
    }
    .label-inner {
      width: 100%;
      min-height: 0;
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 0;
    }
    .name {
      font-size: ${i}px;
      font-weight: ${s};
      font-family: sans-serif;
      text-align: center;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      line-height: 1.2;
      flex-shrink: 0;
      margin-bottom: ${c(i)}px;
    }
    .bars-wrap {
      width: 100%;
      flex: 0 0 auto;
      display: flex;
      align-items: flex-end;
      justify-content: center;
      overflow: hidden;
    }
    .bars {
      font-family: 'Libre Barcode 128', monospace;
      font-weight: ${s};
      font-size: ${f}px;
      line-height: 1;
      white-space: nowrap;
      display: inline-block;
      max-width: 100%;
    }
    .footer {
      display: flex;
      justify-content: center;
      font-size: ${i}px;
      font-weight: ${s};
      font-family: monospace;
      line-height: 1;
      flex-shrink: 0;
      margin-top: ${l()}px;
    }
    @media print {
      @page { margin: 0; }
      body { margin: 0; }
      .label { border: none; }
    }
  </style>
</head>
<body>
  <div class="page">${e.flatMap(e=>Array.from({length:o},()=>e)).map(e=>{let t=u(e)??0;return`
      <div class="label">
        <div class="label-inner">
          <div class="name">${p(e)}</div>
          <div class="bars-wrap" style="height:${f}px">
            <div class="bars" data-bar-height="${f}">${e.code}</div>
          </div>
          <div class="footer">
            <span>${d(t)}</span>
          </div>
        </div>
      </div>`}).join(``)}</div>
  <script>
    function fitBarcode(wrap) {
      var el = wrap.querySelector('.bars');
      if (!el) return;
      var baseHeight = parseFloat(el.getAttribute('data-bar-height') || '${f}');
      var w = wrap.clientWidth;
      el.style.transform = 'none';
      el.style.width = 'auto';
      el.style.display = 'inline-block';
      el.style.fontSize = baseHeight + 'px';
      var tw = el.scrollWidth;
      if (tw > w && w > 0) {
        var scale = w / tw;
        el.style.fontSize = Math.max(10, Math.floor(baseHeight * scale)) + 'px';
      }
      wrap.style.height = Math.max(el.offsetHeight, 12) + 'px';
    }

    function printWhenReady() {
      document.querySelectorAll('.bars-wrap').forEach(fitBarcode);
      requestAnimationFrame(function() {
        requestAnimationFrame(function() {
          setTimeout(function() {
            window.print();
            window.close();
          }, 150);
        });
      });
    }

    document.fonts.ready.then(printWhenReady).catch(printWhenReady);
  <\/script>
</body>
</html>`}var _=r();function v(e){let t=(0,s.c)(12),{code:n,barHeight:r,fontWeight:i}=e,a=i===void 0?400:i,c=(0,o.useRef)(null),l=(0,o.useRef)(null),u;t[0]===r?u=t[1]:(u=()=>{let e=()=>{if(c.current&&l.current){let e=h(c.current,l.current,r);l.current.style.height=`${Math.max(e,12)}px`}};document.fonts?.ready?document.fonts.ready.then(e):e()},t[0]=r,t[1]=u);let d;t[2]!==r||t[3]!==n?(d=[n,r],t[2]=r,t[3]=n,t[4]=d):d=t[4],(0,o.useLayoutEffect)(u,d);let f;t[5]===Symbol.for(`react.memo_cache_sentinel`)?(f={width:`100%`,overflow:`hidden`,display:`flex`,alignItems:`flex-end`,justifyContent:`center`,flexShrink:0},t[5]=f):f=t[5];let p=`${r}px`,m;t[6]!==a||t[7]!==p?(m={fontFamily:`'Libre Barcode 128', monospace`,fontSize:p,fontWeight:a,lineHeight:1,whiteSpace:`nowrap`,display:`inline-block`,maxWidth:`100%`},t[6]=a,t[7]=p,t[8]=m):m=t[8];let g;return t[9]!==n||t[10]!==m?(g=(0,_.jsx)(`div`,{ref:l,style:f,children:(0,_.jsx)(`div`,{ref:c,style:m,children:n})}),t[9]=n,t[10]=m,t[11]=g):g=t[11],g}export{l as a,c,d as i,a as l,g as n,u as o,m as r,p as s,v as t};