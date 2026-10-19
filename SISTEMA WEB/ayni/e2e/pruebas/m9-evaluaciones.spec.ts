import { expect, test, type Page } from '@playwright/test'
import { conSesion, sinDesbordeHorizontal, tokenDe } from './cuentas'

// M9 - CU-16 a CU-19: RF-38 a RF-48
// Cada corrida crea una práctica nueva en Matemática · 3.º B (no se puede borrar una evaluación con entregas);
// la opción 14 del panel vuelve a los datos de demostración.

const CAPTURAS = process.env.AYNI_CAPTURAS

async function capturar(page: Page, nombre: string) {
  if (!CAPTURAS) return
  await expect(page.getByRole('button', { name: 'Cerrar aviso' })).toHaveCount(0, { timeout: 8000 })
  await page.evaluate(() => window.scrollTo(0, 0))
  await page.evaluate(() => document.fonts.ready)
  await page.screenshot({ path: `${CAPTURAS}/${test.info().project.name}/${nombre}.png`, fullPage: true, animations: 'disabled' })
}

const PDF = Buffer.from(
  '%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n',
)

const iso = (d: Date) => d.toLocaleDateString('en-CA', { timeZone: 'America/Lima' })

const visible = (l: ReturnType<Page['getByText']>) => l.locator('visible=true').first()

test.describe('evaluación → entrega → nota → publicar → el estudiante la ve (E2E del plan)', () => {
  test.skip(({ isMobile }) => isMobile, 'Solo en PC: cambia datos')
  test.describe.configure({ mode: 'serial' })
  const titulo = `Práctica E2E ${Date.now().toString().slice(-6)}`
  let curso = 0

  test.beforeAll(async ({ request }) => {
    const r = await request.get('/api/v1/docente/asistencia/cursos', { headers: { Authorization: `Bearer ${tokenDe('docente')}` } })
    curso = (await r.json()).cursos.find((c: { nombre: string }) => c.nombre === 'Matemática · 3.º B').id
  })

  test('el docente crea la práctica y le sube material de apoyo', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto(`/docente/evaluaciones?curso=${curso}`)
    await expect(page.getByRole('heading', { name: 'Evaluaciones', level: 1 })).toBeVisible()
    await capturar(page, '35-docente-evaluaciones')

    await page.getByRole('button', { name: 'Nueva evaluación' }).click()
    const v = page.getByRole('dialog', { name: 'Nueva evaluación' })
    await v.getByRole('button', { name: 'Crear evaluación' }).click()
    await expect(v.getByText('Escribe el título de la evaluación.')).toBeVisible()

    const hoy = new Date()
    const limite = new Date(hoy.getTime() + 2 * 86_400_000)
    await v.getByLabel('Título').fill(titulo)
    await v.getByLabel('Fecha de asignación').fill(iso(hoy))
    await v.getByLabel('Fecha límite').fill(iso(limite))
    await v.getByLabel('Indicaciones (opcional)').fill('Resuelve los ejercicios 1 al 12.\nIncluye el procedimiento en cada respuesta.')
    await v.getByRole('button', { name: 'Crear evaluación' }).click()
    await expect(page.getByText('Evaluación creada.')).toBeVisible()

    const editar = page.getByRole('dialog', { name: 'Editar evaluación' })
    await editar.locator('input[type=file]').setInputFiles({ name: 'Guía de la práctica.pdf', mimeType: 'application/pdf', buffer: PDF })
    await expect(page.getByText('Material de apoyo agregado.')).toBeVisible()
    await expect(editar.getByText('Guía de la práctica.pdf')).toBeVisible()
    await editar.getByRole('button', { name: 'Listo' }).click()
    await expect(visible(page.getByText(titulo))).toBeVisible()
  })

  test('la estudiante descarga la guía y entrega su PDF', async ({ page }) => {
    await conSesion(page, 'estudiante')
    await page.goto('/estudiante/actividades')
    await expect(page.getByRole('heading', { name: 'Actividades', level: 1 })).toBeVisible()
    await capturar(page, '38-estudiante-actividades')
    await page.getByRole('link', { name: `Ver ${titulo}` }).locator('visible=true').first().click()

    await expect(page.getByRole('heading', { name: titulo, level: 1 })).toBeVisible()
    await expect(page.getByText('Incluye el procedimiento en cada respuesta.')).toBeVisible()
    const descarga = page.waitForEvent('download')
    await page.getByRole('button', { name: 'Descargar Guía de la práctica.pdf' }).click()
    expect((await descarga).suggestedFilename()).toBe('Guía de la práctica.pdf')

    const entrada = page.getByLabel('Archivo PDF de la entrega')
    await entrada.setInputFiles({ name: 'tarea.exe', mimeType: 'application/octet-stream', buffer: Buffer.from('MZ') })
    await expect(page.getByText('El archivo debe ser un PDF.')).toBeVisible()
    await entrada.setInputFiles({ name: 'mi-tarea.pdf', mimeType: 'application/pdf', buffer: PDF })
    await capturar(page, '39-estudiante-actividad')
    await page.getByRole('button', { name: 'Entregar actividad' }).click()

    await expect(page.getByText('¡Actividad entregada!')).toBeVisible()
    await expect(page.getByText('Ya entregaste esta actividad.', { exact: false })).toBeVisible()
    await expect(page.getByText(/· Actividad entregada$/)).toBeVisible()
  })

  test('el docente califica: bloquea letras, rechaza 25 y guarda 17,5', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto(`/docente/evaluaciones?curso=${curso}`)
    await page.getByRole('link', { name: `Calificar ${titulo}` }).locator('visible=true').first().click()

    await expect(page.getByRole('heading', { name: titulo, level: 1 })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Descargar la entrega de Quispe Mendoza, Valeria' })).toBeVisible()
    const nota = page.getByLabel('Nota de Quispe Mendoza, Valeria')
    await nota.pressSequentially('abc')
    await expect(nota).toHaveValue('')
    await nota.fill('25')
    await expect(page.getByText('La nota debe estar entre 0 y 20.')).toBeVisible()
    await nota.fill('17,5')
    await expect(page.getByText('Cambios sin guardar')).toBeVisible()
    await capturar(page, '36-docente-notas')
    await page.getByRole('button', { name: 'Guardar notas' }).click()
    await expect(page.getByText('Notas guardadas.')).toBeVisible()
    await expect(nota).toHaveValue('17,5')

    const descarga = page.waitForEvent('download')
    await page.getByRole('button', { name: 'Exportar' }).click()
    expect((await descarga).suggestedFilename()).toBe('notas.xlsx')
  })

  test('el docente publica los promedios y la estudiante los ve', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto(`/docente/calificaciones?curso=${curso}`)
    await expect(page.getByRole('heading', { name: /^Calificaciones del/ })).toBeVisible()
    await expect(page.getByRole('columnheader', { name: new RegExp(titulo) })).toBeVisible()
    await capturar(page, '37-docente-calificaciones')

    // Si una corrida anterior ya publicó, el curso sigue publicado
    const publicar = page.getByRole('button', { name: 'Publicar promedios' })
    if (await publicar.isVisible()) {
      await publicar.click()
      const d = page.getByRole('dialog', { name: 'Publicar promedios' })
      await expect(d.getByText(/Se pondrá 0 en lo que falta calificar|Todas las evaluaciones tienen nota/)).toBeVisible()
      await d.getByRole('button', { name: 'Sí, publicar' }).click()
      await expect(page.getByText('Promedios publicados. Los estudiantes ya pueden ver sus notas.')).toBeVisible()
    }
    await expect(page.getByText('Publicada', { exact: true })).toBeVisible()

    await conSesion(page, 'estudiante')
    await page.goto('/estudiante/notas')
    await expect(page.getByRole('heading', { name: 'Mis notas', level: 1 })).toBeVisible()
    await capturar(page, '40-estudiante-notas')
    await page.getByRole('link', { name: 'Ver detalle de Matemática' }).click()
    await expect(page.getByRole('heading', { name: 'Matemática', level: 1 })).toBeVisible()
    await expect(page.getByRole('listitem').filter({ hasText: titulo })).toContainText('17,5')
    await capturar(page, '41-estudiante-notas-curso')
  })
})

test.describe('pantallas en PC y celular', () => {
  test('estudiante: horario de la semana y próxima clase', async ({ page }) => {
    await conSesion(page, 'estudiante')
    await page.goto('/estudiante/horario')
    await expect(page.getByRole('heading', { name: 'Mi horario', level: 1 })).toBeVisible()
    await expect(page.getByText('Próxima clase')).toBeVisible()
    await expect(visible(page.getByText('Matemática'))).toBeVisible()
    await sinDesbordeHorizontal(page)
    await capturar(page, '42-estudiante-horario')
  })

  test('estudiante: actividades y notas sin desbordes', async ({ page }) => {
    await conSesion(page, 'estudiante')
    await page.goto('/estudiante/actividades')
    await expect(page.getByRole('tab', { name: /Pendientes/ })).toBeVisible()
    await sinDesbordeHorizontal(page)
    await page.goto('/estudiante/notas')
    await expect(page.getByText('Notas por curso')).toBeVisible()
    await sinDesbordeHorizontal(page)
  })

  test('docente: evaluaciones, notas y calificaciones sin desbordes', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto('/docente/evaluaciones')
    await expect(page.getByRole('heading', { name: 'Evaluaciones', level: 1 })).toBeVisible()
    await expect(visible(page.getByText('Examen bimestral'))).toBeVisible()
    await sinDesbordeHorizontal(page)
    await page.goto('/docente/notas')
    await expect(page.getByRole('heading', { name: 'Notas', level: 1 })).toBeVisible()
    await sinDesbordeHorizontal(page)
    await page.goto('/docente/calificaciones')
    await expect(page.getByRole('heading', { name: /^Calificaciones del/ })).toBeVisible()
    await sinDesbordeHorizontal(page)
  })
})
