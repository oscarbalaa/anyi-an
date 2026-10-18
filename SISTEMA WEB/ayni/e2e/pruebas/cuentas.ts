import { readFileSync } from 'node:fs'
import { expect, type Page } from '@playwright/test'

// Cuentas del seeder de demostración (1. INICIO/CREDENCIALES.txt)
export const CUENTAS = {
  admin: { usuario: 'cmedina', clave: 'Ayni#2026', nombre: 'Carlos Medina', base: '/admin', ingreso: '/ingresar/administracion', campo: 'Usuario' },
  docente: { usuario: '41234567', clave: 'Ayni#2026', nombre: 'Luis Paredes', base: '/docente', ingreso: '/ingresar/docente', campo: 'DNI' },
  estudiante: { usuario: 'E-2026-001', clave: 'piki2026', nombre: 'Valeria Quispe', base: '/estudiante', ingreso: '/ingresar/estudiante', campo: 'Código de estudiante' },
} as const

export type RolE2E = keyof typeof CUENTAS

export const ARCHIVO_SESIONES = new URL('../.sesiones.json', import.meta.url)

/** Token de la sesión del rol creada en el calentamiento (para llamar a la API) */
export const tokenDe = (rol: RolE2E) => JSON.parse(readFileSync(ARCHIVO_SESIONES, 'utf8'))[rol] as string

/** Abre la página ya con la sesión del rol (token creado en el calentamiento) */
export async function conSesion(page: Page, rol: RolE2E) {
  const token = tokenDe(rol)
  await page.addInitScript((t) => {
    localStorage.setItem('ayni.token', t)
    localStorage.setItem('ayni.actividad', String(Date.now()))
  }, token)
}

/** Llena el formulario de ingreso. Con el secreto E2E el servidor no revisa el captcha. */
export async function llenarIngreso(page: Page, rol: RolE2E, clave: string = CUENTAS[rol].clave, usuario: string = CUENTAS[rol].usuario) {
  await expect(page.getByTestId('captcha-imagen')).toBeVisible()
  await page.getByLabel(CUENTAS[rol].campo).fill(usuario)
  await page.getByLabel('Contraseña', { exact: true }).fill(clave)
  await page.getByLabel('Código de verificación').fill('AAAAA')
  await page.getByRole('button', { name: 'Ingresar' }).click()
}

export async function sinDesbordeHorizontal(page: Page) {
  const desborde = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
  expect(desborde).toBeLessThanOrEqual(0)
}
