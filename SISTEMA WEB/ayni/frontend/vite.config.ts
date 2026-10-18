import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { fileURLToPath, URL } from 'node:url'
import type { Plugin } from 'vite'
import { defineConfig } from 'vitest/config'

// En el hosting se compila con AYNI_BASE=/grupo02/
const base = process.env.AYNI_BASE ?? '/'
const api = process.env.AYNI_API_DESTINO ?? 'http://localhost:8000'

/**
 * Precarga la imagen de la portada solo cuando se abre la landing: el navegador la
 * pide en paralelo con el JavaScript en vez de esperar a que React la dibuje.
 */
function precargarPortada(): Plugin {
  return {
    name: 'precargar-portada',
    apply: 'build',
    transformIndexHtml: {
      order: 'post',
      handler(_html, ctx) {
        const imagenes = Object.values(ctx.bundle ?? {})
          .filter((a) => a.type === 'asset' && /colinas-\d+-[\w-]+\.webp$/.test(a.fileName))
          .map((a) => `${base}${a.fileName} ${a.fileName.match(/colinas-(\d+)/)![1]}w`)
        if (!imagenes.length) return []
        const codigo = `if(location.pathname===${JSON.stringify(base)}){var l=document.createElement('link');l.rel='preload';l.as='image';l.fetchPriority='high';l.imageSizes='100vw';l.imageSrcset=${JSON.stringify(imagenes.join(', '))};document.head.appendChild(l)}`
        return [{ tag: 'script', children: codigo, injectTo: 'head-prepend' }]
      },
    },
  }
}

export default defineConfig({
  base,
  plugins: [react(), tailwindcss(), precargarPortada()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    host: true,
    port: 5173,
    proxy: { '/api': { target: api, changeOrigin: true } },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/pruebas/setup.ts'],
    testTimeout: 30_000,
    // Docker en Windows tiene poca RAM: con un proceso jsdom por núcleo se queda sin memoria y todo se vuelve lento
    maxWorkers: 3,
    css: false,
    coverage: {
      provider: 'v8',
      include: ['src/**/*.{ts,tsx}'],
      exclude: ['src/pruebas/**', 'src/main.tsx'],
    },
  },
})
