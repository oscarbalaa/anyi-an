import { expect, test, type APIRequestContext, type Page } from '@playwright/test'
import { conSesion, sinDesbordeHorizontal, tokenDe } from './cuentas'

// M6 - CU-06, CU-07 y CU-08: RF-17 a RF-26
// El armado completo usa un periodo de prueba (2099) que se borra al final; los datos demo no cambian.

const CAPTURAS = process.env.AYNI_CAPTURAS
const PRUEBA = '2099'

async function capturar(page: Page, nombre: string) {
  if (!CAPTURAS) return
  // Sin avisos flotantes y desde arriba, para que la captura de página completa salga limpia
  // Los avisos se cierran solos a los 5 segundos
  await expect(page.getByRole('button', { name: 'Cerrar aviso' })).toHaveCount(0, { timeout: 8000 })
  await page.evaluate(() => window.scrollTo(0, 0))
  await page.evaluate(() => document.fonts.ready)
  await page.evaluate(() => Promise.all([...document.images].map((i) => i.decode().catch(() => null))))
  await page.screenshot({ path: `${CAPTURAS}/${test.info().project.name}/${nombre}.png`, fullPage: true, animations: 'disabled' })
}

/** Borra el periodo de prueba con todo lo que se le haya creado */
async function limpiarPrueba(request: APIRequestContext) {
  const headers = { Authorization: `Bearer ${tokenDe('admin')}` }
  const { periodos } = await (await request.get('/api/v1/admin/periodos', { headers })).json()
  const periodo = periodos.find((p: { nombre: string }) => p.nombre === PRUEBA)
  if (!periodo) return
  const { aulas } = await (await request.get(`/api/v1/admin/aulas?periodo_id=${periodo.id}`, { headers })).json()
  for (const aula of aulas) {
    const { cursos } = await (await request.get(`/api/v1/admin/aulas/${aula.id}/carga`, { headers })).json()
    for (const c of cursos) await request.delete(`/api/v1/admin/cursos/${c.id}`, { headers })
    await request.delete(`/api/v1/admin/aulas/${aula.id}`, { headers })
  }
  await request.delete(`/api/v1/admin/periodos/${periodo.id}`, { headers })
}

/** La tabla trae su versión de PC y de celular: se usa la que está a la vista */
const visible = (l: ReturnType<Page['getByText']>) => l.locator('visible=true').first()

const pestana = (page: Page, nombre: string) => page.getByRole('tab', { name: nombre }).click()

/** Elige la primera opción cuyo texto empieza con el indicado */
async function elegir(lista: ReturnType<Page['getByLabel']>, texto: string) {
  const valor = await lista.locator('option').filter({ hasText: texto }).first().getAttribute('value')
  await lista.selectOption(valor ?? '')
}

test.describe('estructura académica (consulta)', () => {
  test.beforeEach(async ({ page }) => {
    await conSesion(page, 'admin')
    await page.goto('/admin/estructura')
  })

  test('periodos: el año en curso con sus 4 bimestres y su fecha de cierre', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Estructura académica', level: 1 })).toBeVisible()
    await expect(page.getByRole('tab', { name: 'Periodos' })).toHaveAttribute('aria-selected', 'true')
    const panel = page.getByRole('region', { name: /^Periodo \d{4}$/ })
    await expect(panel.getByText('IV Bimestre')).toBeVisible()
    await expect(panel.getByText(/Cierre de notas:/).first()).toBeVisible()
    await expect(panel.getByText('En curso').or(panel.getByText('Notas abiertas')).first()).toBeVisible()
    await sinDesbordeHorizontal(page)
    await capturar(page, '17-estructura-periodos')
  })

  test('grados y aulas: tutor, coordinador y matriculados de cada aula', async ({ page }) => {
    await pestana(page, 'Grados y aulas')
    await expect(visible(page.getByText('3.º A', { exact: true }))).toBeVisible()
    await page.getByLabel('Nivel', { exact: true }).selectOption('PRIMARIA')
    await expect(page.getByText('3.º A', { exact: true }).locator('visible=true')).toHaveCount(0)
    await page.getByLabel('Nivel', { exact: true }).selectOption('')
    await expect(visible(page.getByText(/\d+ \/ \d+/))).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Coordinadores' })).toBeVisible()
    await sinDesbordeHorizontal(page)
    await capturar(page, '18-estructura-aulas')
  })

  test('asignaturas: lista, búsqueda y tipos de evaluación', async ({ page }) => {
    await pestana(page, 'Asignaturas')
    await expect(visible(page.getByText('Matemática'))).toBeVisible()
    await page.getByRole('searchbox', { name: /Buscar/ }).fill('ciencia')
    await expect(page.getByText('Matemática').locator('visible=true')).toHaveCount(0)
    await expect(page.getByRole('heading', { name: 'Tipos de evaluación' })).toBeVisible()
    await expect(page.getByText('Práctica')).toBeVisible()
    await capturar(page, '19-estructura-asignaturas')
  })

  test('carga y horarios: cursos del aula y su horario semanal', async ({ page }) => {
    await pestana(page, 'Carga y horarios')
    await expect(page.getByRole('heading', { name: 'Cursos del aula' })).toBeVisible()
    await expect(page.getByText(/h \/ semana/).first()).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Horario semanal' })).toBeVisible()
    await sinDesbordeHorizontal(page)
    await capturar(page, '20-estructura-carga')
  })

  test('colegio: datos, umbrales y escala literal', async ({ page }) => {
    await pestana(page, 'Colegio')
    await expect(page.getByLabel('Nombre del colegio')).toHaveValue('IEP El Señor es mi Pastor')
    await expect(page.getByLabel('AD: hasta')).toHaveValue('20')
    // El umbral alto no puede superar al medio: se avisa antes de enviar (y el servidor también lo rechaza)
    await page.getByLabel('Asistencia para riesgo ALTO (%)').fill('95')
    await page.getByRole('button', { name: 'Guardar datos del colegio' }).click()
    await expect(page.getByText('El umbral de asistencia ALTO debe ser menor que el MEDIO.')).toBeVisible()
    await capturar(page, '21-estructura-colegio')
  })
})

test.describe('armar un aula completa (E2E del plan)', () => {
  // Crea y borra datos: una sola vez (si no, la limpieza del celular borraría lo que arma la PC)
  test.skip(({ isMobile }) => isMobile, 'Solo en PC')
  // Los hooks corren aunque la prueba se salte: en celular no limpian nada
  test.beforeEach(async ({ request, isMobile }) => {
    if (!isMobile) await limpiarPrueba(request)
  })
  test.afterEach(async ({ request, isMobile }) => {
    if (!isMobile) await limpiarPrueba(request)
  })

  test('periodo, bimestre, aula de sección única, 3 cursos y horario sin cruces', async ({ page }) => {
    await conSesion(page, 'admin')
    await page.goto('/admin/estructura')

    // Periodo y bimestre (RN-02)
    await page.getByRole('button', { name: 'Nuevo periodo' }).click()
    let ventana = page.getByRole('dialog', { name: 'Nuevo periodo' })
    await ventana.getByLabel('Año').fill(PRUEBA)
    await ventana.getByLabel('Inicio').fill('2099-03-02')
    await ventana.getByLabel('Fin', { exact: true }).fill('2099-12-18')
    await ventana.getByRole('button', { name: 'Crear periodo' }).click()
    const panel = page.getByRole('region', { name: `Periodo ${PRUEBA}` })
    await expect(panel.getByText('Sin fechas')).toHaveCount(4)

    await panel.getByRole('button', { name: 'Definir' }).first().click()
    ventana = page.getByRole('dialog', { name: `I Bimestre de ${PRUEBA}` })
    await ventana.getByLabel('Inicio de clases').fill('2099-03-02')
    await ventana.getByLabel('Fin de clases').fill('2099-05-08')
    await ventana.getByLabel('Cierre de notas').fill('2099-12-31')
    await ventana.getByRole('button', { name: 'Guardar bimestre' }).click()
    await expect(ventana.getByText(/posterior al fin del periodo/)).toBeVisible()
    await ventana.getByLabel('Cierre de notas').fill('')
    await ventana.getByRole('button', { name: 'Guardar bimestre' }).click()
    // Por defecto: fin de clases + 7 días
    await expect(panel.getByText('15 may 2099')).toBeVisible()

    // Aula de sección única (RN-03)
    await pestana(page, 'Grados y aulas')
    await page.getByLabel('Periodo').selectOption({ label: PRUEBA })
    await page.getByRole('button', { name: 'Nueva aula' }).click()
    ventana = page.getByRole('dialog', { name: 'Nueva aula' })
    await ventana.getByLabel('Grado').selectOption({ label: '1.º secundaria' })
    await ventana.getByLabel('Sección').selectOption('Única')
    await ventana.getByLabel('Capacidad').fill('25')
    await ventana.getByLabel('Ambiente (opcional)').fill('Aula 7')
    await ventana.getByLabel('Docente de aula (tutor)').selectOption({ index: 1 })
    await ventana.getByRole('button', { name: 'Crear aula' }).click()
    await expect(visible(page.getByText('1.º secundaria'))).toBeVisible()
    await expect(visible(page.getByText('0 / 25'))).toBeVisible()

    await page.getByRole('button', { name: 'Nueva aula' }).click()
    ventana = page.getByRole('dialog', { name: 'Nueva aula' })
    await ventana.getByLabel('Grado').selectOption({ label: '1.º secundaria' })
    await ventana.getByLabel('Sección').selectOption('A')
    await ventana.getByRole('button', { name: 'Crear aula' }).click()
    await expect(ventana.getByText('Este grado tiene sección Única: no puede tener otras secciones (RN-03).')).toBeVisible()
    await ventana.getByRole('button', { name: 'Cancelar' }).click()

    // Tres cursos y su horario (RN-05)
    await page.getByRole('button', { name: /Carga y horario de 1\.º secundaria/ }).first().click()
    await expect(page.getByRole('tab', { name: 'Carga y horarios' })).toHaveAttribute('aria-selected', 'true')
    for (const [asignatura, docente] of [
      ['Matemática', 1],
      ['Comunicación', 2],
      ['Inglés', 3],
    ] as const) {
      await page.getByRole('button', { name: 'Asignar curso' }).click()
      ventana = page.getByRole('dialog', { name: 'Asignar curso' })
      await elegir(ventana.getByLabel('Asignatura'), asignatura)
      await ventana.getByLabel('Docente').selectOption({ index: docente })
      await ventana.getByRole('button', { name: 'Asignar', exact: true }).click()
      await expect(visible(page.getByText(asignatura))).toBeVisible()
    }

    const agregarClase = async (curso: string, inicio: string, fin: string) => {
      await page.getByRole('button', { name: 'Agregar clase' }).click()
      const v = page.getByRole('dialog', { name: 'Agregar clase' })
      await elegir(v.getByLabel('Curso'), curso)
      await v.getByLabel('Día').selectOption('1')
      await v.getByLabel('Hora de inicio').fill(inicio)
      await v.getByLabel('Hora de fin').fill(fin)
      await v.getByRole('button', { name: 'Guardar clase' }).click()
      return v
    }
    await agregarClase('Matemática', '08:00', '09:30')
    await expect(page.getByRole('button', { name: /Matemática, Lunes de 08:00 a 09:30/ })).toBeVisible()

    // Otra clase en la misma aula a la misma hora: cruce
    const v = await agregarClase('Comunicación', '08:30', '10:00')
    await expect(v.getByText('Cruce de horario: 1.º secundaria ya tiene Matemática el lunes de 08:00 a 09:30.')).toBeVisible()
    await v.getByLabel('Hora de inicio').fill('09:30')
    await v.getByLabel('Hora de fin').fill('11:00')
    await v.getByRole('button', { name: 'Guardar clase' }).click()
    await expect(page.getByRole('button', { name: /Comunicación, Lunes de 09:30 a 11:00/ })).toBeVisible()
    await capturar(page, '22-estructura-horario-armado')
  })
})

test.describe('permisos', () => {
  test('un docente no entra a la estructura académica ni a su API', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto('/admin/estructura')
    await expect(page.getByRole('heading', { name: 'No tienes permiso para ver esta página' })).toBeVisible()
    const r = await page.request.get('/api/v1/admin/periodos', { headers: { Authorization: `Bearer ${tokenDe('docente')}` } })
    expect(r.status()).toBe(403)
  })
})
