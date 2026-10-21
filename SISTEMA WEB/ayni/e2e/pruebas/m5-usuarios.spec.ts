import { expect, test, type Page } from '@playwright/test'
import { conSesion, CUENTAS, llenarIngreso, sinDesbordeHorizontal } from './cuentas'

// M5 - CU-04 y CU-05: RF-11 a RF-16
// Las pruebas que cambian datos demo los dejan como estaban y corren solo en PC.

const CAPTURAS = process.env.AYNI_CAPTURAS
const CORREOS = process.env.AYNI_CORREOS_URL ?? 'http://localhost:8025'

async function capturar(page: Page, nombre: string) {
  if (!CAPTURAS) return
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

/** Abre la cuenta en el panel (PC) o en la ventana (celular) */
async function abrirCuenta(page: Page, buscar: string, nombre: string) {
  await page.getByLabel('Buscar').fill(buscar)
  const ver = page.getByRole('button', { name: `Ver la cuenta de ${nombre}` })
  await expect(ver.first()).toBeVisible()
  await ver.first().click()
  return page.getByRole('region', { name: /^Cuenta de / }).last()
}

test.describe('Usuarios (administración)', () => {
  test.beforeEach(async ({ page }) => {
    await conSesion(page, 'admin')
  })

  test('lista las cuentas con las bloqueadas primero, busca y filtra', async ({ page }) => {
    await page.goto('/admin/usuarios')
    await expect(page.getByRole('heading', { name: 'Usuarios', level: 1 })).toBeVisible()
    await expect(page.getByText(/\d+ bloqueadas/)).toBeVisible()
    await sinDesbordeHorizontal(page)
    await capturar(page, '12-usuarios')

    await page.getByLabel('Estado').selectOption('BLOQUEADO')
    await expect(page.locator('main').getByText('Bloqueado', { exact: true }).locator('visible=true').first()).toBeVisible()
    await page.getByLabel('Estado').selectOption('')

    // Sin tilde y en mayúsculas encuentra igual
    await page.getByLabel('Buscar').fill('VALERIA QUISPE')
    await expect(page.getByRole('button', { name: /Ver la cuenta de Valeria/ }).first()).toBeVisible()
    await page.getByLabel('Rol').selectOption('DOCENTE')
    await expect(page.getByText('Ningún usuario coincide con los filtros.')).toBeVisible()
  })

  test('valida el formulario de una cuenta nueva sin crearla', async ({ page }) => {
    await page.goto('/admin/usuarios')
    await page.getByRole('button', { name: 'Nueva cuenta administrativa' }).click()
    const ventana = page.getByRole('dialog', { name: 'Nueva cuenta administrativa' })
    await ventana.getByRole('button', { name: 'Crear cuenta' }).click()
    await expect(ventana.getByText('Ingresa los nombres.')).toBeVisible()
    await expect(ventana.getByText('Ingresa el DNI.')).toBeVisible()

    // Los campos no dejan escribir caracteres inválidos
    await ventana.getByLabel('DNI').pressSequentially('45a678b912')
    await expect(ventana.getByLabel('DNI')).toHaveValue('45678912')
    await ventana.getByLabel('Celular (opcional)').pressSequentially('98-765 4321')
    await expect(ventana.getByLabel('Celular (opcional)')).toHaveValue('987654321')

    // El usuario ya existe: lo rechaza el servidor
    await ventana.getByLabel('Nombres').fill('Prueba')
    await ventana.getByLabel('Apellidos').fill('Automática')
    await ventana.getByLabel('Usuario').fill('cmedina')
    await ventana.getByLabel('Cargo').fill('Pruebas')
    await ventana.getByLabel('Correo (opcional)').fill('correo-mal@ayni')
    await ventana.getByRole('button', { name: 'Crear cuenta' }).click()
    await expect(ventana.getByText('El correo debe tener el formato usuario@dominio.com.')).toBeVisible()
    await ventana.getByLabel('Correo (opcional)').fill('')
    await capturar(page, '13-usuarios-nueva')
    await ventana.getByRole('button', { name: 'Crear cuenta' }).click()
    await expect(ventana.getByText('Ese nombre de usuario ya existe.')).toBeVisible()
  })

  test('desbloquea con contraseña temporal; el usuario la cambia y vuelve a entrar (RF-13)', async ({ page, request, isMobile }) => {
    test.skip(isMobile, 'Cambia la cuenta demo de Carmen Rivas: una sola vez')
    const desde = Date.now()
    const carmen = { usuario: '42345678', clave: 'Ayni#2026', correo: 'carmen.rivas@ayni.test' }

    // 5 intentos fallidos bloquean la cuenta (con el secreto E2E no se pide captcha)
    for (let i = 0; i < 5; i++) {
      await request.post('/api/v1/auth/ingresar', { data: { acceso: 'docente', usuario: carmen.usuario, contrasena: 'Mala#0000' } })
    }

    await page.goto('/admin/usuarios')
    const panel = await abrirCuenta(page, carmen.usuario, 'Carmen Rivas Mendoza')
    await expect(panel.getByRole('heading', { name: 'Cuenta bloqueada' })).toBeVisible()
    await capturar(page, '14-usuarios-bloqueada')
    await panel.getByRole('button', { name: 'Desbloquear y generar contraseña temporal' }).click()
    await page.getByRole('dialog', { name: '¿Desbloquear la cuenta?' }).getByRole('button', { name: 'Sí, generar' }).click()

    const resultado = page.getByRole('dialog', { name: 'Contraseña temporal' })
    const clave = (await resultado.getByTestId('clave-temporal').textContent()) ?? ''
    expect(clave).toMatch(/^[A-Z][a-z]{3}[#$%&*]\d{4}$/)
    await expect(resultado.getByText(`También se envió a ${carmen.correo}.`)).toBeVisible()
    await capturar(page, '15-clave-temporal')
    await resultado.getByRole('button', { name: 'Entendido' }).click()

    // El correo con la clave llega a Mailpit
    await expect(async () => {
      const lista = await (await request.get(`${CORREOS}/api/v1/search?query=${encodeURIComponent(`to:${carmen.correo}`)}`)).json()
      const nuevo = lista.messages.find((m: { Created: string; Subject: string }) => Date.parse(m.Created) >= desde - 2000 && m.Subject.includes('temporal'))
      expect(nuevo).toBeTruthy()
      const mensaje = await (await request.get(`${CORREOS}/api/v1/message/${nuevo.ID}`)).json()
      // En el HTML, "&" llega como "&amp;"
      expect(mensaje.HTML.replaceAll('&amp;', '&')).toContain(clave)
    }).toPass({ timeout: 20_000 })

    // Carmen entra con la temporal, la cambia y deja la clave demo de siempre
    const otra = await page.context().browser()!.newContext({ baseURL: test.info().project.use.baseURL, extraHTTPHeaders: test.info().project.use.extraHTTPHeaders })
    const pagina = await otra.newPage()
    await pagina.goto('/ingresar/docente')
    await llenarIngreso(pagina, 'docente', clave, carmen.usuario)
    await expect(pagina.getByRole('heading', { name: 'Crea tu contraseña' })).toBeVisible()
    await pagina.getByLabel('Contraseña temporal').fill(clave)
    await pagina.getByLabel('Nueva contraseña', { exact: true }).fill(carmen.clave)
    await pagina.getByLabel('Repite la nueva contraseña').fill(carmen.clave)
    await pagina.getByRole('button', { name: 'Guardar y continuar' }).click()
    await expect(pagina).toHaveURL(/\/docente$/)
    await otra.close()
  })

  test('desactiva y vuelve a activar una cuenta (RF-12)', async ({ page, isMobile }) => {
    test.skip(isMobile, 'Cambia una cuenta demo: una sola vez')
    await page.goto('/admin/usuarios')
    const panel = await abrirCuenta(page, 'Rosa Huaman', 'Rosa Huamán Flores')
    // Si una corrida anterior la dejó inactiva, primero se activa
    if (await panel.getByRole('button', { name: 'Activar cuenta', exact: true }).isVisible()) {
      await panel.getByRole('button', { name: 'Activar cuenta', exact: true }).click()
      await page.getByRole('button', { name: 'Sí, activar', exact: true }).click()
    }

    await panel.getByRole('button', { name: 'Desactivar cuenta', exact: true }).click()
    await page.getByRole('button', { name: 'Sí, desactivar' }).click()
    await expect(panel.getByRole('heading', { name: 'Cuenta inactiva' })).toBeVisible()
    await expect(page.getByText('Cuenta desactivada. Sus sesiones se cerraron.')).toBeVisible()

    await panel.getByRole('button', { name: 'Activar cuenta', exact: true }).click()
    await page.getByRole('button', { name: 'Sí, activar', exact: true }).click()
    await expect(panel.getByRole('heading', { name: 'Cuenta activa' })).toBeVisible()
    await panel.getByText('Historial de la cuenta').click()
    await expect(panel.getByText('Cuenta desactivada').first()).toBeVisible()
  })

  test('en su propia cuenta no ofrece acciones', async ({ page }) => {
    await page.goto('/admin/usuarios')
    const panel = await abrirCuenta(page, 'cmedina', 'Carlos Medina Rojas')
    await expect(panel.getByText('Es tu cuenta: tus datos y tu contraseña se cambian en Mi perfil.')).toBeVisible()
    await expect(panel.getByRole('button', { name: 'Desactivar cuenta', exact: true })).toHaveCount(0)
  })
})

test.describe('permisos', () => {
  test('un docente no entra a Usuarios ni a su API', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto('/admin/usuarios')
    await expect(page.getByRole('heading', { name: 'No tienes permiso para ver esta página' })).toBeVisible()

    const token = await page.evaluate(() => localStorage.getItem('ayni.token'))
    const r = await page.request.get('/api/v1/admin/usuarios', { headers: { Authorization: `Bearer ${token}` } })
    expect(r.status()).toBe(403)
  })
})

test.describe('Mi perfil', () => {
  for (const rol of ['admin', 'docente', 'estudiante'] as const) {
    test(`${rol}: ve sus datos y el formulario de contraseña`, async ({ page }) => {
      await conSesion(page, rol)
      await page.goto(`${CUENTAS[rol].base}/perfil`)
      await expect(page.getByRole('heading', { name: 'Mi perfil' })).toBeVisible()
      await expect(page.getByRole('heading', { name: CUENTAS[rol].nombre, exact: false })).toBeVisible()
      await expect(page.getByRole('heading', { name: 'Datos personales' })).toBeVisible()
      await expect(page.getByRole('heading', { name: 'Cambiar contraseña' })).toBeVisible()
      if (rol === 'admin') await expect(page.getByRole('heading', { name: 'Permisos principales' })).toBeVisible()
      if (rol === 'estudiante') await expect(page.getByText('E-2026-001')).toBeVisible()
      if (rol === 'docente') await expect(page.getByText('Cursos a cargo')).toBeVisible()
      await sinDesbordeHorizontal(page)
      await capturar(page, `16-perfil-${rol}`)
    })
  }

  test('edita su celular y lo deja como estaba (RF-14)', async ({ page, isMobile }) => {
    test.skip(isMobile, 'Cambia datos demo: una sola vez')
    await conSesion(page, 'estudiante')
    await page.goto('/estudiante/perfil')
    const datos = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Datos personales' }) })
    const original = (await datos.locator('dt:text-is("Celular") + dd').textContent())?.replace(/\D/g, '') ?? ''

    await datos.getByRole('button', { name: 'Editar contacto' }).click()
    await datos.getByLabel('Celular').fill('812345678')
    await datos.getByRole('button', { name: 'Guardar cambios' }).click()
    await expect(datos.getByText('El celular debe tener 9 dígitos y empezar con 9.')).toBeVisible()

    await datos.getByLabel('Celular').fill('912345678')
    await datos.getByRole('button', { name: 'Guardar cambios' }).click()
    await expect(page.getByText('Tus datos se actualizaron.')).toBeVisible()
    await expect(datos.getByText('912 345 678')).toBeVisible()

    await datos.getByRole('button', { name: 'Editar contacto' }).click()
    await datos.getByLabel('Celular').fill(original)
    await datos.getByRole('button', { name: 'Guardar cambios' }).click()
    await expect(datos.getByRole('button', { name: 'Editar contacto' })).toBeVisible()
  })

  test('cambia su contraseña desde el perfil y la repone (RF-15)', async ({ page, isMobile }) => {
    test.skip(isMobile, 'Cambia la clave de Mateo Flores: una sola vez')
    const mateo = { usuario: 'E-2026-002', clave: 'piki2026', nueva: 'piki2027' }
    await page.goto('/ingresar/estudiante')
    await llenarIngreso(page, 'estudiante', mateo.clave, mateo.usuario)
    await expect(page).toHaveURL(/\/estudiante$/)
    await page.getByRole('button', { name: 'Menú de la cuenta' }).click()
    await page.getByRole('menuitem', { name: 'Mi perfil' }).click()
    await expect(page.getByRole('heading', { name: 'Mi perfil' })).toBeVisible()

    const cambiar = async (actual: string, nueva: string) => {
      await page.getByLabel('Contraseña actual').fill(actual)
      await page.getByLabel('Nueva contraseña', { exact: true }).fill(nueva)
      await page.getByLabel('Repite la nueva contraseña').fill(nueva)
      await page.getByRole('button', { name: 'Guardar contraseña' }).click()
      await expect(page.getByText('Tu contraseña se actualizó.')).toBeVisible()
      await expect(page.getByLabel('Contraseña actual')).toHaveValue('')
    }
    await page.getByLabel('Nueva contraseña', { exact: true }).fill('pikipiki')
    await expect(page.getByText('Al menos un número')).toBeVisible()
    await cambiar(mateo.clave, mateo.nueva)
    await page.getByText('Tu contraseña se actualizó.').waitFor({ state: 'detached', timeout: 10_000 }).catch(() => null)
    await cambiar(mateo.nueva, mateo.clave)
  })
})
