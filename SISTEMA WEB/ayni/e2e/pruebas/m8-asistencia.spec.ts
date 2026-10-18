import { expect, test, type APIRequestContext, type Page } from '@playwright/test'
import { conSesion, sinDesbordeHorizontal, tokenDe, type RolE2E } from './cuentas'

// M8 - CU-13 a CU-15: RF-33 a RF-37
// La prueba registra la última clase pasada de Luis Paredes en Matemática · 3.º B (el seeder la deja sin
// registrar). Para poder repetirse, antes el administrador borra ese registro si ya existe.

const CAPTURAS = process.env.AYNI_CAPTURAS

async function capturar(page: Page, nombre: string) {
  if (!CAPTURAS) return
  await expect(page.getByRole('button', { name: 'Cerrar aviso' })).toHaveCount(0, { timeout: 8000 })
  await page.evaluate(() => window.scrollTo(0, 0))
  await page.evaluate(() => document.fonts.ready)
  await page.screenshot({ path: `${CAPTURAS}/${test.info().project.name}/${nombre}.png`, fullPage: true, animations: 'disabled' })
}

const visible = (l: ReturnType<Page['getByText']>) => l.locator('visible=true').first()

const api = async <T>(request: APIRequestContext, rol: RolE2E, ruta: string): Promise<T> => {
  const r = await request.get(`/api/v1${ruta}`, { headers: { Authorization: `Bearer ${tokenDe(rol)}` } })
  expect(r.ok(), `${ruta}: ${r.status()}`).toBeTruthy()
  return r.json()
}

const hoy = () => new Date().toLocaleDateString('en-CA', { timeZone: 'America/Lima' })

/** Igual que la interfaz: "jue 15 oct" */
const fechaCorta = (iso: string) => {
  const [a, m, d] = iso.split('-').map(Number)
  return new Date(a, m - 1, d).toLocaleDateString('es-PE', { weekday: 'short', day: 'numeric', month: 'short' }).replace(/[.,]/g, '')
}

type Fechas = { fechas: { fecha: string; estado: string; hoy: boolean }[] }

/** Curso de Matemática 3.º B y su última clase anterior a hoy, ya sin registro */
async function prepararClase(request: APIRequestContext) {
  const { cursos } = await api<{ cursos: { id: number; nombre: string }[] }>(request, 'docente', '/docente/asistencia/cursos')
  const curso = cursos.find((c) => c.nombre === 'Matemática · 3.º B')!
  const { fechas } = await api<Fechas>(request, 'docente', `/docente/asistencia/cursos/${curso.id}/fechas`)
  const clase = fechas.find((f) => f.fecha < hoy())!
  if (clase.estado !== 'SIN_REGISTRO') {
    const r = await request.delete(`/api/v1/admin/asistencia/cursos/${curso.id}/${clase.fecha}`, {
      headers: { Authorization: `Bearer ${tokenDe('admin')}` },
      data: { motivo: 'Prueba E2E: se vuelve a registrar la clase.' },
    })
    expect(r.ok()).toBeTruthy()
  }
  return { curso: curso.id, fecha: clase.fecha }
}

const fila = (page: Page, nombre: string) => page.getByRole('group', { name: `Asistencia de ${nombre}` })

test.describe('registro, consulta y corrección', () => {
  test.skip(({ isMobile }) => isMobile, 'Solo en PC: cambia datos')
  test.describe.configure({ mode: 'serial' })
  let clase: { curso: number; fecha: string }

  test.beforeAll(async ({ request }) => {
    clase = await prepararClase(request)
  })

  test('el docente registra su última clase con una falta y una tardanza', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto(`/docente/asistencia?curso=${clase.curso}&fecha=${clase.fecha}`)
    await expect(page.getByRole('heading', { name: 'Registro de asistencia', level: 1 })).toBeVisible()
    await expect(page.getByText('Sin registrar', { exact: true })).toBeVisible()
    await expect(page.getByText(/Clase pasada sin registrar/).first()).toBeVisible()

    await fila(page, 'Quispe Mendoza, Valeria').getByRole('radio', { name: 'Falta', exact: true }).check({ force: true })
    await fila(page, 'Aranda Ruiz, Kevin').getByRole('radio', { name: 'Tardanza' }).check({ force: true })
    await page.getByLabel('Observación de Aranda Ruiz, Kevin').fill('Llegó 8:20')
    await expect(page.getByTestId('conteo-asistencia')).toContainText('1 falta · 1 tardanza')
    await capturar(page, '31-docente-asistencia')

    await page.getByRole('button', { name: 'Guardar asistencia' }).click()
    await expect(page.getByText('Asistencia guardada.')).toBeVisible()
    // Clase pasada: una vez definitiva, el docente ya no la corrige (RN-07)
    await expect(page.getByText(/solo el administrador puede corregir esta asistencia/)).toBeVisible()
    await expect(fila(page, 'Quispe Mendoza, Valeria').getByRole('radio', { name: 'Falta', exact: true })).toBeChecked()
    await expect(fila(page, 'Quispe Mendoza, Valeria').getByRole('radio', { name: 'Presente' })).toBeDisabled()
  })

  test('la estudiante ve la falta en su asistencia', async ({ page }) => {
    await conSesion(page, 'estudiante')
    await page.goto('/estudiante/asistencia')
    await expect(page.getByRole('heading', { name: 'Mi asistencia', level: 1 })).toBeVisible()
    await page.getByLabel('Mostrar').selectOption('F')
    const tabla = page.getByRole('table', { name: 'Registros de asistencia' })
    await expect(tabla.getByRole('row').filter({ hasText: fechaCorta(clase.fecha) }).filter({ hasText: 'Matemática' })).toContainText('Falta')
    await page.getByLabel('Mostrar').selectOption('')
    await capturar(page, '32-estudiante-asistencia')
  })

  test('el administrador justifica la falta y queda registrado quién lo hizo', async ({ page }) => {
    await conSesion(page, 'admin')
    await page.goto(`/admin/asistencia?pestana=corregir&curso=${clase.curso}&fecha=${clase.fecha}`)
    await expect(page.getByText(/Como administrador puedes corregir/).first()).toBeVisible()
    await fila(page, 'Quispe Mendoza, Valeria').getByRole('radio', { name: 'Falta justificada' }).check({ force: true })
    await page.getByRole('button', { name: 'Guardar corrección' }).click()
    await expect(page.getByText('Escribe el motivo de la justificación.')).toBeVisible()

    await page.getByLabel('Observación de Quispe Mendoza, Valeria').fill('Presentó certificado médico')
    await page.getByRole('button', { name: 'Guardar corrección' }).click()
    await expect(page.getByText('Asistencia corregida. El cambio queda registrado con tu nombre.')).toBeVisible()
    await expect(page.getByText(/corregido por Carlos Medina/)).toBeVisible()
    await capturar(page, '34-admin-corregir')

    await conSesion(page, 'estudiante')
    await page.goto('/estudiante/asistencia')
    await page.getByLabel('Mostrar').selectOption('J')
    await expect(
      page.getByRole('table', { name: 'Registros de asistencia' }).getByRole('row').filter({ hasText: fechaCorta(clase.fecha) }).filter({ hasText: 'Matemática' }),
    ).toContainText('Presentó certificado médico')
  })
})

test.describe('pantallas en PC y celular', () => {
  test('docente: elige curso y fecha de clase', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto('/docente/asistencia')
    await expect(page.getByLabel('Curso')).toBeVisible()
    await expect(page.getByLabel('Fecha de la clase').locator('option').first()).toBeAttached()
    await expect(page.getByRole('group').first()).toBeVisible()
    await sinDesbordeHorizontal(page)
  })

  test('estudiante: porcentaje, calendario y registros', async ({ page }) => {
    await conSesion(page, 'estudiante')
    await page.goto('/estudiante/asistencia')
    await expect(page.getByText('Asistencia', { exact: true }).first()).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Calendario' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Resumen por curso' })).toBeVisible()
    await sinDesbordeHorizontal(page)
    if (test.info().project.name === 'celular') await capturar(page, '32-estudiante-asistencia')
  })

  test('administrador: % por aula y docentes con clases sin registrar', async ({ page }) => {
    await conSesion(page, 'admin')
    await page.goto('/admin/asistencia')
    await expect(page.getByRole('heading', { name: 'Asistencia', level: 1 })).toBeVisible()
    await expect(visible(page.getByText('3.º B', { exact: true }))).toBeVisible()
    // El seeder deja a dos docentes sin registrar su última semana
    await expect(page.getByText('Ana Lozano')).toBeVisible()
    await sinDesbordeHorizontal(page)
    await capturar(page, '33-admin-asistencia')
  })
})
