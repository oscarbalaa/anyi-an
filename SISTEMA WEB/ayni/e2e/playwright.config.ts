import { defineConfig, devices } from '@playwright/test'

const urlBase = process.env.AYNI_URL ?? 'http://localhost:5173'
// Con este secreto el servidor acepta el ingreso sin resolver el captcha (solo fuera de producción)
const secretoE2E = process.env.AYNI_SECRETO_E2E ?? ''

export default defineConfig({
  testDir: './pruebas',
  globalSetup: './calentamiento.ts',
  // Docker en Windows es lento: margen amplio para cada prueba
  timeout: 60_000,
  expect: { timeout: 10_000 },
  retries: process.env.CI ? 1 : 0,
  reporter: [
    ['list'],
    ['html', { outputFolder: '/reportes/e2e', open: 'never' }],
    ['junit', { outputFile: '/reportes/e2e.xml' }],
  ],
  outputDir: './test-results',
  use: {
    baseURL: urlBase,
    locale: 'es-PE',
    timezoneId: 'America/Lima',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    extraHTTPHeaders: secretoE2E ? { 'X-Ayni-E2E': secretoE2E } : {},
  },
  projects: [
    { name: 'pc', use: { ...devices['Desktop Chrome'] } },
    { name: 'celular', use: { ...devices['Pixel 7'] } },
  ],
})
