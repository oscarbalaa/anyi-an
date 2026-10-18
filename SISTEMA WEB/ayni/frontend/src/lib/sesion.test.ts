import { afterEach, describe, expect, it, vi } from 'vitest'
import { simularFetch } from '@/pruebas/utilidades'
import { pedir } from './api'
import { EVENTO_EXPIRADA, INACTIVIDAD_MS, recordado, token } from './sesion'

describe('token de sesión', () => {
  afterEach(() => vi.useRealTimers())

  it('se envía como Bearer en cada petición', async () => {
    const fetch = simularFetch(200, {})
    token.guardar('abc')

    await pedir('/auth/yo')

    expect(fetch.mock.calls[0][1].headers.Authorization).toBe('Bearer abc')
  })

  it('un 401 borra el token y avisa a la app', async () => {
    simularFetch(401, { mensaje: 'Tu sesión no es válida o expiró.' })
    const escucha = vi.fn()
    window.addEventListener(EVENTO_EXPIRADA, escucha)
    token.guardar('abc')

    await expect(pedir('/auth/yo')).rejects.toMatchObject({ estado: 401 })

    expect(token.leer()).toBeNull()
    expect(escucha).toHaveBeenCalledOnce()
    window.removeEventListener(EVENTO_EXPIRADA, escucha)
  })

  it('vence tras una hora sin peticiones, como en el servidor (RN-17)', () => {
    vi.useFakeTimers()
    token.guardar('abc')

    expect(token.vencido(Date.now() + INACTIVIDAD_MS - 1000)).toBe(false)
    expect(token.vencido(Date.now() + INACTIVIDAD_MS + 1000)).toBe(true)
  })

  it('cada petición con sesión reinicia la cuenta de inactividad', async () => {
    simularFetch(200, {})
    token.guardar('abc')
    localStorage.setItem('ayni.actividad', '0')

    await pedir('/sesion/verificar')

    expect(token.vencido()).toBe(false)
  })

  it('"Recordarme" guarda el usuario por acceso', () => {
    recordado.guardar('docente', '41234567')
    expect(recordado.leer('docente')).toBe('41234567')
    expect(recordado.leer('admin')).toBe('')
    recordado.guardar('docente', null)
    expect(recordado.leer('docente')).toBe('')
  })
})
