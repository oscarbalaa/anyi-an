import { expect, test, type Page } from '@playwright/test'
import { conSesion, type RolE2E } from './cuentas'

// M3 - RF-01, RF-02, RNF-03, RNF-07, RNF-13: diseño, landing, selector, pantallas de ingreso y layouts

const CAPTURAS = process.env.AYNI_CAPTURAS

/** Captura de pantalla completa para el manual (solo cuando se pide la carpeta) */
async function capturar(page: Page, nombre: string) {
  if (!CAPTURAS) return
  await page.evaluate(() => document.fonts.ready)
  // La captura de página completa redibuja: se espera a que todas las imágenes estén listas
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

async function sinDesbordeHorizontal(page: Page) {
  const desborde = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
  expect(desborde).toBeLessThanOrEqual(0)
}

async function mostrarTodo(page: Page) {
  // Las secciones aparecen al hacer scroll: se recorre la página para que todas se vean
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 400) {
      window.scrollTo(0, y)
      await new Promise((r) => setTimeout(r, 60))
    }
    window.scrollTo(0, 0)
  })
}

test.describe('landing', () => {
  test('muestra la portada y las cuatro secciones', async ({ page }) => {
    await page.goto('/')

    await expect(page.getByRole('heading', { level: 1 })).toHaveText(/Señales\s+claras\s*para acompañar mejor/)
    for (const seccion of ['¿Qué es Ayni?', 'Descubre Ayni', 'Piki y Tambo', 'Contacto']) {
      await expect(page.getByRole('heading', { level: 2, name: seccion })).toBeAttached()
    }
    await expect(page.getByText('IEP El Señor es mi Pastor')).toBeAttached()
    await sinDesbordeHorizontal(page)
    await mostrarTodo(page)
    await capturar(page, '01-landing')
  })

  test('los enlaces del menú llevan a cada sección', async ({ page, isMobile }) => {
    test.skip(isMobile, 'En celular el menú de secciones se oculta')
    await page.goto('/')

    await page.getByRole('link', { name: 'Piki y Tambo' }).click()
    await expect(page).toHaveURL(/#piki-y-tambo$/)
    await expect(page.getByRole('heading', { name: 'Piki y Tambo' })).toBeInViewport()
  })

  test('Ingresar lleva al selector y Administración al acceso administrativo', async ({ page }) => {
    await page.goto('/')
    await page.getByRole('link', { name: 'Ingresar', exact: true }).first().click()
    await expect(page).toHaveURL(/\/ingresar$/)

    await page.goto('/')
    await page.getByRole('link', { name: /Administración|Acceso administrativo/ }).click()
    await expect(page.getByRole('heading', { name: 'Acceso administrativo' })).toBeVisible()
  })
})

test.describe('selector de ingreso', () => {
  test('ofrece ingresar como estudiante o como docente', async ({ page }) => {
    await page.goto('/ingresar')

    await expect(page.getByRole('heading', { name: '¿Cómo deseas ingresar?' })).toBeVisible()
    await expect(page.getByRole('img', { name: /Piki/ })).toBeVisible()
    await expect(page.getByRole('img', { name: /Tambo/ })).toBeVisible()
    await sinDesbordeHorizontal(page)
    await capturar(page, '02-selector-ingreso')

    await page.getByRole('link', { name: 'Ingresar como docente' }).click()
    await expect(page.getByText('Ingresa a tu espacio docente')).toBeVisible()
  })
})

const ACCESOS = [
  { ruta: '/ingresar/estudiante', titulo: 'Bienvenido de nuevo', campo: 'Código de estudiante', valido: 'E-2026-001', captura: '03-ingreso-estudiante' },
  { ruta: '/ingresar/docente', titulo: 'Bienvenido de nuevo', campo: 'DNI', valido: '41234567', captura: '04-ingreso-docente' },
  { ruta: '/ingresar/administracion', titulo: 'Acceso administrativo', campo: 'Usuario', valido: 'cmedina', captura: '05-ingreso-administracion' },
]

test.describe('pantallas de ingreso', () => {
  for (const a of ACCESOS) {
    test(`${a.ruta} valida los campos antes de enviar`, async ({ page }) => {
      await page.goto(a.ruta)
      await expect(page.getByRole('heading', { name: a.titulo })).toBeVisible()
      await sinDesbordeHorizontal(page)
      await capturar(page, a.captura)

      await page.getByRole('button', { name: 'Ingresar' }).click()
      await expect(page.getByRole('alert').first()).toBeVisible()
      await expect(page.getByLabel(a.campo)).toHaveAttribute('aria-invalid', 'true')

      await page.getByLabel(a.campo).fill(a.valido)
      await page.getByLabel('Contraseña', { exact: true }).fill('Ayni#2026')
      await page.getByRole('button', { name: 'Ingresar' }).click()
      await expect(page.getByText('Escribe el código de la imagen.')).toBeVisible()
    })
  }

  test('el DNI no acepta letras y el código se escribe en mayúsculas', async ({ page }) => {
    await page.goto('/ingresar/docente')
    await page.getByLabel('DNI').pressSequentially('41a2b345678')
    await expect(page.getByLabel('DNI')).toHaveValue('41234567')

    await page.goto('/ingresar/estudiante')
    await page.getByLabel('Código de estudiante').pressSequentially('e-2026-001')
    await expect(page.getByLabel('Código de estudiante')).toHaveValue('E-2026-001')
  })

  test('un código con formato inválido muestra el mensaje en español', async ({ page }) => {
    await page.goto('/ingresar/estudiante')
    await page.getByLabel('Código de estudiante').fill('E2026')
    await page.getByLabel('Contraseña', { exact: true }).fill('x')
    await page.getByRole('button', { name: 'Ingresar' }).click()
    await expect(page.getByText('El código tiene el formato E-AAAA-NNN')).toBeVisible()
  })

  test('el ojo muestra y oculta la contraseña', async ({ page }) => {
    await page.goto('/ingresar/estudiante')
    const clave = page.getByLabel('Contraseña', { exact: true })
    await clave.fill('secreta')
    await expect(clave).toHaveAttribute('type', 'password')
    await page.getByRole('button', { name: 'Mostrar contraseña' }).click()
    await expect(clave).toHaveAttribute('type', 'text')
  })
})

// "modulo": la pantalla aún muestra en qué módulo llega (null si ya está construida)
const ROLES: { rol: RolE2E; base: string; etiqueta: string; opciones: number; pantalla: string; modulo: string | null; captura: string }[] = [
  { rol: 'admin', base: '/admin', etiqueta: 'Administrativo', opciones: 10, pantalla: 'Reportes', modulo: null, captura: '06-inicio-administracion' },
  { rol: 'docente', base: '/docente', etiqueta: 'Docente', opciones: 9, pantalla: 'Mis estudiantes', modulo: null, captura: '07-inicio-docente' },
  { rol: 'estudiante', base: '/estudiante', etiqueta: 'Estudiante', opciones: 6, pantalla: 'Notificaciones', modulo: null, captura: '08-inicio-estudiante' },
]

const SALUDO = /^(Buenos días|Buenas tardes|Buenas noches|Hola), /

test.describe('layouts por rol', () => {
  for (const r of ROLES) {
    test(`${r.base} tiene su menú, sus colores y el bimestre actual`, async ({ page, isMobile }) => {
      await conSesion(page, r.rol)
      await page.goto(r.base)
      // Desde M12 la portada de cada rol es su inicio; el catálogo del diseño queda en /diseno
      await expect(page.getByRole('heading', { level: 1, name: SALUDO })).toBeVisible()
      await expect(page.getByTestId('periodo-actual')).toContainText(/Periodo \d{4}/)
      await expect(page.getByTestId('periodo-actual')).toContainText(/Bimestre/)
      await sinDesbordeHorizontal(page)
      await capturar(page, r.captura)

      if (isMobile) {
        await expect(page.locator('#menu-lateral')).toBeHidden()
        await page.getByRole('button', { name: 'Abrir menú' }).click()
      }
      const menu = page.locator('#menu-lateral nav')
      await expect(menu.getByRole('link')).toHaveCount(r.opciones)
      if (isMobile) await capturar(page, `${r.captura}-menu`)

      await menu.getByRole('link', { name: r.pantalla }).click()
      await expect(page.getByRole('heading', { level: 1, name: r.pantalla })).toBeVisible()
      if (r.modulo) await expect(page.getByText(r.modulo)).toBeVisible()
      if (isMobile) await expect(page.locator('#menu-lateral')).toBeHidden()
    })
  }

  test('cada rol usa su propio color principal', async ({ page }) => {
    const colores = new Set<string>()
    for (const r of ROLES) {
      await conSesion(page, r.rol)
      await page.goto(r.base)
      await expect(page.getByRole('button', { name: 'Menú de la cuenta' })).toBeVisible()
      colores.add(await page.locator('[data-tema]').first().evaluate((el) => getComputedStyle(el).getPropertyValue('--primario').trim()))
    }
    expect(colores.size).toBe(3)
  })

  test('la tabla busca sin importar tildes y pagina los resultados', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto('/docente/diseno')
    const tabla = page.getByRole('table', { name: 'Estudiantes de ejemplo' }).or(page.getByRole('list', { name: 'Estudiantes de ejemplo' }))
    await expect(page.getByText('Página 1 de 3')).toBeVisible()

    await page.getByRole('searchbox', { name: /Buscar/ }).fill('andres')
    await expect(tabla.locator(':visible', { hasText: 'Mori Lazo, Andrés' }).first()).toBeVisible()
    await expect(page.getByText('Página 1 de 3')).toBeHidden()
  })

  test('el diálogo de confirmación se abre, se cancela y se confirma', async ({ page }) => {
    await conSesion(page, 'admin')
    await page.goto('/admin/diseno')
    await page.getByRole('button', { name: 'Eliminar…' }).click()
    const dialogo = page.getByRole('dialog', { name: '¿Eliminar el registro?' })
    await expect(dialogo).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(dialogo).toBeHidden()

    await page.getByRole('button', { name: 'Eliminar…' }).click()
    await dialogo.getByRole('button', { name: 'Sí, eliminar' }).click()
    await expect(page.getByRole('status').filter({ hasText: 'Registro eliminado' })).toBeVisible()
  })

  test('el formulario de ejemplo no deja escribir letras en el DNI', async ({ page }) => {
    await conSesion(page, 'estudiante')
    await page.goto('/estudiante/diseno')
    await page.getByLabel('DNI').pressSequentially('12ab34')
    await expect(page.getByLabel('DNI')).toHaveValue('1234')
    await page.getByRole('button', { name: 'Validar' }).click()
    await expect(page.getByText('El DNI debe tener 8 dígitos.')).toBeVisible()
  })
})

test.describe('errores y accesibilidad', () => {
  test('muestra 404 en rutas inexistentes, también dentro de un rol', async ({ page }) => {
    await conSesion(page, 'admin')
    for (const ruta of ['/no-existe', '/admin/no-existe']) {
      await page.goto(ruta)
      await expect(page.getByRole('heading', { name: 'Esta página no existe' })).toBeVisible()
    }
  })

  test('tiene una página 403 para accesos no permitidos', async ({ page }) => {
    await page.goto('/sin-permiso')
    await expect(page.getByRole('heading', { name: 'No tienes permiso para ver esta página' })).toBeVisible()
  })

  test('permite saltar al contenido con el teclado', async ({ page, isMobile }) => {
    test.skip(isMobile, 'Prueba de teclado')
    await conSesion(page, 'admin')
    await page.goto('/admin/diseno')
    await expect(page.getByText('Vista previa del diseño · Administrativo')).toBeVisible()
    await page.keyboard.press('Tab')
    await expect(page.getByRole('link', { name: 'Saltar al contenido' })).toBeFocused()
  })

  test('las imágenes informativas tienen texto alternativo', async ({ page }) => {
    for (const ruta of ['/', '/ingresar', '/ingresar/docente']) {
      await page.goto(ruta)
      const sinAlt = await page.locator('img:not([alt])').count()
      expect(sinAlt).toBe(0)
    }
  })
})
