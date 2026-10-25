import { readFileSync } from 'node:fs'
import { expect, test, type Page } from '@playwright/test'
import { conSesion, sinDesbordeHorizontal } from './cuentas'

// M12 - CU-27 y CU-28: RF-59 a RF-64 (inicio de cada rol, ficha 360°, mis estudiantes y reportes)

const CAPTURAS = process.env.AYNI_CAPTURAS

async function capturar(page: Page, nombre: string) {
  if (!CAPTURAS) return
  await expect(page.getByRole('button', { name: 'Cerrar aviso' })).toHaveCount(0, { timeout: 8000 })
  await page.evaluate(() => window.scrollTo(0, 0))
  await page.evaluate(() => document.fonts.ready)
  // Las ilustraciones fuera de la vista son diferidas (loading=lazy): se piden ya y no se espera más de 5 s
  await page.evaluate(() =>
    Promise.all(
      [...document.images].map((i) => {
        i.loading = 'eager'
        return Promise.race([i.decode().catch(() => null), new Promise((listo) => setTimeout(listo, 5000))])
      }),
    ),
  )
  await page.screenshot({ path: `${CAPTURAS}/${test.info().project.name}/${nombre}.png`, fullPage: true, animations: 'disabled' })
}

const SALUDO = /^(Buenos días|Buenas tardes|Buenas noches), /
const visible = (l: ReturnType<Page['getByText']>) => l.locator('visible=true').first()

test.describe('inicio de cada rol (RF-59)', () => {
  test('la administración ve los KPIs, lo que necesita su atención y el avance de registro', async ({ page }) => {
    await conSesion(page, 'admin')
    await page.goto('/admin')
    await expect(page.getByRole('heading', { level: 1, name: SALUDO })).toBeVisible()
    await expect(page.locator('#contenido').getByText('Estudiantes matriculados')).toBeVisible()
    await expect(page.getByText(/^Necesitan su atención \(\d+\)$/)).toBeVisible()
    await expect(page.getByRole('progressbar', { name: /^Asistencia: \d+ de \d+ clases dictadas/ })).toBeVisible()
    await sinDesbordeHorizontal(page)
    await capturar(page, '54-admin-inicio')

    await visible(page.getByRole('link', { name: /^Resolver: / })).click()
    await expect(page).not.toHaveURL(/\/admin$/)
  })

  test('el docente ve sus tareas pendientes y sus clases de hoy', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto('/docente')
    await expect(page.getByRole('heading', { level: 1, name: SALUDO })).toBeVisible()
    await expect(page.getByText('Hoy acompañamos con información clara.')).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Mis clases de hoy' })).toBeVisible()
    // Otras pruebas registran asistencia y notas: puede quedar al día
    await expect(page.getByRole('list', { name: 'Tareas pendientes' }).or(page.getByText('No tienes tareas pendientes'))).toBeVisible()
    await sinDesbordeHorizontal(page)
    await capturar(page, '55-docente-inicio')
  })

  test('el estudiante ve su promedio, su asistencia y descarga su boleta en PDF (RF-62)', async ({ page }) => {
    await conSesion(page, 'estudiante')
    await page.goto('/estudiante')
    await expect(page.getByRole('heading', { level: 1, name: 'Hola, Valeria' })).toBeVisible()
    await expect(page.locator('#contenido').getByText('Mi asistencia', { exact: true })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Mis notas por curso' })).toBeVisible()
    await sinDesbordeHorizontal(page)
    await capturar(page, '56-estudiante-inicio')

    const descarga = page.waitForEvent('download')
    await page.getByRole('button', { name: 'Descargar mi boleta' }).click()
    const archivo = await descarga
    expect(archivo.suggestedFilename()).toBe('boleta_e_2026_001.pdf')
    expect(readFileSync(await archivo.path()).subarray(0, 8).toString()).toBe('%PDF-1.4')
  })
})

test.describe('mis estudiantes y ficha 360° (RF-60, RF-61)', () => {
  test('el docente abre la ficha de un estudiante desde su lista', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto('/docente/estudiantes')
    await expect(page.getByRole('heading', { level: 1, name: 'Mis estudiantes' })).toBeVisible()
    await expect(page.getByLabel('Ver')).toBeVisible()
    await sinDesbordeHorizontal(page)
    await capturar(page, '57-docente-mis-estudiantes')

    // El docente demo es tutor: ve toda su aula con el promedio y la asistencia generales
    const tutoria = page.getByLabel('Ver').locator('option', { hasText: /^Mi tutoría/ })
    if (await tutoria.count()) {
      await page.getByLabel('Ver').selectOption('tutoria')
      await expect(page.getByText(/^Tu aula de tutoría/)).toBeVisible()
      await expect(page.getByText('Promedio general').first()).toBeAttached()
    }

    await visible(page.getByRole('link', { name: /^Ver la ficha de / })).click()
    await expect(page).toHaveURL(/\/docente\/ficha\?matricula=\d+$/)
    await expect(page.getByRole('heading', { name: 'Promedio por curso' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Evolución del riesgo' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Boleta en PDF' })).toHaveCount(0)
    await sinDesbordeHorizontal(page)
    await capturar(page, '58-docente-ficha-360')
  })

  test('la administración ve la misma ficha y descarga la boleta', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto('/docente/estudiantes')
    const enlace = visible(page.getByRole('link', { name: /^Ver la ficha de / }))
    const matricula = new URL((await enlace.getAttribute('href'))!, 'http://x').searchParams.get('matricula')

    await conSesion(page, 'admin')
    await page.goto(`/admin/ficha?matricula=${matricula}`)
    await expect(page.getByRole('link', { name: 'Volver a Estudiantes' })).toBeVisible()
    const descarga = page.waitForEvent('download')
    await page.getByRole('button', { name: 'Boleta en PDF' }).click()
    expect((await descarga).suggestedFilename()).toMatch(/^boleta_e_\d{4}_\d{3}\.pdf$/)
  })
})

test.describe('reportes en Excel (RF-63, RF-64)', () => {
  test('la administración descarga la asistencia y el consolidado del aula', async ({ page }) => {
    await conSesion(page, 'admin')
    await page.goto('/admin/reportes')
    await expect(page.getByRole('heading', { level: 1, name: 'Reportes' })).toBeVisible()
    await sinDesbordeHorizontal(page)
    await capturar(page, '59-admin-reportes')

    for (const [boton, aviso] of [
      ['Descargar asistencia', 'Asistencia descargada.'],
      ['Descargar consolidado', 'Consolidado descargado.'],
    ]) {
      const descarga = page.waitForEvent('download')
      await page.getByRole('button', { name: boton }).click()
      expect((await descarga).suggestedFilename()).toMatch(/\.xlsx$/)
      await expect(page.getByText(aviso)).toBeVisible()
    }
  })
})
