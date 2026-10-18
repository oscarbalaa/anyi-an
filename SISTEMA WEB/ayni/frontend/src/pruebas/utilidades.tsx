import { render } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { vi } from 'vitest'
import { ProveedorAvisos } from '@/components/ui/avisos'
import type { Rol } from '@/layout/roles'
import { token, type Usuario } from '@/lib/sesion'
import { ProveedorSesion } from '@/lib/sesion-proveedor'
import { rutas } from '@/rutas'

export function renderizarEn(ruta: string) {
  const router = createMemoryRouter(rutas, { initialEntries: [ruta] })

  return {
    router,
    ...render(
      <ProveedorAvisos>
        <ProveedorSesion>
          <RouterProvider router={router} />
        </ProveedorSesion>
      </ProveedorAvisos>,
    ),
  }
}

const json = (estado: number, cuerpo: unknown) =>
  new Response(JSON.stringify(cuerpo), { status: estado, headers: { 'Content-Type': 'application/json' } })

export function simularFetch(estado: number, cuerpo: unknown) {
  const fetch = vi.fn().mockImplementation(async () => json(estado, cuerpo))
  vi.stubGlobal('fetch', fetch)
  return fetch
}

type Respuesta = [number, unknown] | ((cuerpo: Record<string, unknown>) => [number, unknown])

/** Responde según "MÉTODO /ruta" o "/ruta" (con o sin ?parámetros); lo no declarado devuelve 404 */
export function simularApi(respuestas: Record<string, Respuesta>) {
  const fetch = vi.fn().mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.replace(/^.*\/api\/v1/, '')
    const metodo = opciones.method ?? 'GET'
    const sinConsulta = ruta.split('?')[0]
    const r =
      respuestas[`${metodo} ${ruta}`] ??
      respuestas[ruta] ??
      respuestas[`${metodo} ${sinConsulta}`] ??
      respuestas[sinConsulta]
    if (!r) return json(404, { mensaje: 'No encontrado' })
    const [estado, cuerpo] = typeof r === 'function' ? r(JSON.parse((opciones.body as string) ?? '{}')) : r
    return json(estado, cuerpo)
  })
  vi.stubGlobal('fetch', fetch)
  return fetch
}

/** Cuerpo enviado en la llamada a una ruta de la API */
export function cuerpoEnviado(fetch: ReturnType<typeof vi.fn>, ruta: string) {
  const llamada = fetch.mock.calls.find(([url, opciones]) => String(url).endsWith(ruta) && opciones?.body)
  return llamada ? JSON.parse(llamada[1].body) : undefined
}

const ROL_API = { estudiante: 'ESTUDIANTE', docente: 'DOCENTE', admin: 'ADMIN' } as const

export function usuarioDe(acceso: Rol, extra: Partial<Usuario> = {}): Usuario {
  return {
    id: 1,
    usuario: { estudiante: 'E-2026-001', docente: '41234567', admin: 'cmedina' }[acceso],
    rol: ROL_API[acceso],
    acceso,
    nombre: 'Ana Torres',
    nombre_completo: 'Ana María Torres Vega',
    iniciales: 'AT',
    detalle: { estudiante: '3.º B', docente: 'Coordinadora 3.º A', admin: 'Directora' }[acceso],
    correo: null,
    debe_cambiar_contrasena: false,
    ultimo_acceso: null,
    ...extra,
  }
}

/** Deja un token guardado, como si el usuario ya hubiera ingresado */
export function conSesion(acceso: Rol, extra: Partial<Usuario> = {}) {
  token.guardar('token-de-prueba')
  return usuarioDe(acceso, extra)
}

export const CAPTCHA = { id: '9b1c0f9e-0000-4000-8000-000000000001', imagen: 'data:image/png;base64,iVBORw0KGgo=' }
