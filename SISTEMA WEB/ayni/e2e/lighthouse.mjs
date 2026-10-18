// Mide rendimiento y accesibilidad con Lighthouse usando el Chrome de Playwright.
// Obligatorio: accesibilidad >= 90 en todo y rendimiento >= 90 en PC.
// El rendimiento en celular simulado se informa (ver docs/seguridad-y-calidad en el reporte).
import { chromium } from '@playwright/test'
import * as chromeLauncher from 'chrome-launcher'
import lighthouse from 'lighthouse'
import configEscritorio from 'lighthouse/core/config/desktop-config.js'
import { mkdirSync, writeFileSync } from 'node:fs'

const BASE = process.env.AYNI_URL ?? 'http://localhost:5173'
const SALIDA = '/reportes/lighthouse'
const MINIMO = 90
const PAGINAS = [
  ['/', 'landing'],
  ['/ingresar', 'selector'],
  ['/ingresar/estudiante', 'ingreso-estudiante'],
  ['/docente', 'inicio-docente'],
]
const CATEGORIAS = { performance: 'Rendimiento', accessibility: 'Accesibilidad', 'best-practices': 'Buenas prácticas', seo: 'SEO' }

mkdirSync(SALIDA, { recursive: true })
const chrome = await chromeLauncher.launch({
  chromePath: chromium.executablePath(),
  chromeFlags: ['--headless=new', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'],
})

const opciones = { port: chrome.port, output: 'html', onlyCategories: Object.keys(CATEGORIAS), logLevel: 'error' }

// Lighthouse frena la CPU 4 veces para simular un celular, pensando en un equipo de
// referencia rápido. Dentro de Docker el equipo ya es más lento, así que el freno se
// ajusta a su velocidad real (benchmarkIndex), como recomienda la documentación de Lighthouse.
const prueba = await lighthouse(BASE + '/', { ...opciones, onlyCategories: ['performance'], output: 'json' })
const indice = prueba.lhr.environment.benchmarkIndex
const freno = Math.min(4, Math.max(1, Math.round((4 * indice) / 1000)))
console.log(`Velocidad del equipo (benchmarkIndex): ${Math.round(indice)} → freno de CPU en celular: ${freno}x`)

const configCelular = {
  extends: 'lighthouse:default',
  settings: { throttling: { rttMs: 150, throughputKbps: 1638.4, cpuSlowdownMultiplier: freno } },
}

const resultados = []
try {
  for (const [ruta, nombre] of PAGINAS) {
    for (const dispositivo of ['celular', 'pc']) {
      const respuesta = await lighthouse(BASE + ruta, opciones, dispositivo === 'pc' ? configEscritorio : configCelular)
      writeFileSync(`${SALIDA}/${nombre}-${dispositivo}.html`, respuesta.report)
      const puntajes = Object.fromEntries(
        Object.keys(CATEGORIAS).map((c) => [c, Math.round((respuesta.lhr.categories[c]?.score ?? 0) * 100)]),
      )
      resultados.push({ pagina: ruta, dispositivo, ...puntajes })
      console.log(
        `${ruta.padEnd(22)} ${dispositivo.padEnd(8)} ` +
          Object.entries(CATEGORIAS)
            .map(([c, t]) => `${t}: ${puntajes[c]}`)
            .join('  '),
      )
    }
  }
} finally {
  await chrome.kill()
}

writeFileSync(`${SALIDA}/resumen.json`, JSON.stringify(resultados, null, 2))
// Con un equipo muy lento (menos de 800) el rendimiento medido no es confiable: solo se informa
const rendimientoConfiable = indice >= 800
if (!rendimientoConfiable) {
  console.log(`\nEquipo lento para medir rendimiento (índice ${Math.round(indice)} < 800): el rendimiento se informa, no se exige.`)
}
writeFileSync(`${SALIDA}/equipo.json`, JSON.stringify({ benchmarkIndex: Math.round(indice), frenoCelular: freno, rendimientoConfiable }))
const fallas = resultados.filter(
  (r) => r.accessibility < MINIMO || (rendimientoConfiable && r.dispositivo === 'pc' && r.performance < MINIMO),
)
if (fallas.length) {
  console.error(`\nLighthouse: ${fallas.length} medición(es) por debajo de ${MINIMO} (accesibilidad, o rendimiento en PC).`)
  process.exit(1)
}
const medido = rendimientoConfiable ? 'accesibilidad y rendimiento en PC' : 'accesibilidad'
console.log(`\nLighthouse: ${medido} con ${MINIMO} o más en todas las páginas.`)
