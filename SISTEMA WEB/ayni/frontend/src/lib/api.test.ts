import { describe, expect, it, vi } from 'vitest'
import { simularFetch } from '@/pruebas/utilidades'
import { ErrorApi, pedir, URL_API } from './api'

describe('cliente de la API', () => {
  it('usa una ruta relativa a la base de la aplicación', async () => {
    const fetch = simularFetch(200, { estado: 'ok' })

    await pedir('/health')

    expect(URL_API).toBe('/api/v1')
    expect(fetch).toHaveBeenCalledWith(
      '/api/v1/health',
      expect.objectContaining({ headers: expect.objectContaining({ Accept: 'application/json' }) }),
    )
  })

  it('devuelve el cuerpo JSON cuando la respuesta es correcta', async () => {
    simularFetch(200, { estado: 'ok', version: '0.1.0' })

    await expect(pedir('/health')).resolves.toEqual({ estado: 'ok', version: '0.1.0' })
  })

  it('lanza ErrorApi con el mensaje y los errores por campo de la API', async () => {
    simularFetch(422, {
      mensaje: 'Los datos enviados no son válidos.',
      errores: { correo: ['El correo es obligatorio.'] },
    })

    const error = await pedir<never>('/usuarios', { method: 'POST', body: '{}' }).catch((e: ErrorApi) => e)

    expect(error).toBeInstanceOf(ErrorApi)
    expect(error.estado).toBe(422)
    expect(error.message).toBe('Los datos enviados no son válidos.')
    expect(error.errores.correo).toEqual(['El correo es obligatorio.'])
  })

  it('envía JSON solo cuando hay cuerpo', async () => {
    const fetch = simularFetch(200, {})

    await pedir('/x', { method: 'POST', body: '{"a":1}' })

    expect(fetch.mock.calls[0][1].headers['Content-Type']).toBe('application/json')
  })

  it('avisa en español cuando no hay conexión con el servidor', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))

    await expect(pedir('/health')).rejects.toMatchObject({
      estado: 0,
      message: 'No se pudo conectar con el servidor. Revisa tu conexión.',
    })
  })

  it('usa un mensaje genérico si la respuesta de error no es JSON', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('<html>', { status: 502 })))

    await expect(pedir('/health')).rejects.toMatchObject({ estado: 502, message: 'Error 502 del servidor.' })
  })
})
