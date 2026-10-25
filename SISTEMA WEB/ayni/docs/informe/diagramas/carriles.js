// Diagrama de carriles: window.carriles({titulo, carriles:[...], nodos:{id:{c,f,t,tipo}}, aristas:[[de,a,etiqueta]]})
window.carriles = function (d) {
  const anchoC = d.anchoCarril || 300, altoF = d.altoFila || 100, cab = 44, nw = anchoC - 60, nh = 56;
  const filas = Math.max(...Object.values(d.nodos).map(n => n.f)) + 1;
  const W = anchoC * d.carriles.length + 2, H = cab + filas * altoF + 20;
  const ns = 'http://www.w3.org/2000/svg';
  const svg = document.createElementNS(ns, 'svg'); svg.id = 'lienzo';
  svg.setAttribute('width', W); svg.setAttribute('height', H);
  document.body.appendChild(svg);
  const el = (n, a, t) => { const e = document.createElementNS(ns, n); for (const k in a) e.setAttribute(k, a[k]); if (t != null) e.textContent = t; svg.appendChild(e); return e; };
  const defs = el('defs', {}); const m = document.createElementNS(ns, 'marker');
  Object.entries({ id: 'f', markerWidth: 10, markerHeight: 8, refX: 9, refY: 4, orient: 'auto' }).forEach(([k, v]) => m.setAttribute(k, v));
  const pa = document.createElementNS(ns, 'path'); pa.setAttribute('d', 'M0,0 L10,4 L0,8 z'); pa.setAttribute('fill', '#333'); m.appendChild(pa); defs.appendChild(m);
  d.carriles.forEach((c, i) => {
    el('rect', { x: 1 + i * anchoC, y: 1, width: anchoC, height: H - 2, fill: i % 2 ? '#fafbfc' : '#fff', stroke: '#555' });
    el('rect', { x: 1 + i * anchoC, y: 1, width: anchoC, height: cab, fill: '#2f5d8a', stroke: '#555' });
    el('text', { x: 1 + i * anchoC + anchoC / 2, y: 28, 'text-anchor': 'middle', 'font-size': 15, 'font-weight': 'bold', fill: '#fff' }, c);
  });
  const P = {};
  for (const [id, n] of Object.entries(d.nodos)) P[id] = { x: 1 + n.c * anchoC + anchoC / 2 + (n.dx || 0), y: cab + n.f * altoF + altoF / 2, n };
  const caja = id => { const p = P[id], t = p.n.tipo; return t === 'decision' ? { w: 190, h: 74 } : t === 'fin' ? { w: 170, h: 40 } : { w: nw, h: nh }; };
  for (const [a, b, et, via] of d.aristas) {
    const A = P[a], B = P[b], ca = caja(a), cb = caja(b);
    let dpath, lx, ly;
    if (A.n.c === B.n.c && B.y > A.y && !via) { dpath = `M${A.x},${A.y + ca.h / 2} L${B.x},${B.y - cb.h / 2}`; lx = A.x + 8; ly = (A.y + ca.h / 2 + B.y - cb.h / 2) / 2; }
    else if (via === 'lado') { const sx = B.x > A.x ? A.x + ca.w / 2 : A.x - ca.w / 2; dpath = `M${sx},${A.y} L${B.x},${A.y} L${B.x},${B.y - cb.h / 2}`; lx = (sx + B.x) / 2; ly = A.y - 6; }
    else if (via === 'lado-lado') { const sx = B.x > A.x ? A.x + ca.w / 2 : A.x - ca.w / 2; const ex = B.x > A.x ? B.x - cb.w / 2 : B.x + cb.w / 2; const mx = (sx + ex) / 2; dpath = `M${sx},${A.y} L${mx},${A.y} L${mx},${B.y} L${ex},${B.y}`; lx = mx + 6; ly = (A.y + B.y) / 2; }
    else { const my = B.y - cb.h / 2 - 18; dpath = `M${A.x},${A.y + ca.h / 2} L${A.x},${my} L${B.x},${my} L${B.x},${B.y - cb.h / 2}`; lx = A.x + 8; ly = A.y + ca.h / 2 + 16; }
    el('path', { d: dpath, fill: 'none', stroke: '#333', 'stroke-width': 1.4, 'marker-end': 'url(#f)' });
    if (et) el('text', { x: lx, y: ly, 'font-size': 13, 'font-weight': 'bold', fill: '#7a2e00' }, et);
  }
  for (const [id, p] of Object.entries(P)) {
    const { w, h } = caja(id), t = p.n.tipo;
    if (t === 'decision') el('polygon', { points: `${p.x},${p.y - h / 2} ${p.x + w / 2},${p.y} ${p.x},${p.y + h / 2} ${p.x - w / 2},${p.y}`, fill: '#fff6e5', stroke: '#b06a00', 'stroke-width': 1.4 });
    else el('rect', { x: p.x - w / 2, y: p.y - h / 2, width: w, height: h, rx: t === 'fin' ? 20 : 6, fill: t === 'fin' ? '#e8eef5' : t === 'problema' ? '#fdecec' : '#eef5ee', stroke: t === 'problema' ? '#b03030' : t === 'fin' ? '#2f5d8a' : '#3b7a3b', 'stroke-width': 1.4 });
    const lineas = p.n.t.split('|');
    lineas.forEach((l, i) => el('text', { x: p.x, y: p.y + 5 + (i - (lineas.length - 1) / 2) * 16, 'text-anchor': 'middle', 'font-size': 13 }, l));
  }
};
