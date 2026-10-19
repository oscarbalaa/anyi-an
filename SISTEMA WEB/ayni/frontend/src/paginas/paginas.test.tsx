import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import type { Rol } from '@/layout/roles'
import { CAPTCHA, conSesion, renderizarEn, simularApi } from '@/pruebas/utilidades'

const calendario = {
  periodo: { id: 1, nombre: '2026', fecha_inicio: '2026-02-23', fecha_fin: '2026-11-27' },
  bimestre: {
    id: 4,
    numero: 4,
    nombre: 'IV Bimestre',
    fecha_inicio: '2026-09-28',
    fecha_fin: '2026-11-27',
    fecha_cierre_notas: '2026-12-04',
    estado: 'ABIERTO',
  },
}

/** Abre una pantalla del rol con sesión iniciada y el calendario de la API */
function entrarComo(rol: Rol, ruta: string, calendarioApi: [number, unknown] = [200, calendario]) {
  const usuario = conSesion(rol)
  simularApi({ '/auth/yo': [200, { usuario }], '/calendario/actual': calendarioApi })
  return renderizarEn(ruta)
}

describe('landing', () => {
  it('tiene la portada, las secciones y el acceso a ingresar', async () => {
    renderizarEn('/')

    expect(await screen.findByRole('heading', { level: 1 })).toHaveTextContent(
      /Señales\s+claras\s*para acompañar mejor/,
    )
    for (const s of ['¿Qué es Ayni?', 'Descubre Ayni', 'Piki y Tambo', 'Contacto']) {
      expect(screen.getByRole('heading', { level: 2, name: s })).toBeInTheDocument()
    }
    expect(screen.getAllByRole('link', { name: /Ingresar/ })[0]).toHaveAttribute('href', '/ingresar')
    expect(screen.getByRole('link', { name: /Administración|Acceso administrativo/ })).toHaveAttribute(
      'href',
      '/ingresar/administracion',
    )
  })
})

describe('ingreso', () => {
  it('pide el código al estudiante y valida antes de enviar', async () => {
    const fetch = simularApi({ 'POST /captcha': [201, CAPTCHA] })
    renderizarEn('/ingresar/estudiante')

    await userEvent.click(await screen.findByRole('button', { name: 'Ingresar' }))
    expect(await screen.findByText('Ingresa tu código de estudiante.')).toBeInTheDocument()
    expect(screen.getByText('Ingresa tu contraseña.')).toBeInTheDocument()
    expect(screen.getByText('Escribe el código de la imagen.')).toBeInTheDocument()
    expect(fetch.mock.calls.some(([url]) => String(url).endsWith('/auth/ingresar'))).toBe(false)
  })

  it('muestra el acceso administrativo con usuario en vez de correo (D5)', async () => {
    simularApi({ 'POST /captcha': [201, CAPTCHA] })
    renderizarEn('/ingresar/administracion')

    expect(await screen.findByRole('heading', { name: 'Acceso administrativo' })).toBeInTheDocument()
    expect(screen.getByLabelText('Usuario')).toBeInTheDocument()
  })
})

describe('layout por rol', () => {
  it('muestra el menú del rol y el bimestre actual', async () => {
    entrarComo('admin', '/admin')

    const menu = await screen.findByRole('complementary', { name: 'Menú principal' })
    expect(within(menu).getAllByRole('link').length).toBeGreaterThanOrEqual(8)
    expect(await within(menu).findByText('IV Bimestre')).toBeInTheDocument()
    expect(within(menu).getByText('Periodo 2026')).toBeInTheDocument()
  })

  it('abre y cierra el menú en celular', async () => {
    entrarComo('estudiante', '/estudiante')

    const boton = await screen.findByRole('button', { name: 'Abrir menú' })
    expect(boton).toHaveAttribute('aria-expanded', 'false')
    await userEvent.click(boton)
    expect(boton).toHaveAttribute('aria-expanded', 'true')
    await userEvent.click(screen.getByRole('button', { name: 'Cerrar menú' }))
    expect(boton).toHaveAttribute('aria-expanded', 'false')
  })

  it('las pantallas pendientes indican su módulo', async () => {
    entrarComo('docente', '/docente/estudiantes')

    expect(await screen.findByRole('heading', { level: 1, name: 'Mis estudiantes' })).toBeInTheDocument()
    expect(screen.getByText('Módulo 12')).toBeInTheDocument()
  })

  it('si la API no responde, el menú sigue funcionando', async () => {
    entrarComo('docente', '/docente', [500, { mensaje: 'Error' }])

    expect(await screen.findByText('Sin bimestre activo')).toBeInTheDocument()
  })
})

describe('errores', () => {
  it.each([
    ['estudiante', '/no-existe'],
    ['admin', '/admin/no-existe'],
    ['estudiante', '/estudiante/clave'],
  ] as const)('muestra 404 (%s en %s)', async (rol, ruta) => {
    entrarComo(rol, ruta)

    expect(await screen.findByRole('heading', { name: 'Esta página no existe' })).toBeInTheDocument()
  })

  it('tiene página 403', async () => {
    renderizarEn('/sin-permiso')

    expect(await screen.findByRole('heading', { name: 'No tienes permiso para ver esta página' })).toBeInTheDocument()
  })
})
