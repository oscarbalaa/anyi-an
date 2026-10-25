// Uso: node render.cjs entrada.html salida.png [ancho]
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
(async () => {
const [,, entrada, salida, ancho = '1600'] = process.argv;
const b = await chromium.launch();
const p = await b.newPage({ viewport: { width: +ancho, height: 900 }, deviceScaleFactor: 2 });
if (entrada.endsWith('.mmd')) {
  const src = fs.readFileSync(entrada, 'utf8');
  const lib = path.resolve('node_modules/mermaid/dist/mermaid.min.js');
  await p.setContent(`<html><body style="margin:0;background:#fff"><div id="d"></div></body></html>`);
  await p.addScriptTag({ path: lib });
  await p.evaluate(async (src) => {
    mermaid.initialize({ startOnLoad: false, theme: 'neutral', fontFamily: 'Arial', er: { useMaxWidth: false, fontSize: 14 }, flowchart: { useMaxWidth: false, htmlLabels: true }, securityLevel: 'loose' });
    const { svg } = await mermaid.render('g', src);
    document.getElementById('d').innerHTML = svg;
  }, src);
  const el = await p.$('#d svg');
  await el.screenshot({ path: salida });
} else {
  await p.goto('file://' + path.resolve(entrada));
  await p.waitForTimeout(300);
  const el = await p.$('#lienzo');
  await el.screenshot({ path: salida });
}
await b.close();
})();
