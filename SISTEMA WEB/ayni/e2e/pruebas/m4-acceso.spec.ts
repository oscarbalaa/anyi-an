import { expect, test, type Page } from '@playwright/test'
import { conSesion, CUENTAS, llenarIngreso, sinDesbordeHorizontal, type RolE2E } from './cuentas'

// M4 - CU-01, CU-02, CU-03: RF-03 a RF-10
// Las cuentas demo no se bloquean: los intentos fallidos usan usuarios que no existen.

const CAPTURAS = process.env.AYNI_CAPTURAS
const CORREOS = process.env.AYNI_CORREOS_URL ?? 'http://localhost:8025'

async function capturar(page: Page, nombre: string) {
  if (!CAPTURAS) return
  await page.evaluate(() => document.fonts.ready)
  // La captura de página completa redibuja: se espera a que todas las imágenes estén listas
  await page.evaluate(() => Promise.all([...document.images].map((i) => i.decode().catch(() => null))))
  await page.screenshot({ path: `${CAPTURAS}/${test.info().project.name}/${nombre}.png`, fullPage: true, animations: 'disabled' })
}

test.describe('inicio y cierre de sesión', () => {
  for (const rol of Object.keys(CUENTAS) as RolE2E[]) {
    test(`${rol}: ingresa con sus datos, ve su nombre y cierra sesión`, async ({ page }) => {
      const c = CUENTAS[rol]
      await page.goto(c.ingreso)
      await sinDesbordeHorizontal(page)
      await llenarIngreso(page, rol)

      await expect(page).toHaveURL(new RegExp(`${c.base}$`))
      await expect(page.getByRole('heading', { level: 1 })).toContainText(c.nombre.split(' ')[0])
      const cuenta = page.getByRole('button', { name: 'Menú de la cuenta' })
      await expect(cuenta).toContainText(c.nombre.split(' ').map((p) => p[0]).join(''))

      await cuenta.click()
      await page.getByRole('menuitem', { name: 'Cerrar sesión' }).click()
      await expect(page.getByText('Cerraste sesión. ¡Hasta pronto!')).toBeVisible()
      await expect(page).toHaveURL(new RegExp(`${c.ingreso}$`))

      // El token ya no sirve: volver al panel pide ingresar otra vez
      await page.goto(c.base)
      await expect(page).toHaveURL(new RegExp(`${c.ingreso}$`))
    })
  }

  test('con datos incorrectos da un mensaje genérico y cambia el captcha', async ({ page }) => {
    await page.goto('/ingresar/docente')
    const imagen = page.getByTestId('captcha-imagen')
    const antes = await imagen.getAttribute('src')

    await llenarIngreso(page, 'docente', 'Clave#Falsa1', '40000001')
    await expect(page.getByRole('alert')).toContainText('Usuario o contraseña incorrectos.')
    await expect(page.getByLabel('Contraseña', { exact: true })).toHaveValue('')
    await expect(imagen).not.toHaveAttribute('src', antes ?? '')
    await capturar(page, '09-ingreso-error')
  })

  test('el botón de la imagen trae un captcha nuevo', async ({ page }) => {
    await page.goto('/ingresar/estudiante')
    const imagen = page.getByTestId('captcha-imagen')
    const antes = await imagen.getAttribute('src')
    await page.getByRole('button', { name: 'Cambiar la imagen del código' }).click()
    await expect(imagen).not.toHaveAttribute('src', antes ?? '')
  })
})

test.describe('captcha real (sin el secreto de pruebas)', () => {
  test.use({ extraHTTPHeaders: {} })

  test('el servidor rechaza un código incorrecto aunque la contraseña sea correcta', async ({ page }) => {
    await page.goto('/ingresar/docente')
    await llenarIngreso(page, 'docente')

    await expect(page.getByText('El código de verificación no es correcto o ya venció.')).toBeVisible()
    await expect(page).toHaveURL(/\/ingresar\/docente$/)
  })
})

test.describe('rutas protegidas (RF-09)', () => {
  test('sin sesión pide ingresar y luego vuelve a la pantalla pedida', async ({ page }) => {
    await page.goto('/docente/asistencia')
    await expect(page).toHaveURL(/\/ingresar\/docente$/)

    await llenarIngreso(page, 'docente')
    await expect(page).toHaveURL(/\/docente\/asistencia$/)
    await expect(page.getByRole('heading', { level: 1, name: 'Asistencia' })).toBeVisible()
  })

  test('un estudiante no puede abrir la sección administrativa', async ({ page }) => {
    await conSesion(page, 'estudiante')
    await page.goto('/admin/usuarios')
    await expect(page.getByRole('heading', { name: 'No tienes permiso para ver esta página' })).toBeVisible()
  })

  test('la API rechaza peticiones sin token o con el rol equivocado', async ({ request }) => {
    expect((await request.get('/api/v1/auth/yo')).status()).toBe(401)
    expect((await request.get('/api/v1/auth/yo', { headers: { Authorization: 'Bearer inventado' } })).status()).toBe(401)
  })

  test('tras 1 hora sin actividad la sesión se cierra (RF-08)', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.addInitScript(() => localStorage.setItem('ayni.actividad', String(Date.now() - 61 * 60 * 1000)))
    await page.goto('/docente')
    await expect(page).toHaveURL(/\/ingresar\/docente$/)
  })
})

test.describe('recuperación de contraseña (RF-06)', () => {
  test('envía el enlace al correo y permite crear la contraseña', async ({ page, request, isMobile }) => {
    // Usa la cuenta "promero" para no cerrar las sesiones de las otras pruebas
    test.skip(isMobile, 'Una sola vez: el enlace llega al mismo buzón')
    const correo = 'patricia.romero@ayni.test'
    const desde = Date.now()

    await page.goto('/ingresar/administracion')
    await page.getByRole('link', { name: '¿Olvidaste tu contraseña?' }).click()
    await expect(page.getByRole('heading', { name: 'Recupera tu contraseña' })).toBeVisible()
    await capturar(page, '10-recuperar')
    await page.getByLabel('Usuario').fill('promero')
    await page.getByLabel('Código de verificación').fill('AAAAA')
    await page.getByRole('button', { name: 'Enviar enlace' }).click()
    await expect(page.getByRole('heading', { name: 'Revisa tu correo' })).toBeVisible()

    // El correo llega a Mailpit (servidor de correo de prueba)
    let enlace = ''
    await expect(async () => {
      const lista = await (await request.get(`${CORREOS}/api/v1/search?query=${encodeURIComponent(`to:${correo}`)}`)).json()
      const nuevo = lista.messages.find((m: { Created: string }) => Date.parse(m.Created) >= desde - 2000)
      expect(nuevo).toBeTruthy()
      const mensaje = await (await request.get(`${CORREOS}/api/v1/message/${nuevo.ID}`)).json()
      enlace = mensaje.HTML.match(/\/restablecer\?token=[A-Za-z0-9]{64}(?:&amp;|&)acceso=admin/)[0].replace('&amp;', '&')
    }).toPass({ timeout: 20_000 })

    await page.goto(enlace)
    await expect(page.getByRole('heading', { name: 'Crea una nueva contraseña' })).toBeVisible()
    await page.getByLabel('Nueva contraseña', { exact: true }).fill('debil')
    await expect(page.getByText('Al menos un símbolo')).toBeVisible()
    await capturar(page, '11-restablecer')
    // Se deja la misma clave del demo para no cambiar las credenciales
    await page.getByLabel('Nueva contraseña', { exact: true }).fill(CUENTAS.admin.clave)
    await page.getByLabel('Repite la nueva contraseña').fill(CUENTAS.admin.clave)
    await page.getByRole('button', { name: 'Guardar contraseña' }).click()
    await expect(page.getByText('Tu contraseña se actualizó. Ya puedes ingresar.')).toBeVisible()

    // El mismo enlace no sirve dos veces
    await page.goto(enlace)
    await page.getByLabel('Nueva contraseña', { exact: true }).fill(CUENTAS.admin.clave)
    await page.getByLabel('Repite la nueva contraseña').fill(CUENTAS.admin.clave)
    await page.getByRole('button', { name: 'Guardar contraseña' }).click()
    await expect(page.getByRole('link', { name: 'Solicitar otro enlace' })).toBeVisible()
  })
})
