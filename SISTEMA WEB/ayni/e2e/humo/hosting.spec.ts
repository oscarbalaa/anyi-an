import { expect, test, type Page } from '@playwright/test'
import { CUENTAS, llenarIngreso, sinDesbordeHorizontal, type RolE2E } from '../pruebas/cuentas'

// M14 - prueba de humo del hosting: la web, la API, lo prohibido y un recorrido de SOLO LECTURA por rol.
// No crea, edita ni borra datos (solo inicia y cierra sesión).

const relativa = (ruta: string) => ruta.replace(/^\//, '')

test.describe('web y API', () => {
  test('la portada carga sin errores ni bloqueos de la política de contenido', async ({ page }) => {
    const errores: string[] = []
    page.on('pageerror', (e) => errores.push(e.message))
    page.on('console', (m) => m.type() === 'error' && errores.push(m.text()))
    await page.goto('./')
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(/Señales\s+claras/)
    await sinDesbordeHorizontal(page)
    expect(errores).toEqual([])
  })

  test('recargar una pantalla interna no da 404', async ({ request }) => {
    for (const ruta of ['ingresar', 'ingresar/docente', 'admin/usuarios', 'estudiante/notas']) {
      const r = await request.get(ruta)
      expect(r.status(), ruta).toBe(200)
      expect(await r.text(), ruta).toContain('<div id="root">')
    }
  })

  test('la API responde, con la base de datos conectada y sin mostrar versiones', async ({ request }) => {
    const r = await request.get('api/v1/health')
    expect(r.status()).toBe(200)
    const datos = await r.json()
    expect(datos.estado).toBe('ok')
    // En producción no dice el entorno ni las versiones de PHP, Laravel o la base de datos
    expect(datos.base_de_datos).toEqual({ conectada: true })
    expect(datos.entorno ?? datos.php ?? datos.laravel).toBeUndefined()
    expect(r.headers()['x-frame-options']).toBe('DENY')
  })

  test('al forzar un error no se muestran detalles técnicos', async ({ request }) => {
    const noExiste = await request.get('api/v1/no-existe')
    expect(noExiste.status()).toBe(404)
    expect(await noExiste.json()).toEqual({ mensaje: 'El recurso solicitado no existe.' })

    const malFormado = await request.post('api/v1/auth/ingresar', { data: { acceso: ['x'], usuario: ['x'] } })
    expect(malFormado.status()).toBe(422)
    expect(await malFormado.text()).not.toMatch(/vendor|Stack trace|Exception|\.php/)
  })
})

test.describe('lo privado no se abre desde el navegador (403)', () => {
  for (const ruta of [
    '.env',
    '.htaccess',
    'backend/',
    'backend/.env',
    'backend/composer.json',
    'backend/vendor/autoload.php',
    'backend/storage/logs/',
    'vendor/',
    'storage/',
    '1.%20INICIO/',
    '1.%20INICIO/CREDENCIALES.txt',
    'docs/',
    'scripts/ayni.ps1',
    'docker-compose.yml',
    'README.md',
  ]) {
    test(ruta, async ({ request }) => {
      const r = await request.get(ruta)
      expect(r.status()).toBe(403)
      expect(await r.text()).not.toMatch(/APP_KEY|DB_PASSWORD|Index of/)
    })
  }
})

async function ingresar(page: Page, rol: RolE2E) {
  await page.goto(relativa(CUENTAS[rol].ingreso))
  await llenarIngreso(page, rol)
  await expect(page).toHaveURL(new RegExp(`${CUENTAS[rol].base}(/|$)`))
  await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible()
}

for (const rol of Object.keys(CUENTAS) as RolE2E[]) {
  test(`${rol}: ingresa, recorre su menú (solo lectura) y sale`, async ({ page, isMobile }) => {
    test.setTimeout(240_000)
    const errores: string[] = []
    page.on('pageerror', (e) => errores.push(`${page.url()}: ${e.message}`))
    await ingresar(page, rol)
    // En el celular el menú lateral se dibuja al abrirlo
    if (isMobile) await page.getByRole('button', { name: 'Abrir menú' }).click()

    // El menú se dibuja cuando termina de cargar el panel del rol
    await expect(page.locator('#menu-lateral nav a').first()).toBeAttached()
    const rutas = await page.locator('#menu-lateral nav a').evaluateAll((enlaces) => enlaces.map((a) => new URL((a as HTMLAnchorElement).href).pathname))
    expect(rutas.length).toBeGreaterThan(4)
    for (const ruta of rutas) {
      await page.goto(ruta)
      await expect(page.getByRole('heading', { level: 1 }).first(), ruta).toBeVisible()
      await expect(page.getByText(/No se pudo cargar/)).toHaveCount(0)
    }
    expect(errores).toEqual([])

    await page.getByRole('button', { name: 'Menú de la cuenta' }).click()
    await page.getByRole('menuitem', { name: 'Cerrar sesión' }).click()
    await expect(page).toHaveURL(/ingresar|\/$/)
  })
}
