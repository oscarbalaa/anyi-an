import { expect, test } from '@playwright/test'

// M1 - RF-65, RNF-01, RNF-03, RNF-05

test.describe('página de estado del sistema', () => {
  test('carga sin errores y confirma API y base de datos', async ({ page }) => {
    const errores: string[] = []
    page.on('console', (m) => m.type() === 'error' && errores.push(m.text()))
    page.on('pageerror', (e) => errores.push(e.message))

    await page.goto('/estado')

    await expect(page).toHaveTitle('AYNI')
    await expect(page.getByRole('heading', { name: 'Estado del sistema' })).toBeVisible()
    await expect(page.getByTestId('indicador-API')).toHaveText(/AYNI \d+\.\d+\.\d+/)
    await expect(page.getByTestId('indicador-Base de datos')).toHaveText(/MySQL|MariaDB/)
    await expect(page.getByTestId('indicador-PHP')).toHaveText(/^8\.1\./)
    expect(errores).toEqual([])
  })

  test('no tiene desplazamiento horizontal', async ({ page }) => {
    await page.goto('/estado')
    await expect(page.getByTestId('indicador-API')).toBeVisible()

    const desborde = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
    expect(desborde).toBeLessThanOrEqual(0)
  })

  test('una ruta de pantalla desconocida muestra la página 404 de AYNI', async ({ page }) => {
    const respuesta = await page.goto('/alguna/ruta/inexistente')

    expect(respuesta?.status()).toBe(200)
    await expect(page.getByRole('heading', { name: 'Esta página no existe' })).toBeVisible()
    await page.getByRole('link', { name: 'Volver al inicio' }).click()
    await expect(page).toHaveURL(/\/$/)
  })
})

test.describe('API por la misma dirección', () => {
  test('responde el estado en JSON', async ({ request }) => {
    const respuesta = await request.get('/api/v1/health')

    expect(respuesta.status()).toBe(200)
    expect(await respuesta.json()).toMatchObject({ estado: 'ok', zona_horaria: 'America/Lima' })
  })

  test('responde 404 en JSON y en español', async ({ request }) => {
    const respuesta = await request.get('/api/v1/no-existe')

    expect(respuesta.status()).toBe(404)
    expect(await respuesta.json()).toEqual({ mensaje: 'El recurso solicitado no existe.' })
  })
})

test.describe('archivos internos ocultos (igual que en el hosting)', () => {
  const rutas = [
    '/backend/.env',
    '/backend/.env.example',
    '/backend/composer.json',
    '/backend/artisan',
    '/backend/storage/logs/laravel.log',
    '/.htaccess',
    '/docker-compose.yml',
    '/1.%20INICIO/CREDENCIALES.txt',
    '/1.%20INICIO/CREDENCIALES_PRIVADAS.txt',
    '/backend/',
    '/backend/app/',
  ]

  for (const ruta of rutas) {
    test(`no expone ${ruta}`, async ({ request }) => {
      const respuesta = await request.get(ruta)
      const texto = await respuesta.text()

      expect(respuesta.headers()['content-type'] ?? '').not.toContain('text/plain')
      expect(texto).not.toMatch(/APP_KEY|DB_PASSWORD|laravel\/framework|RewriteEngine|AYNI_BD_CLAVE|Index of/)
    })
  }

  test('envía cabeceras básicas de seguridad', async ({ request }) => {
    const respuesta = await request.get('/')

    expect(respuesta.headers()['x-content-type-options']).toBe('nosniff')
    expect(respuesta.headers()['x-frame-options']).toBe('SAMEORIGIN')
    expect(respuesta.headers()['server'] ?? '').not.toMatch(/\d/)
    // M13: React solo carga lo propio (sin scripts de otros sitios ni eval)
    expect(respuesta.headers()['content-security-policy']).toContain("script-src 'self'")
    expect(respuesta.headers()['content-security-policy']).toContain("object-src 'none'")
  })

  test('la API envía cada cabecera de seguridad una sola vez y sin CORS para otros sitios', async ({ request }) => {
    const respuesta = await request.get('/api/v1/health', { headers: { Origin: 'https://malo.example' } })

    // Si Apache y Laravel la enviaran los dos, llegaría repetida ("DENY, SAMEORIGIN")
    expect(respuesta.headers()['x-frame-options']).toBe('DENY')
    expect(respuesta.headers()['x-content-type-options']).toBe('nosniff')
    expect(respuesta.headers()['content-security-policy']).toBe("default-src 'none'; frame-ancestors 'none'")
    expect(respuesta.headers()['access-control-allow-origin']).toBeUndefined()
  })
})
