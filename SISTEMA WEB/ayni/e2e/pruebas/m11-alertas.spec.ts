import { expect, test, type Page } from '@playwright/test'
import { conSesion, sinDesbordeHorizontal } from './cuentas'

// M11 - CU-24 a CU-26: RF-52 a RF-58
// Cada corrida cierra una alerta abierta de la demo; la opción 14 del panel vuelve a los datos de demostración.

const CAPTURAS = process.env.AYNI_CAPTURAS

async function capturar(page: Page, nombre: string) {
  if (!CAPTURAS) return
  await expect(page.getByRole('button', { name: 'Cerrar aviso' })).toHaveCount(0, { timeout: 8000 })
  await page.evaluate(() => window.scrollTo(0, 0))
  await page.evaluate(() => document.fonts.ready)
  await page.screenshot({ path: `${CAPTURAS}/${test.info().project.name}/${nombre}.png`, fullPage: true, animations: 'disabled' })
}

const visible = (l: ReturnType<Page['getByText']>) => l.locator('visible=true').first()

test.describe('recalcular y seguir una alerta hasta cerrarla (Prueba tú mismo)', () => {
  test.skip(({ isMobile }) => isMobile, 'Solo en PC: cambia datos')
  test.describe.configure({ mode: 'serial' })

  test('la administración recalcula el riesgo en el panel', async ({ page }) => {
    await conSesion(page, 'admin')
    await page.goto('/admin/riesgo')
    await expect(page.getByRole('heading', { name: 'Panel de riesgo académico', level: 1 })).toBeVisible()
    await expect(page.getByText('Riesgo alto', { exact: true })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Distribución por aula' })).toBeVisible()
    await page.getByRole('button', { name: 'Recalcular' }).click()
    await expect(page.getByText(/^Riesgo recalculado para \d+ estudiantes/)).toBeVisible()
    await capturar(page, '48-admin-panel-riesgo')
  })

  test('ve las alertas estudiantiles y las administrativas', async ({ page }) => {
    await conSesion(page, 'admin')
    await page.goto('/admin/alertas')
    await expect(page.getByRole('heading', { name: 'Alertas', level: 1 })).toBeVisible()
    await expect(visible(page.getByRole('link', { name: /^Abrir la alerta de / }))).toBeVisible()
    await capturar(page, '49-admin-alertas')

    await page.getByRole('tab', { name: 'Administrativas' }).click()
    await expect(visible(page.getByText(/^Cuenta bloqueada: /))).toBeVisible()
    await expect(visible(page.getByText(/estudiantes? sin matrícula/))).toBeVisible()
    await capturar(page, '50-admin-alertas-administrativas')
  })

  test('abre una alerta, registra una intervención y la cierra con justificación', async ({ page }) => {
    await conSesion(page, 'admin')
    await page.goto('/admin/alertas')
    await visible(page.getByRole('link', { name: /^Abrir la alerta de / })).click()

    await expect(page.getByRole('heading', { name: '¿Por qué el sistema lo señala?' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Acciones recomendadas' })).toBeVisible()
    await capturar(page, '51-admin-detalle-alerta')

    const seguimiento = page.getByRole('button', { name: 'Pasar a seguimiento' })
    if (await seguimiento.isVisible()) {
      await seguimiento.click()
      await expect(page.getByText('La alerta pasó a seguimiento.')).toBeVisible()
    }

    await page.getByRole('button', { name: 'Registrar intervención' }).click()
    const v = page.getByRole('dialog', { name: 'Registrar intervención' })
    await v.getByRole('button', { name: 'Guardar intervención' }).click()
    await expect(v.getByText('Describe la intervención.')).toBeVisible()
    await v.getByLabel('Tipo').selectOption('Reunión con el apoderado')
    await v.getByLabel('¿Qué se hizo?').fill('Reunión con la madre: se acordó revisar juntos la asistencia cada semana.')
    await v.getByLabel('Resultado (opcional)').fill('Se comprometió a avisar las faltas.')
    await v.getByRole('button', { name: 'Guardar intervención' }).click()
    await expect(page.getByText('Intervención registrada.')).toBeVisible()
    await expect(page.getByRole('list', { name: 'Intervenciones' })).toContainText('Reunión con la madre')

    await page.getByRole('button', { name: 'Cerrar alerta' }).click()
    const cerrar = page.getByRole('dialog', { name: 'Cerrar alerta' })
    await cerrar.getByRole('button', { name: 'Cerrar alerta' }).click()
    await expect(cerrar.getByText('Escribe la justificación.')).toBeVisible()
    await cerrar.getByLabel('Justificación').fill('Asistió a todas las clases desde la reunión con la familia.')
    await cerrar.getByRole('button', { name: 'Cerrar alerta' }).click()

    await expect(page.getByText('Alerta cerrada.')).toBeVisible()
    await expect(page.getByText('Cerrada', { exact: true }).first()).toBeVisible()
    await expect(page.getByRole('list', { name: 'Historial de la alerta' })).toContainText('Alerta cerrada')
    await expect(page.getByRole('button', { name: 'Registrar intervención' })).toHaveCount(0)
  })

  test('el docente ve las alertas de sus aulas y su panel de coordinación', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto('/docente/alertas')
    await expect(page.getByRole('heading', { name: 'Alertas asignadas', level: 1 })).toBeVisible()
    await page.getByLabel('Encargado').selectOption('todas')
    await expect(visible(page.getByRole('link', { name: /^Abrir la alerta de / }))).toBeVisible()
    await capturar(page, '52-docente-alertas')

    await page.goto('/docente/coordinacion')
    await expect(page.getByRole('heading', { name: 'Panel de coordinación · 3.º A', level: 1 })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Estudiantes que requieren atención primero' })).toBeVisible()
    await capturar(page, '53-docente-coordinacion')
  })
})

test.describe('pantallas en PC y celular', () => {
  test('panel, alertas y detalle sin desbordes', async ({ page }) => {
    await conSesion(page, 'admin')
    await page.goto('/admin/riesgo')
    await expect(page.getByRole('heading', { name: 'Factores más frecuentes' })).toBeVisible()
    await sinDesbordeHorizontal(page)
    await page.goto('/admin/alertas?pestana=administrativas')
    await expect(page.getByRole('tab', { name: 'Administrativas', selected: true })).toBeVisible()
    await sinDesbordeHorizontal(page)
    await page.goto('/admin/alertas')
    await visible(page.getByRole('link', { name: /^Abrir la alerta de / })).click()
    await expect(page.getByRole('heading', { name: 'Historial e intervenciones' })).toBeVisible()
    await sinDesbordeHorizontal(page)
  })

  test('el docente: alertas y coordinación sin desbordes', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto('/docente/alertas')
    await expect(page.getByText('Sin primera intervención')).toBeVisible()
    await sinDesbordeHorizontal(page)
    await page.goto('/docente/coordinacion')
    await expect(page.getByRole('heading', { name: /^Panel de coordinación/ })).toBeVisible()
    await sinDesbordeHorizontal(page)
  })

  test('el estudiante no entra a las alertas', async ({ page }) => {
    await conSesion(page, 'estudiante')
    await page.goto('/estudiante/alertas')
    await expect(page.getByText('404').first()).toBeVisible()
  })
})
