import { expect, test, type APIRequestContext, type Page } from '@playwright/test'
import { conSesion, sinDesbordeHorizontal, tokenDe } from './cuentas'
import { COLUMNAS, crearXlsx } from './excel'

// M7 - CU-09 a CU-12: RF-27 a RF-32
// No hay forma de borrar estudiantes: la importación crea uno nuevo en cada corrida (DNI distinto),
// anula la matrícula que se le hace (los cupos no cambian) y lo deja retirado. La opción 14 del panel
// vuelve a los datos de demostración.

const CAPTURAS = process.env.AYNI_CAPTURAS

async function capturar(page: Page, nombre: string) {
  if (!CAPTURAS) return
  await expect(page.getByRole('button', { name: 'Cerrar aviso' })).toHaveCount(0, { timeout: 8000 })
  await page.evaluate(() => window.scrollTo(0, 0))
  await page.evaluate(() => document.fonts.ready)
  await page.screenshot({ path: `${CAPTURAS}/${test.info().project.name}/${nombre}.png`, fullPage: true, animations: 'disabled' })
}

/** La tabla trae su versión de PC y de celular: se usa la que está a la vista */
const visible = (l: ReturnType<Page['getByText']>) => l.locator('visible=true').first()

// La pestaña cambia la dirección dentro de una transición: se espera a que quede elegida
async function pestana(page: Page, nombre: string) {
  const tab = page.getByRole('tab', { name: nombre })
  await tab.click()
  await expect(tab).toHaveAttribute('aria-selected', 'true')
}

async function elegir(lista: ReturnType<Page['getByLabel']>, texto: string) {
  const opcion = lista.locator('option').filter({ hasText: texto }).first()
  await expect(opcion).toBeAttached()
  await lista.selectOption((await opcion.getAttribute('value')) ?? '')
}

test.describe('docentes', () => {
  test.beforeEach(async ({ page }) => {
    await conSesion(page, 'admin')
    await page.goto('/admin/docentes')
  })

  test('lista con conteos por estado, búsqueda y ficha con su carga', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Docentes', level: 1 })).toBeVisible()
    await expect(page.getByRole('option', { name: /^Activos \(\d+\)$/ })).toBeAttached()
    await expect(visible(page.getByText('Luis Paredes Aranda'))).toBeVisible()
    await sinDesbordeHorizontal(page)
    await capturar(page, '23-docentes')

    await page.getByLabel('Buscar').fill('41234567')
    await expect(page.getByText('Raúl Benites Soto').locator('visible=true')).toHaveCount(0)
    await page.getByRole('button', { name: 'Abrir a Luis Paredes Aranda' }).locator('visible=true').click()
    await expect(page.getByText('Cursos a cargo (periodo en curso)')).toBeVisible()
    await expect(page.getByText(/^Matemática · /).first()).toBeVisible()
    await expect(page.getByText(/· usuario 41234567$/)).toBeVisible()
    await capturar(page, '24-docente-ficha')
  })

  test('nuevo docente: valida en la página y el servidor rechaza un DNI ya registrado', async ({ page }) => {
    await page.getByRole('button', { name: 'Nuevo docente' }).click()
    const registrar = page.getByRole('button', { name: 'Registrar docente' })
    await registrar.click()
    await expect(page.getByText('Ingresa el número de documento.')).toBeVisible()

    await page.getByLabel('DNI', { exact: true }).fill('41234567')
    await page.getByLabel('Nombres').fill('Luis')
    await page.getByLabel('Apellidos').fill('Paredes')
    await page.getByLabel('Fecha de nacimiento (opcional)').fill('2015-01-01')
    await registrar.click()
    await expect(page.getByText(/el personal tiene de 18 a 75 años/)).toBeVisible()

    await page.getByLabel('Fecha de nacimiento (opcional)').fill('')
    await registrar.click()
    await expect(page.getByText('Ya hay una persona registrada con ese documento.')).toBeVisible()
  })
})

test.describe('docentes (cambios)', () => {
  test.skip(({ isMobile }) => isMobile, 'Solo en PC')
  const DNI = '47417956' // Silvia Navarro Ponce

  /** Deja a la docente como estaba aunque la prueba falle a la mitad */
  async function reponer(request: APIRequestContext) {
    const headers = { Authorization: `Bearer ${tokenDe('admin')}` }
    const { datos } = await (await request.get(`/api/v1/admin/docentes?buscar=${DNI}&estado=`, { headers })).json()
    if (!datos?.length) return
    const { docente: d } = await (await request.get(`/api/v1/admin/docentes/${datos[0].id}`, { headers })).json()
    if (d.estado === 'ACTIVO') return
    const campos = ['tipo_documento', 'numero_documento', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo', 'correo', 'celular', 'direccion', 'titulo', 'especialidad', 'fecha_inicio_contrato', 'fecha_fin_contrato']
    await request.put(`/api/v1/admin/docentes/${d.id}`, { headers, data: { ...Object.fromEntries(campos.map((c) => [c, d[c]])), estado: 'ACTIVO' } })
  }
  test.afterEach(async ({ request, isMobile }) => {
    if (!isMobile) await reponer(request)
  })

  test('pasa a una docente a licencia y la vuelve a activar', async ({ page }) => {
    await conSesion(page, 'admin')
    await page.goto('/admin/docentes')
    await page.getByLabel('Buscar').fill(DNI)
    await page.getByRole('button', { name: /^Abrir a Silvia Navarro/ }).locator('visible=true').click()

    for (const [estado, insignia] of [
      ['LICENCIA', 'Licencia'],
      ['ACTIVO', 'Activo'],
    ]) {
      await page.getByRole('button', { name: 'Editar datos' }).click()
      await page.getByLabel('Estado', { exact: true }).last().selectOption(estado)
      await page.getByRole('button', { name: 'Guardar cambios' }).click()
      await expect(page.getByText('Datos del docente guardados.')).toBeVisible()
      await expect(page.getByLabel(/^Docente Silvia/).getByText(insignia, { exact: true })).toBeVisible()
    }
  })
})

test.describe('estudiantes (consulta)', () => {
  test.beforeEach(async ({ page }) => {
    await conSesion(page, 'admin')
    await page.goto('/admin/estudiantes')
  })

  test('lista, filtro "sin matrícula" y ficha con matrículas y apoderados', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Estudiantes', level: 1 })).toBeVisible()
    await expect(page.getByText(/^\d+ estudiantes registrados en total\.$/)).toBeVisible()
    await sinDesbordeHorizontal(page)
    await capturar(page, '25-estudiantes')

    await page.getByLabel('Aula').selectOption('sin')
    await expect(visible(page.getByText('Sin matrícula').or(page.getByText('No hay estudiantes con esos filtros.')))).toBeVisible()
    await page.getByLabel('Aula').selectOption('')

    await page.getByLabel('Buscar').fill('E-2026-001')
    await page.getByRole('button', { name: /^Ver ficha de Valeria Quispe/ }).locator('visible=true').click()
    await expect(page.getByRole('heading', { name: 'Matrículas' })).toBeVisible()
    await expect(page.getByText(/^2026 · /)).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Apoderados' })).toBeVisible()
    await capturar(page, '26-estudiante-ficha')
  })

  test('importar: columnas de la plantilla y su descarga', async ({ page }) => {
    await pestana(page, 'Importar Excel')
    await expect(visible(page.getByText('numero_documento'))).toBeVisible()
    await expect(visible(page.getByText('codigo', { exact: true }))).toBeVisible()
    const descarga = page.waitForEvent('download')
    await page.getByRole('button', { name: 'Descargar plantilla' }).click()
    expect((await descarga).suggestedFilename()).toBe('plantilla_estudiantes.xlsx')
    await sinDesbordeHorizontal(page)
    await capturar(page, '27-estudiantes-importar')
  })

  test('matrículas: cupos por aula y lista del periodo', async ({ page }) => {
    await pestana(page, 'Matrículas')
    const cupos = page.getByRole('list', { name: 'Cupos por aula' })
    await expect(cupos.getByText(/^\d+ \/ \d+/).first()).toBeVisible()
    await expect(visible(page.getByText('Activa', { exact: true }))).toBeVisible()
    await sinDesbordeHorizontal(page)
    await capturar(page, '28-estudiantes-matriculas')
  })
})

test.describe('importar y matricular (E2E del plan)', () => {
  test.skip(({ isMobile }) => isMobile, 'Solo en PC')

  test('importa un Excel con una fila errónea, matricula al nuevo estudiante y anula la matrícula', async ({ page }) => {
    const dni = `7${String(Date.now()).slice(-7)}`
    const excel = crearXlsx([
      COLUMNAS,
      ['DNI', dni, 'Importado', 'Prueba Excel', '14/05/2013', 'F', 'SECUNDARIA', '1', 'A', '', '', '', ''],
      ['DNI', '123', 'Fila', 'Con Errores', '31/02/2013', 'X', 'SECUNDARIA', '1', 'A', '', '', '', ''],
    ])

    await conSesion(page, 'admin')
    await page.goto('/admin/estudiantes?pestana=importar')
    await page.getByLabel(/Matricular en el periodo en curso/).uncheck()
    await page.getByLabel('Archivo de Excel').setInputFiles({
      name: 'importacion_e2e.xlsx',
      mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      buffer: excel,
    })

    // Paso 3: revisión
    await expect(page.getByText('Fila 3')).toBeVisible()
    await expect(page.getByText('El DNI debe tener 8 dígitos.')).toBeVisible()
    await expect(page.getByText('La fecha «31/02/2013» no es válida (usa DD/MM/AAAA).')).toBeVisible()
    await expect(page.getByText('El sexo es M o F.')).toBeVisible()
    const descarga = page.waitForEvent('download')
    await page.getByRole('button', { name: 'Descargar filas con error' }).click()
    expect((await descarga).suggestedFilename()).toBe('filas_con_error.xlsx')
    await capturar(page, '29-importacion-revisada')

    await page.getByRole('button', { name: 'Importar 1 estudiante' }).click()
    const resultado = page.getByLabel('Resultado de la importación')
    await expect(resultado.getByText('Se importó 1 estudiante', { exact: true })).toBeVisible()
    await expect(resultado.getByText('1 fila tenía errores y no se importó.')).toBeVisible()
    const codigo = (await resultado.textContent())?.match(/E-\d{4}-\d{3}/)?.[0] ?? ''
    expect(codigo).not.toBe('')
    await capturar(page, '30-importacion-resultado')

    // Matrícula (RN-04) y anulación: los cupos quedan como estaban
    await pestana(page, 'Matrículas')
    await page.getByRole('button', { name: 'Nueva matrícula' }).click()
    const v = page.getByRole('dialog', { name: 'Nueva matrícula' })
    await v.getByLabel('Buscar').fill(dni)
    await elegir(v.getByLabel('Estudiante sin matrícula en el periodo'), codigo)
    await elegir(v.getByLabel('Aula'), '1.º A')
    await v.getByRole('button', { name: 'Matricular' }).click()
    await expect(page.getByText('Matrícula registrada.')).toBeVisible()

    await page.getByLabel('Buscar').fill(codigo)
    await page.getByRole('button', { name: /^Anular la matrícula de Importado Prueba/ }).locator('visible=true').click()
    const anular = page.getByRole('dialog', { name: 'Anular matrícula' })
    await anular.getByLabel('Motivo').fill('corto')
    await anular.getByRole('button', { name: 'Anular matrícula' }).click()
    await expect(anular.getByText('Explica el motivo en 10 a 300 caracteres.')).toBeVisible()
    await anular.getByLabel('Motivo').fill('Matrícula de prueba registrada por error')
    await anular.getByRole('button', { name: 'Anular matrícula' }).click()
    await expect(page.getByText('Matrícula anulada.')).toBeVisible()
    await page.getByLabel('Estado').selectOption('')
    await expect(visible(page.getByText('Anulada', { exact: true }))).toBeVisible()

    // Queda retirado (su cuenta se desactiva) para no mezclarse con los activos
    await pestana(page, 'Lista')
    await page.getByLabel('Buscar').fill(dni)
    await page.getByRole('button', { name: /^Ver ficha de Importado Prueba/ }).locator('visible=true').click()
    await expect(page.getByText('Registrado por')).toBeVisible()
    await expect(page.getByText('Importación Excel')).toBeVisible()
    await page.getByRole('button', { name: 'Editar datos' }).click()
    await page.getByLabel('Estado', { exact: true }).last().selectOption('RETIRADO')
    await page.getByRole('button', { name: 'Guardar cambios' }).click()
    await expect(page.getByText('Datos del estudiante guardados.')).toBeVisible()
  })
})

test.describe('permisos', () => {
  test('un docente no entra a estudiantes ni a su API', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto('/admin/estudiantes')
    await expect(page.getByRole('heading', { name: 'No tienes permiso para ver esta página' })).toBeVisible()
    const r = await page.request.get('/api/v1/admin/estudiantes', { headers: { Authorization: `Bearer ${tokenDe('docente')}` } })
    expect(r.status()).toBe(403)
  })
})
