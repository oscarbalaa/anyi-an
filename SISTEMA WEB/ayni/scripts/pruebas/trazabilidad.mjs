// M13 - Matriz de trazabilidad: cada RF de docs/requisitos.md con las pruebas que lo cubren.
// Busca "RF-NN" (y rangos "RF-NN a RF-MM" / "RF-NN – RF-MM") en el nombre de cada prueba, en su describe y en el
// comentario de cabecera del archivo. Escribe docs/trazabilidad.md.
//   node scripts/pruebas/trazabilidad.mjs            → regenera el documento
//   node scripts/pruebas/trazabilidad.mjs --revisar  → falla si un RF no tiene pruebas o si el documento está desactualizado
import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs'
import { join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

const RAIZ = fileURLToPath(new URL('../..', import.meta.url))
const DOC = join(RAIZ, 'docs/trazabilidad.md')
const CARPETAS = [
  ['Pest', 'backend/tests', /\.php$/],
  ['Vitest', 'frontend/src', /\.test\.tsx?$/],
  ['Playwright', 'e2e/pruebas', /\.spec\.ts$/],
  ['Humo (hosting)', 'e2e/humo', /\.spec\.ts$/],
  ['Panel', 'scripts/pruebas', /(\.Tests\.ps1|\.test\.sh)$/],
]

/** "RF-38 a RF-48", "RF-52 – RF-58", "RF-60" → [38..48], [52..58], [60] */
function rfsEn(texto) {
  const rfs = new Set()
  for (const m of texto.matchAll(/RF-(\d{2})(?:\s*(?:a|–|-|al)\s*RF-(\d{2}))?/g)) {
    const desde = Number(m[1])
    const hasta = Number(m[2] ?? m[1])
    for (let n = desde; n <= hasta; n++) rfs.add(n)
  }
  return [...rfs]
}

function archivos(carpeta, patron) {
  const ruta = join(RAIZ, carpeta)
  return readdirSync(ruta).flatMap((nombre) => {
    const completo = join(ruta, nombre)
    if (statSync(completo).isDirectory()) return nombre === 'node_modules' ? [] : archivos(join(carpeta, nombre), patron)
    return patron.test(nombre) ? [join(carpeta, nombre)] : []
  })
}

// Requisitos: "| RF-01 | Descripción | Actor | Prioridad |"
const requisitos = readFileSync(join(RAIZ, 'docs/requisitos.md'), 'utf8')
  .split('\n')
  .map((l) => l.match(/^\| RF-(\d{2}) \| ([^|]+) \|/))
  .filter(Boolean)
  .map((m) => ({ n: Number(m[1]), texto: m[2].trim() }))
  // La tabla de requisitos va primero; las filas de la matriz por módulo repiten algunos RF
  .filter((r, i, todos) => todos.findIndex((x) => x.n === r.n) === i)

const cobertura = new Map(requisitos.map((r) => [r.n, []]))
for (const [tipo, carpeta, patron] of CARPETAS) {
  for (const archivo of archivos(carpeta, patron)) {
    const lineas = readFileSync(join(RAIZ, archivo), 'utf8').split('\n')
    // Cabecera: comentarios del inicio del archivo (hasta la primera prueba)
    const inicio = lineas.findIndex((l) => /\b(it|test|describe)\s*\(|^\s*(Describe|It) |^prueba /.test(l))
    const cabecera = rfsEn(lineas.slice(0, inicio > 0 ? inicio : 15).join(' '))
    const describes = []
    let pruebas = 0
    const porRf = new Map()
    lineas.forEach((linea) => {
      const d = linea.match(/^(\s*)(?:test\.)?describe\(\s*['"`](.+?)['"`]/) ?? linea.match(/^(\s*)Describe ['"](.+?)['"]/)
      if (d) {
        while (describes.length && describes.at(-1).sangria >= d[1].length) describes.pop()
        describes.push({ sangria: d[1].length, rfs: rfsEn(d[2]) })
      }
      const t =
        linea.match(/^\s*(?:it|test)\(\s*['"`](.+?)['"`]/) ??
        linea.match(/^\s*It ['"](.+?)['"]/) ??
        linea.match(/^prueba ['"](.+?)['"]/)
      if (!t) return
      pruebas++
      const propios = rfsEn(t[1])
      const heredados = describes.flatMap((x) => x.rfs)
      for (const n of new Set([...propios, ...heredados, ...cabecera])) {
        if (!porRf.has(n)) porRf.set(n, [])
        porRf.get(n).push(t[1].replace(/\|/g, '/').slice(0, 90))
      }
    })
    for (const [n, nombres] of porRf) {
      cobertura.get(n)?.push({ tipo, archivo: relative(RAIZ, join(RAIZ, archivo)), nombres, pruebas })
    }
  }
}

const filas = requisitos.map(({ n, texto }) => {
  const c = cobertura.get(n)
  const total = c.reduce((t, x) => t + x.nombres.length, 0)
  const detalle = c
    .map((x) => `${x.tipo} \`${x.archivo.split('/').slice(-2).join('/')}\` (${x.nombres.length}): ${x.nombres.slice(0, 2).map((s) => `«${s}»`).join(', ')}${x.nombres.length > 2 ? '…' : ''}`)
    .join('<br>')
  return `| RF-${String(n).padStart(2, '0')} | ${texto} | ${total} | ${detalle || '**SIN PRUEBAS**'} |`
})
const sinPruebas = requisitos.filter(({ n }) => cobertura.get(n).length === 0).map(({ n }) => `RF-${String(n).padStart(2, '0')}`)

const documento = `# AYNI — Matriz de trazabilidad por requisito

> Generada con \`node scripts/pruebas/trazabilidad.mjs\` a partir de \`docs/requisitos.md\` y de los nombres de las pruebas. No se edita a mano.
> La CI la vuelve a generar y falla si un requisito queda sin pruebas o si este archivo quedó desactualizado.
> **Resultado:** todas las pruebas listadas pasan en la CI de GitHub (\`.github/workflows/pruebas.yml\`) y en la opción 10 del panel.

${requisitos.length} requisitos funcionales · ${requisitos.length - sinPruebas.length} con pruebas automáticas.

| RF | Requisito | Pruebas | Dónde (archivo y algunas pruebas) |
|---|---|---|---|
${filas.join('\n')}
`

if (process.argv.includes('--revisar')) {
  const actual = (() => {
    try {
      return readFileSync(DOC, 'utf8').replace(/\r\n/g, '\n')
    } catch {
      return ''
    }
  })()
  const errores = []
  if (sinPruebas.length) errores.push(`Requisitos sin pruebas: ${sinPruebas.join(', ')}`)
  if (actual !== documento) errores.push('docs/trazabilidad.md está desactualizado: ejecuta node scripts/pruebas/trazabilidad.mjs')
  if (errores.length) {
    console.error(errores.join('\n'))
    process.exit(1)
  }
  console.log(`Trazabilidad al día: ${requisitos.length} requisitos, todos con pruebas.`)
} else {
  writeFileSync(DOC, documento)
  console.log(`docs/trazabilidad.md: ${requisitos.length} requisitos, ${sinPruebas.length} sin pruebas${sinPruebas.length ? `: ${sinPruebas.join(', ')}` : ''}.`)
}
