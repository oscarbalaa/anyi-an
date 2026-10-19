import { expect, test, type Page } from '@playwright/test'
import { conSesion, sinDesbordeHorizontal, tokenDe } from './cuentas'

// M10 - CU-22 y CU-23: RF-49 a RF-51
// Cada corrida registra una incidencia nueva para Valeria; la opción 14 del panel vuelve a los datos de demostración.

const CAPTURAS = process.env.AYNI_CAPTURAS

async function capturar(page: Page, nombre: string) {
  if (!CAPTURAS) return
  await expect(page.getByRole('button', { name: 'Cerrar aviso' })).toHaveCount(0, { timeout: 8000 })
  await page.evaluate(() => window.scrollTo(0, 0))
  await page.evaluate(() => document.fonts.ready)
  await page.screenshot({ path: `${CAPTURAS}/${test.info().project.name}/${nombre}.png`, fullPage: true, animations: 'disabled' })
}

const visible = (l: ReturnType<Page['getByText']>) => l.locator('visible=true').first()

test.describe('incidencia + nota publicada → el estudiante ve las 2 notificaciones (Prueba tú mismo)', () => {
  test.skip(({ isMobile }) => isMobile, 'Solo en PC: cambia datos')
  test.describe.configure({ mode: 'serial' })
  const descripcion = `Interrumpió la clase de forma reiterada (E2E ${Date.now().toString().slice(-6)}).`

  test('el docente registra una incidencia grave de Valeria', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto('/docente/incidencias')
    await expect(page.getByRole('heading', { name: 'Incidencias', level: 1 })).toBeVisible()
    await capturar(page, '43-docente-incidencias')

    const form = page.locator('#registrar-incidencia')
    await form.getByRole('button', { name: 'Guardar incidencia' }).click()
    await expect(form.getByText('Elige al estudiante.')).toBeVisible()
    await expect(form.getByText('Describe lo ocurrido.')).toBeVisible()

    await form.getByLabel('Estudiante', { exact: true }).fill('quispe')
    await form.getByRole('button', { name: 'Elegir a Quispe Mendoza, Valeria' }).click()
    await expect(form.getByTestId('estudiante-elegido')).toHaveText('Quispe Mendoza, Valeria')
    await form.getByLabel('Tipo').selectOption({ label: 'Conducta' })
    await form.getByLabel('Gravedad').selectOption('GRAVE')
    await form.getByLabel('Descripción').fill('mal')
    await form.getByRole('button', { name: 'Guardar incidencia' }).click()
    await expect(form.getByText('Describe lo ocurrido en 10 a 500 caracteres.')).toBeVisible()
    await form.getByLabel('Descripción').fill(descripcion)
    await form.getByRole('button', { name: 'Guardar incidencia' }).click()

    await expect(page.getByText('Incidencia registrada.')).toBeVisible()
    await expect(visible(page.getByText(descripcion))).toBeVisible()
    await expect(form.getByLabel('Descripción')).toHaveValue('')
  })

  test('el docente publica los promedios de Matemática · 3.º B', async ({ page, request }) => {
    const r = await request.get('/api/v1/docente/asistencia/cursos', { headers: { Authorization: `Bearer ${tokenDe('docente')}` } })
    const curso = (await r.json()).cursos.find((c: { nombre: string }) => c.nombre === 'Matemática · 3.º B').id
    await conSesion(page, 'docente')
    await page.goto(`/docente/calificaciones?curso=${curso}`)
    await expect(page.getByRole('heading', { name: /^Calificaciones del/ })).toBeVisible()
    // Si otra corrida ya publicó, el curso sigue publicado y su notificación ya existe
    const publicar = page.getByRole('button', { name: 'Publicar promedios' })
    if (await publicar.isVisible()) {
      await publicar.click()
      await page.getByRole('dialog', { name: 'Publicar promedios' }).getByRole('button', { name: 'Sí, publicar' }).click()
      await expect(page.getByText('Promedios publicados. Los estudiantes ya pueden ver sus notas.')).toBeVisible()
    }
    await expect(page.getByText('Publicada', { exact: true })).toBeVisible()
  })

  test('la estudiante ve las 2 notificaciones en la campana y en su pantalla', async ({ page }) => {
    await conSesion(page, 'estudiante')
    await page.goto('/estudiante')
    const campana = page.getByRole('button', { name: /^Notificaciones \(\d+ sin leer\)$/ })
    await expect(campana).toBeVisible()
    await campana.click()
    const panel = page.getByRole('region', { name: 'Últimas notificaciones' })
    await expect(panel.getByText('Incidencia registrada').first()).toBeVisible()
    await expect(panel.getByText(/Notas (publicadas|cerradas)/).first()).toBeVisible()
    await capturar(page, '44-estudiante-campana')
    await panel.getByRole('link', { name: 'Ver todas' }).click()

    await expect(page.getByRole('heading', { name: 'Notificaciones', level: 1 })).toBeVisible()
    const lista = page.getByRole('list', { name: 'Notificaciones' })
    await expect(lista.getByText('Se registró una incidencia de tipo Conducta (grave)', { exact: false }).first()).toBeVisible()
    await expect(lista.getByText(/Notas (publicadas|cerradas)/).first()).toBeVisible()
    await capturar(page, '45-estudiante-notificaciones')

    await page.getByRole('button', { name: 'Marcar todas como leídas' }).first().click()
    await expect(page.getByText('Todas las notificaciones quedaron como leídas.')).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Vas al día con tus avisos' })).toBeVisible()
    await expect(page.getByTestId('contador-notificaciones')).toHaveCount(0)
  })

  test('la administración recibe el aviso de la incidencia grave y abre el historial', async ({ page }) => {
    await conSesion(page, 'admin')
    await page.goto('/admin/notificaciones')
    await expect(page.getByRole('heading', { name: 'Notificaciones', level: 1 })).toBeVisible()
    await capturar(page, '46-admin-notificaciones')
    await page.getByRole('link', { name: 'Ver incidencias: Incidencia grave' }).first().click()

    await expect(page).toHaveURL(/\/admin\/incidencias\?matricula=\d+/)
    await expect(page.getByText('Historial de Valeria Quispe Mendoza')).toBeVisible()
    await expect(visible(page.getByText(descripcion))).toBeVisible()
    await capturar(page, '47-admin-incidencias')
    await page.getByRole('button', { name: 'Ver todas' }).click()
    await expect(page.getByText('Historial de Valeria Quispe Mendoza')).toBeHidden()
  })
})

test.describe('pantallas en PC y celular', () => {
  test('la campana y las notificaciones caben en la pantalla', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto('/docente/notificaciones')
    await expect(page.getByRole('heading', { name: 'Notificaciones', level: 1 })).toBeVisible()
    await expect(page.getByRole('tab', { name: /Sin leer/ })).toBeVisible()
    await sinDesbordeHorizontal(page)
    await page.getByRole('button', { name: /^Notificaciones/ }).click()
    const panel = page.getByRole('region', { name: 'Últimas notificaciones' })
    await expect(panel).toBeVisible()
    const caja = await panel.boundingBox()
    const ancho = page.viewportSize()?.width ?? 0
    expect(caja && caja.x >= 0 && caja.x + caja.width <= ancho).toBeTruthy()
    await page.keyboard.press('Escape')
    await expect(panel).toBeHidden()
  })

  test('incidencias del docente y del administrador sin desbordes', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto('/docente/incidencias')
    await expect(page.getByRole('heading', { name: 'Registrar incidencia' })).toBeVisible()
    await sinDesbordeHorizontal(page)
    await conSesion(page, 'admin')
    await page.goto('/admin/incidencias')
    await expect(page.getByRole('list', { name: 'Incidencias por gravedad' })).toBeVisible()
    await sinDesbordeHorizontal(page)
  })

  test('el estudiante no entra a incidencias', async ({ page }) => {
    await conSesion(page, 'estudiante')
    await page.goto('/estudiante/incidencias')
    await expect(page.getByText('404').first()).toBeVisible()
  })
})
