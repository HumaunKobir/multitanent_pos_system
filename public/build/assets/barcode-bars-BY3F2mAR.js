import{c as e,n as t,r as n,t as r}from"./jsx-runtime-Tg1yNRQt.js";import{t as i}from"./createLucideIcon-DKh52u_-.js";var a=i(`Printer`,[[`path`,{d:`M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2`,key:`143wyd`}],[`path`,{d:`M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6`,key:`1itne7`}],[`rect`,{x:`6`,y:`14`,width:`12`,height:`8`,rx:`1`,key:`1ue0tg`}]]),o=e(n(),1),s=t(),c=.86,l=.98;function u(e){return Math.max(6,Math.ceil(e*.5))}function d(){return 1}function f(e){if(e?.variation?.price!=null)return parseFloat(e.variation.price);if(!e?.product)return null;let t=parseFloat(e.product.discount_price??0),n=parseFloat(e.product.sale_price??0);return t>0?t:n}function p(e){return`Price: ${(e==null?0:Number(e)).toFixed(2)}`}function m(e){return e?.product?.name??e?.name??`Product Name`}function h(e){let t=m(e);return e?.code?`${t} - ${e.code}`:t}function g(e){let{height:t,fontSize:n}=e,r=t*96,i=Math.ceil(n*1.2),a=u(n),o=n+d(),s=r-6-i-a-o;return s<=32?Math.max(12,s):Math.max(32,Math.floor(s*l))}function _(e,t,n,{fill:r=!1}={}){if(!e||!t)return n;let i=t.clientWidth*c,a=t.clientHeight,o=r&&a>8?Math.floor(a*l):n,s=Math.max(32,Math.min(n,o));e.style.transform=`none`,e.style.transformOrigin=`center center`,e.style.width=`auto`,e.style.maxWidth=`none`,e.style.display=`inline-block`,e.style.fontSize=`${s}px`;let u=e.scrollWidth;return u>i&&i>0&&u>0&&(e.style.transform=`scaleX(${i/u})`),e.offsetHeight||s}function v(e,t){let{width:n,height:r,fontSize:i,fontWeight:a,copies:o}=t,s=a===`bold`?700:400,m=g(t);return`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Barcode Labels</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    @page { size: ${n}in ${r}in; margin: 0; }
    html, body { width: 100%; background: white; }
    .page { display: flex; flex-wrap: wrap; align-content: flex-start; }
    .label {
      width: ${n}in;
      height: ${r}in;
      border: 1px solid #ccc;
      display: flex;
      align-items: stretch;
      justify-content: center;
      padding: 3px 5px;
      overflow: hidden;
      page-break-inside: avoid;
      break-inside: avoid;
    }
    .label-inner {
      width: 100%;
      height: 100%;
      min-height: 0;
      display: flex;
      flex-direction: column;
      justify-content: stretch;
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
      margin-bottom: ${u(i)}px;
    }
    .bars-wrap {
      width: 100%;
      flex: 1 1 0;
      min-height: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      padding: 0 6px;
      box-sizing: border-box;
    }
    .bars {
      font-family: 'Libre Barcode 128', monospace;
      font-weight: ${s};
      font-size: ${m}px;
      line-height: 1;
      white-space: nowrap;
      display: inline-block;
    }
    .footer {
      display: flex;
      justify-content: center;
      font-size: ${i}px;
      font-weight: ${s};
      font-family: monospace;
      line-height: 1;
      flex-shrink: 0;
      margin-top: ${d()}px;
    }
    @media print {
      @page { size: ${n}in ${r}in; margin: 0; }
      html, body {
        width: ${n}in;
        margin: 0;
        padding: 0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .page {
        display: block;
        width: ${n}in;
        margin: 0;
        padding: 0;
      }
      .label {
        width: ${n}in;
        height: ${r}in;
        max-height: ${r}in;
        border: none;
        page-break-after: always;
        break-after: page;
        overflow: hidden;
      }
      .label:last-child {
        page-break-after: avoid;
        break-after: avoid;
      }
    }
  </style>
</head>
<body>
  <div class="page">${e.flatMap(e=>Array.from({length:o},()=>e)).map(e=>{let t=f(e)??0;return`
      <div class="label">
        <div class="label-inner">
          <div class="name">${h(e)}</div>
          <div class="bars-wrap">
            <div class="bars" data-max-bar-height="${m}">${e.code}</div>
          </div>
          <div class="footer">
            <span>${p(t)}</span>
          </div>
        </div>
      </div>`}).join(``)}</div>
  <script>
    function fitBarcode(wrap) {
      var el = wrap.querySelector('.bars');
      if (!el) return;
      var maxBarHeight = parseFloat(el.getAttribute('data-max-bar-height') || '${m}');
      var targetWidth = wrap.clientWidth * ${c};
      var availableHeight = wrap.clientHeight;
      var heightFromContainer = availableHeight > 8
        ? Math.floor(availableHeight * ${l})
        : maxBarHeight;
      var baseHeight = Math.max(
        32,
        Math.min(maxBarHeight, heightFromContainer)
      );
      el.style.transform = 'none';
      el.style.transformOrigin = 'center center';
      el.style.width = 'auto';
      el.style.maxWidth = 'none';
      el.style.display = 'inline-block';
      el.style.fontSize = baseHeight + 'px';
      var tw = el.scrollWidth;
      if (tw > targetWidth && targetWidth > 0 && tw > 0) {
        el.style.transform = 'scaleX(' + (targetWidth / tw) + ')';
      }
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
</html>`}var y=r();function b(e){let t=(0,s.c)(19),{code:n,barHeight:r,fontWeight:i,fill:a}=e,c=i===void 0?400:i,l=a===void 0?!1:a,u=(0,o.useRef)(null),d=(0,o.useRef)(null),f;t[0]!==r||t[1]!==l?(f=()=>{let e=()=>{u.current&&d.current&&_(u.current,d.current,r,{fill:l})};document.fonts?.ready?document.fonts.ready.then(e):e();let t=d.current;if(!t)return;let n=new ResizeObserver(()=>{e()});return n.observe(t),()=>{n.disconnect()}},t[0]=r,t[1]=l,t[2]=f):f=t[2];let p;t[3]!==r||t[4]!==n||t[5]!==l?(p=[n,r,l],t[3]=r,t[4]=n,t[5]=l,t[6]=p):p=t[6],(0,o.useLayoutEffect)(f,p);let m=l?`1 1 0`:`0 0 auto`,h=l?0:void 0,g;t[7]!==m||t[8]!==h?(g={width:`100%`,overflow:`hidden`,display:`flex`,alignItems:`center`,justifyContent:`center`,flex:m,minHeight:h,padding:`0 6px`,boxSizing:`border-box`},t[7]=m,t[8]=h,t[9]=g):g=t[9];let v=`${r}px`,b;t[10]!==c||t[11]!==v?(b={fontFamily:`'Libre Barcode 128', monospace`,fontSize:v,fontWeight:c,lineHeight:1,whiteSpace:`nowrap`,display:`inline-block`},t[10]=c,t[11]=v,t[12]=b):b=t[12];let x;t[13]!==n||t[14]!==b?(x=(0,y.jsx)(`div`,{ref:u,style:b,children:n}),t[13]=n,t[14]=b,t[15]=x):x=t[15];let S;return t[16]!==x||t[17]!==g?(S=(0,y.jsx)(`div`,{ref:d,style:g,children:x}),t[16]=x,t[17]=g,t[18]=S):S=t[18],S}export{d as a,u as c,p as i,a as l,v as n,f as o,g as r,h as s,b as t};