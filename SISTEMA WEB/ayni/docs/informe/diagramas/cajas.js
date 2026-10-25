window.lienzo = function (W, H) {
  const ns = 'http://www.w3.org/2000/svg';
  const svg = document.createElementNS(ns, 'svg'); svg.id = 'lienzo'; svg.setAttribute('width', W); svg.setAttribute('height', H);
  document.body.appendChild(svg);
  const el = (n, a, t) => { const e = document.createElementNS(ns, n); for (const k in a) e.setAttribute(k, a[k]); if (t != null) e.textContent = t; svg.appendChild(e); return e; };
  svg.innerHTML = '<defs><marker id="f" markerWidth="10" markerHeight="8" refX="9" refY="4" orient="auto"><path d="M0,0 L10,4 L0,8 z" fill="#333"/></marker><marker id="fi" markerWidth="10" markerHeight="8" refX="1" refY="4" orient="auto"><path d="M10,0 L0,4 L10,8 z" fill="#333"/></marker></defs>';
  const C = { azul: ['#e8eef5', '#2f5d8a'], verde: ['#eef5ee', '#3b7a3b'], naranja: ['#fff4e6', '#b06a00'], gris: ['#f3f3f3', '#777'], morado: ['#f3eef8', '#6b4a8a'], rojo: ['#fdecec', '#b03030'] };
  return {
    el,
    grupo(x, y, w, h, titulo, color = 'gris', punteado) { const [f, s] = C[color]; el('rect', { x, y, width: w, height: h, rx: 10, fill: f, stroke: s, 'stroke-width': 1.6, 'stroke-dasharray': punteado ? '7 5' : '' }); el('text', { x: x + 12, y: y + 22, 'font-size': 14, 'font-weight': 'bold', fill: s }, titulo); },
    caja(x, y, w, h, titulo, lineas = [], color = 'azul', punteado) { const [f, s] = C[color]; el('rect', { x, y, width: w, height: h, rx: 6, fill: '#fff', stroke: s, 'stroke-width': 1.5, 'stroke-dasharray': punteado ? '6 4' : '' });
      el('rect', { x, y, width: w, height: 26, rx: 6, fill: f, stroke: s, 'stroke-width': 1.5, 'stroke-dasharray': punteado ? '6 4' : '' });
      el('text', { x: x + w / 2, y: y + 18, 'text-anchor': 'middle', 'font-size': 13.5, 'font-weight': 'bold', fill: '#222' }, titulo);
      lineas.forEach((l, i) => el('text', { x: x + w / 2, y: y + 46 + i * 17, 'text-anchor': 'middle', 'font-size': 12.5, fill: '#333' }, l)); },
    flecha(pts, etiqueta, opts = {}) { const d = 'M' + pts.map(p => p.join(',')).join(' L'); el('path', { d, fill: 'none', stroke: '#333', 'stroke-width': 1.5, 'marker-end': 'url(#f)', 'marker-start': opts.doble ? 'url(#fi)' : '', 'stroke-dasharray': opts.punteado ? '6 4' : '' });
      if (etiqueta) { const [a, b] = [pts[0], pts[pts.length - 1]]; const x = opts.lx ?? (a[0] + b[0]) / 2 + 6, y = opts.ly ?? (a[1] + b[1]) / 2 - 6; etiqueta.split('|').forEach((t, i) => el('text', { x, y: y + i * 15, 'font-size': 12, fill: '#7a2e00', 'text-anchor': opts.ancla || 'start' }, t)); } },
    texto(x, y, t, o = {}) { el('text', { x, y, 'font-size': o.tam || 12, fill: o.color || '#444', 'text-anchor': o.ancla || 'start', 'font-style': o.cursiva ? 'italic' : '' }, t); },
  };
};
