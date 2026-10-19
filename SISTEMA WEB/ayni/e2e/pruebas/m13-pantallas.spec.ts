import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'
import { conSesion, sinDesbordeHorizontal, type RolE2E } from './cuentas'

// M13 - accesibilidad y celular: TODAS las pantallas del menú de cada rol, en PC y celular.
// Revisa título, desborde horizontal, errores de JavaScript o de la política de contenido (CSP) y axe (WCAG 2 A y AA).

const ROLES: { rol: RolE2E; base: string }[] = [
  { rol: 'admin', base: '/admin' },
  { rol: 'docente', base: '/docente' },
  { rol: 'estudiante', base: '/estudiante' },
]

/** Problemas de axe graves o críticos, con dónde están */
async function problemasDeAccesibilidad(page: Page) {
  const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze()
  return violations
    .filter((v) => v.impact === 'serious' || v.impact === 'critical')
    .map((v) => `${v.id} (${v.impact}): ${v.help} → ${v.nodes.slice(0, 3).map((n) => n.target.join(' ')).join(' | ')}`)
}

for (const { rol, base } of ROLES) {
  test(`todas las pantallas de ${base} se ven bien, sin errores y accesibles`, async ({ page }) => {
    test.setTimeout(180_000)
    const errores: string[] = []
    page.on('pageerror', (e) => errores.push(`${page.url()}: ${e.message}`))
    page.on('console', (m) => {
      if (m.type() === 'error' && /Content Security Policy|Refused to/i.test(m.text())) errores.push(`${page.url()}: ${m.text()}`)
    })

    await conSesion(page, rol)
    await page.goto(base)
    await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible()
    const rutas = await page.locator('#menu-lateral nav a').evaluateAll((enlaces) => enlaces.map((a) => new URL((a as HTMLAnchorElement).href).pathname))
    expect(rutas.length).toBeGreaterThan(4)

    const problemas: string[] = []
    for (const ruta of [...new Set([...rutas, `${base}/perfil`, `${base}/notificaciones`])]) {
      await page.goto(ruta)
      await expect(page.getByRole('heading', { level: 1 }).first(), ruta).toBeVisible()
      await page.waitForLoadState('networkidle')
      await sinDesbordeHorizontal(page)
      problemas.push(...(await problemasDeAccesibilidad(page)).map((p) => `${ruta}: ${p}`))
    }

    // Se revisan juntos para ver en una sola corrida todo lo que falla
    const todo = [...errores, ...problemas]
    expect(todo, todo.join('\n')).toEqual([])
  })
}

test.describe('con el teclado', () => {
  test.skip(({ isMobile }) => isMobile, 'Prueba de teclado')

  test('el menú se recorre con Tab y cada enlace muestra dónde está el foco', async ({ page }) => {
    await conSesion(page, 'docente')
    await page.goto('/docente')
    await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible()

    await page.keyboard.press('Tab')
    await expect(page.getByRole('link', { name: 'Saltar al contenido' })).toBeFocused()
    const enlaces = await page.locator('#menu-lateral nav a').count()
    let conFoco = 0
    for (let i = 0; i < enlaces + 10 && conFoco < enlaces; i++) {
      await page.keyboard.press('Tab')
      const activo = await page.evaluate(() => {
        const el = document.activeElement as HTMLElement | null
        if (!el?.closest('#menu-lateral nav')) return null
        const estilo = getComputedStyle(el)
        return estilo.outlineStyle !== 'none' || estilo.boxShadow !== 'none'
      })
      if (activo === null) continue
      expect(activo, 'El enlace con foco debe tener un contorno visible').toBe(true)
      conFoco++
    }
    expect(conFoco).toBe(enlaces)
  })

  test('Enter en "Saltar al contenido" lleva el foco al contenido principal', async ({ page }) => {
    await conSesion(page, 'estudiante')
    await page.goto('/estudiante')
    await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible()
    await page.keyboard.press('Tab')
    await page.keyboard.press('Enter')
    await expect(page).toHaveURL(/#contenido$/)
    await expect(page.locator('#contenido')).toBeFocused()
  })
})
