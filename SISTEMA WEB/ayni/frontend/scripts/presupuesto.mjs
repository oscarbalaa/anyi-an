// M13: presupuesto del bundle. Compila en una carpeta temporal y revisa el peso comprimido (gzip).
// Lo que se descarga al abrir AYNI (index + CSS) no debe pasar de 160 kB, ni cada pantalla de 60 kB.
import { execFileSync } from 'node:child_process'
import { mkdtempSync, readdirSync, readFileSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { gzipSync } from 'node:zlib'

const INICIAL_KB = 160
const PANTALLA_KB = 60

const salida = mkdtempSync(join(tmpdir(), 'ayni-presupuesto-'))
execFileSync('npx', ['vite', 'build', '--outDir', salida, '--emptyOutDir', '--logLevel', 'error'], { stdio: 'inherit' })

const kb = (archivo) => gzipSync(readFileSync(join(salida, 'assets', archivo))).length / 1024
const archivos = readdirSync(join(salida, 'assets')).filter((a) => /\.(js|css)$/.test(a))
const iniciales = archivos.filter((a) => /^index-.*\.(js|css)$/.test(a))
const inicial = iniciales.reduce((t, a) => t + kb(a), 0)
const errores = []

if (inicial > INICIAL_KB) errores.push(`Carga inicial: ${inicial.toFixed(1)} kB (máximo ${INICIAL_KB} kB)`)
const diferidos = archivos.filter((a) => !iniciales.includes(a) && a.endsWith('.js')).map((a) => [a, kb(a)])
const [mayor, mayorKb] = diferidos.sort((x, y) => y[1] - x[1])[0] ?? ['—', 0]
rmSync(salida, { recursive: true, force: true })
for (const [a, peso] of diferidos) {
  if (peso > PANTALLA_KB) errores.push(`${a}: ${peso.toFixed(1)} kB (máximo ${PANTALLA_KB} kB)`)
}

console.log(`Carga inicial: ${inicial.toFixed(1)} kB gzip · pantalla más pesada: ${mayor} (${mayorKb.toFixed(1)} kB)`)
if (errores.length) {
  console.error(`El bundle pasa su presupuesto:\n- ${errores.join('\n- ')}`)
  process.exit(1)
}
