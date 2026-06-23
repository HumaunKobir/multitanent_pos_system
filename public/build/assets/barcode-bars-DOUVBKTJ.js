import{c as e,n as t,r as n,t as r}from"./jsx-runtime-Tg1yNRQt.js";import{t as i}from"./createLucideIcon-DKh52u_-.js";var a=i(`Printer`,[[`path`,{d:`M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2`,key:`143wyd`}],[`path`,{d:`M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6`,key:`1itne7`}],[`rect`,{x:`6`,y:`14`,width:`12`,height:`8`,rx:`1`,key:`1ue0tg`}]]),o=e(n(),1),s=t(),c=.86,l=.98,u=204,d=206;function f(e){return e<=94?String.fromCharCode(e+32):String.fromCharCode(e+146)}function p(e){return/^[\x20-\x7E]+$/.test(String(e??``))}function m(e){let t=String(e??``);if(!t)return``;if(!p(t))throw Error(`Barcode code contains unsupported characters: ${t}`);let n=104,r=String.fromCharCode(u);for(let e=0;e<t.length;e++){let i=t.charCodeAt(e)-32;n+=i*(e+1),r+=t[e]}return n%=103,r+=f(n),r+=String.fromCharCode(d),r}function h(e){let t=String(e??``).trim();return t?m(t):``}function g(e){return String(e).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`)}function _(e){return Math.max(6,Math.ceil(e*.5))}function v(){return 1}function y(e){if(e?.variation?.price!=null)return parseFloat(e.variation.price);if(!e?.product)return null;let t=parseFloat(e.product.discount_price??0),n=parseFloat(e.product.sale_price??0);return t>0?t:n}function b(e){return`Price: ${(e==null?0:Number(e)).toFixed(2)}`}function x(e){return e?.product?.name??e?.name??`Product Name`}function S(e){let t=x(e);return e?.code?`${t} - ${e.code}`:t}function C(e){let{height:t,fontSize:n}=e,r=t*96,i=Math.ceil(n*1.2),a=_(n),o=n+v(),s=r-6-i-a-o;return s<=32?Math.max(12,s):Math.max(32,Math.floor(s*l))}function w(e,t,n,{fill:r=!1}={}){if(!e||!t)return n;let i=t.clientWidth*c,a=t.clientHeight,o=r&&a>8?Math.floor(a*l):n,s=Math.max(32,Math.min(n,o));e.style.transform=`none`,e.style.transformOrigin=`center center`,e.style.width=`auto`,e.style.maxWidth=`none`,e.style.display=`inline-block`;do{if(e.style.fontSize=`${s}px`,e.scrollWidth<=i||s<=32)break;--s}while(s>=32);return e.offsetHeight||s}function T(e,t){let{width:n,height:r,fontSize:i,fontWeight:a,copies:o}=t,s=a===`bold`?700:400,u=C(t);return`<!DOCTYPE html>
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
      margin-bottom: ${_(i)}px;
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
      font-size: ${u}px;
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
      margin-top: ${v()}px;
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
  <div class="page">${e.flatMap(e=>Array.from({length:o},()=>e)).map(e=>{let t=y(e)??0,n=S(e),r=h(e.code);return`
      <div class="label">
        <div class="label-inner">
          <div class="name">${g(n)}</div>
          <div class="bars-wrap">
            <div class="bars" data-max-bar-height="${u}">${g(r)}</div>
          </div>
          <div class="footer">
            <span>${g(b(t))}</span>
          </div>
        </div>
      </div>`}).join(``)}</div>
  <script>
    function fitBarcode(wrap) {
      var el = wrap.querySelector('.bars');
      if (!el) return;
      var maxBarHeight = parseFloat(el.getAttribute('data-max-bar-height') || '${u}');
      var targetWidth = wrap.clientWidth * ${c};
      var availableHeight = wrap.clientHeight;
      var heightFromContainer = availableHeight > 8
        ? Math.floor(availableHeight * ${l})
        : maxBarHeight;
      var height = Math.max(
        32,
        Math.min(maxBarHeight, heightFromContainer)
      );
      var minHeight = Math.max(32, 18);
      el.style.transform = 'none';
      el.style.transformOrigin = 'center center';
      el.style.width = 'auto';
      el.style.maxWidth = 'none';
      el.style.display = 'inline-block';
      do {
        el.style.fontSize = height + 'px';
        if (el.scrollWidth <= targetWidth || height <= minHeight) {
          break;
        }
        height -= 1;
      } while (height >= minHeight);
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
</html>`}var E=r();function D(e){let t=(0,s.c)(22),{code:n,barHeight:r,fontWeight:i,fill:a}=e,c=i===void 0?400:i,l=a===void 0?!1:a,u=(0,o.useRef)(null),d=(0,o.useRef)(null),f;t[0]===n?f=t[1]:(f=h(n),t[0]=n,t[1]=f);let p=f,m;t[2]!==r||t[3]!==l?(m=()=>{let e=()=>{u.current&&d.current&&w(u.current,d.current,r,{fill:l})};document.fonts?.ready?document.fonts.ready.then(e):e();let t=d.current;if(!t)return;let n=new ResizeObserver(()=>{e()});return n.observe(t),()=>{n.disconnect()}},t[2]=r,t[3]=l,t[4]=m):m=t[4];let g;t[5]!==r||t[6]!==n||t[7]!==p||t[8]!==l?(g=[n,r,l,p],t[5]=r,t[6]=n,t[7]=p,t[8]=l,t[9]=g):g=t[9],(0,o.useLayoutEffect)(m,g);let _=l?`1 1 0`:`0 0 auto`,v=l?0:void 0,y;t[10]!==_||t[11]!==v?(y={width:`100%`,overflow:`hidden`,display:`flex`,alignItems:`center`,justifyContent:`center`,flex:_,minHeight:v,padding:`0 6px`,boxSizing:`border-box`},t[10]=_,t[11]=v,t[12]=y):y=t[12];let b=`${r}px`,x;t[13]!==c||t[14]!==b?(x={fontFamily:`'Libre Barcode 128', monospace`,fontSize:b,fontWeight:c,lineHeight:1,whiteSpace:`nowrap`,display:`inline-block`},t[13]=c,t[14]=b,t[15]=x):x=t[15];let S;t[16]!==p||t[17]!==x?(S=(0,E.jsx)(`div`,{ref:u,style:x,children:p}),t[16]=p,t[17]=x,t[18]=S):S=t[18];let C;return t[19]!==S||t[20]!==y?(C=(0,E.jsx)(`div`,{ref:d,style:y,children:S}),t[19]=S,t[20]=y,t[21]=C):C=t[21],C}export{v as a,_ as c,b as i,a as l,T as n,y as o,C as r,S as s,D as t};