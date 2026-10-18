// Primera visita a las páginas principales antes de las pruebas: evita que el arranque
// en frío del contenedor (OPcache, Apache) haga fallar la primera prueba por tiempo.
// También abre una sesión por rol con las cuentas demo, que comparten las pruebas.
import { request } from '@playwright/test'
import { writeFileSync } from 'node:fs'
import { CUENTAS, ARCHIVO_SESIONES } from './pruebas/cuentas'

export default async function calentar() {
  const base = process.env.AYNI_URL ?? 'http://localhost:5173'
  const secreto = process.env.AYNI_SECRETO_E2E ?? ''
  const cliente = await request.newContext({
    baseURL: base,
    timeout: 120_000,
    extraHTTPHeaders: secreto ? { 'X-Ayni-E2E': secreto } : {},
  })
  for (const ruta of ['/api/v1/health', '/api/v1/calendario/actual', '/', '/estado', '/ingresar', '/docente']) {
    await cliente.get(ruta)
  }

  if (!secreto) throw new Error('Falta AYNI_SECRETO_E2E: enciende AYNI con el panel para que lo genere en .env')
  const tokens: Record<string, string> = {}
  for (const [rol, c] of Object.entries(CUENTAS)) {
    const r = await cliente.post('/api/v1/auth/ingresar', { data: { acceso: rol, usuario: c.usuario, contrasena: c.clave } })
    if (!r.ok()) throw new Error(`No se pudo ingresar como ${rol} (${r.status()}): ${await r.text()}`)
    tokens[rol] = (await r.json()).token
  }
  writeFileSync(ARCHIVO_SESIONES, JSON.stringify(tokens))
  await cliente.dispose()
}
