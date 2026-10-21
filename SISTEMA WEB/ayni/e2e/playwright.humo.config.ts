import { defineConfig, devices } from '@playwright/test'

// M14 - prueba de humo contra el hosting (o su simulación en la CI): SOLO LECTURA.
// AYNI_URL termina en "/" (por ejemplo https://…/grupo02/) y las pruebas usan rutas relativas.
const urlBase = (process.env.AYNI_URL ?? 'http://localhost:5173/').replace(/\/?$/, '/')
const secretoE2E = process.env.AYNI_SECRETO_E2E ?? ''

export default defineConfig({
  testDir: './humo',
  timeout: 90_000,
  expect: { timeout: 15_000 },
  retries: 1,
  workers: 1,
  reporter: [['list'], ['junit', { outputFile: '/reportes/humo.xml' }]],
  outputDir: './test-results/humo',
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
