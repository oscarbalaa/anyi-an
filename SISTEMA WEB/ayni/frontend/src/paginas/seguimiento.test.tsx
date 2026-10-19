import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import type { ListaIncidencias } from '@/lib/incidencias'
import { hace, type ListaNotificaciones, type Notificacion } from '@/lib/notificaciones'
import { esquemaIncidencia } from '@/lib/validaciones'
import type { Rol } from '@/layout/roles'
import { conSesion, cuerpoEnviado, renderizarEn, simularApi } from '@/pruebas/utilidades'

// M10 - CU-22 y CU-23 en la interfaz

const ahora = new Date().toISOString()
const nota: Notificacion = {
  id: 11,
  tipo: 'NOTA_PUBLICADA',
  titulo: 'Notas publicadas',
  mensaje: 'Se publicaron tus notas de Matemática del IV Bimestre.',
  enlace: '/estudiante/notas?curso=7&bimestre=4',
  leida: false,
  fecha: ahora,
}
const incidencia: Notificacion = {
  id: 10,
  tipo: 'INCIDENCIA_REGISTRADA',
  titulo: 'Incidencia registrada',
  mensaje: 'Se registró una incidencia de tipo Conducta (leve) el 9 de octubre.',
  enlace: null,
  leida: false,
  fecha: ahora,
}
const leida: Notificacion = { ...nota, id: 5, titulo: 'Falta registrada', tipo: 'FALTA_REGISTRADA', leida: true }

const lista = (datos: Notificacion[]): ListaNotificaciones => ({
  datos,
  pagina: 1,
  por_pagina: 15,
  total: datos.length,
  ultima_pagina: 1,
  conteo: { todas: 3, pendientes: 2, leidas: 1 },
})

function comoRol(rol: Rol, ruta: string, respuestas: Parameters<typeof simularApi>[0]) {
  const usuario = conSesion(rol)
  const fetch = simularApi({
    '/auth/yo': [200, { usuario }],
    '/calendario/actual': [200, { periodo: null, bimestre: null }],
    '/notificaciones/resumen': [200, { pendientes: 2, recientes: [nota, incidencia, leida] }],
    ...respuestas,
  })
  renderizarEn(ruta)
  return fetch
}

describe('Campana (RF-51)', () => {
  it('muestra el contador y las últimas notificaciones; al abrir una la marca como leída', async () => {
    const fetch = comoRol('estudiante', '/estudiante', {
      'POST /notificaciones/11/leer': [200, { mensaje: 'Notificación marcada como leída.' }],
      '/estudiante/notas': [200, { periodo: null, bimestre: null, bimestres: [], cursos: [] }],
    })
    const campana = await screen.findByRole('button', { name: 'Notificaciones (2 sin leer)' })
    expect(screen.getByTestId('contador-notificaciones')).toHaveTextContent('2')

    await userEvent.click(campana)
    const panel = screen.getByRole('region', { name: 'Últimas notificaciones' })
    expect(within(panel).getAllByText('(sin leer)')).toHaveLength(2)
    expect(within(panel).getByRole('link', { name: 'Ver todas' })).toHaveAttribute('href', '/estudiante/notificaciones')

    await userEvent.click(within(panel).getByRole('link', { name: /Notas publicadas/ }))
    await waitFor(() =>
      expect(fetch).toHaveBeenCalledWith(expect.stringContaining('/notificaciones/11/leer'), expect.anything()),
    )
    expect(screen.queryByRole('region', { name: 'Últimas notificaciones' })).not.toBeInTheDocument()
  })

  it('sin pendientes no muestra contador', async () => {
    comoRol('docente', '/docente', { '/notificaciones/resumen': [200, { pendientes: 0, recientes: [] }] })
    await userEvent.click(await screen.findByRole('button', { name: 'Notificaciones' }))
    expect(screen.queryByTestId('contador-notificaciones')).not.toBeInTheDocument()
    expect(screen.getByText('Aún no tienes notificaciones.')).toBeInTheDocument()
  })
})

describe('Pantalla de notificaciones (los 3 roles)', () => {
  it('filtra por pestañas, lleva a cada pantalla y marca todas como leídas', async () => {
    const fetch = comoRol('estudiante', '/estudiante/notificaciones', {
      '/notificaciones': [200, lista([nota, incidencia, leida])],
      'POST /notificaciones/leer-todas': [200, { mensaje: 'Todas las notificaciones quedaron como leídas.' }],
      'POST /notificaciones/10/leer': [200, { mensaje: 'Notificación marcada como leída.' }],
    })
    expect(await screen.findByRole('heading', { name: 'Tienes 2 avisos sin leer' })).toBeInTheDocument()
    const items = within(screen.getByRole('list', { name: 'Notificaciones' })).getAllByRole('listitem')
    expect(items).toHaveLength(3)
    expect(within(items[0]).getByRole('link', { name: 'Ver notas: Notas publicadas' })).toHaveAttribute(
      'href',
      '/estudiante/notas?curso=7&bimestre=4',
    )

    // Sin enlace: se marca con su propio botón
    await userEvent.click(within(items[1]).getByRole('button', { name: 'Marcar como leída: Incidencia registrada' }))
    await waitFor(() =>
      expect(fetch).toHaveBeenCalledWith(expect.stringContaining('/notificaciones/10/leer'), expect.anything()),
    )

    await userEvent.click(screen.getByRole('tab', { name: /Sin leer \(2\)/ }))
    await waitFor(() =>
      expect(fetch).toHaveBeenCalledWith(expect.stringContaining('filtro=pendientes'), expect.anything()),
    )

    await userEvent.click(screen.getByRole('button', { name: 'Marcar todas como leídas' }))
    expect(await screen.findByText('Todas las notificaciones quedaron como leídas.')).toBeInTheDocument()
  })

  it('al día: lo celebra y la lista vacía lo dice', async () => {
    comoRol('admin', '/admin/notificaciones', {
      '/notificaciones/resumen': [200, { pendientes: 0, recientes: [] }],
      '/notificaciones': [200, { ...lista([]), total: 0, conteo: { todas: 0, pendientes: 0, leidas: 0 } }],
    })
    expect(await screen.findByRole('heading', { name: 'Vas al día con tus avisos' })).toBeInTheDocument()
    expect(screen.getByText('Aún no tienes notificaciones.', { selector: 'p' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Marcar todas como leídas' })).not.toBeInTheDocument()
  })

  it('el tiempo se lee como en el mockup', () => {
    const base = new Date('2026-10-19T15:00:00Z')
    expect(hace('2026-10-19T14:59:40Z', base)).toBe('Ahora')
    expect(hace('2026-10-19T14:40:00Z', base)).toBe('Hace 20 min')
    expect(hace('2026-10-19T12:00:00Z', base)).toBe('Hace 3 h')
    expect(hace('2026-10-18T12:00:00Z', base)).toBe('Ayer')
    expect(hace('2026-09-30T12:00:00Z', base)).toBe('30 set')
  })
})

const incidencias: ListaIncidencias = {
  datos: [
    {
      id: 3,
      fecha: '2026-10-15',
      gravedad: 'GRAVE',
      tipo: 'Conducta',
      descripcion: 'Agredió verbalmente a un compañero durante el recreo.',
      estudiante: { matricula_id: 21, nombre: 'Valeria Quispe Mendoza', codigo: 'E-2026-001' },
      aula: '3.º B',
      registrado_por: 'Luis Paredes',
    },
  ],
  pagina: 1,
  por_pagina: 10,
  total: 1,
  ultima_pagina: 1,
  resumen: { LEVE: 0, MODERADA: 0, GRAVE: 1 },
  tipos: [
    { id: 1, nombre: 'Conducta', descripcion: null },
    { id: 4, nombre: 'Salud', descripcion: null },
  ],
  aulas: [{ id: 32, nombre: '3.º B' }],
}
const estudiantes = {
  estudiantes: [
    { matricula_id: 21, nombre: 'Quispe Mendoza, Valeria', codigo: 'E-2026-001', aula: '3.º B' },
    { matricula_id: 22, nombre: 'Aranda Ruiz, Kevin', codigo: 'E-2026-002', aula: '3.º B' },
  ],
}

describe('Incidencias (RF-49, RF-50)', () => {
  it('lista con gravedad, autor y conteo; abre el historial de un estudiante', async () => {
    const fetch = comoRol('docente', '/docente/incidencias', {
      '/docente/incidencias': [200, incidencias],
      '/docente/incidencias/estudiantes': [200, estudiantes],
    })
    expect(await screen.findByRole('heading', { name: 'Incidencias', level: 1 })).toBeInTheDocument()
    expect((await screen.findAllByText('3.º B · Luis Paredes')).length).toBeGreaterThan(0)
    expect(screen.getByRole('list', { name: 'Incidencias por gravedad' })).toHaveTextContent('1 grave')

    await userEvent.click(screen.getAllByRole('button', { name: 'Ver historial de Valeria Quispe Mendoza' })[0])
    await waitFor(() =>
      expect(fetch).toHaveBeenCalledWith(expect.stringContaining('matricula_id=21'), expect.anything()),
    )
    expect(await screen.findByText('Historial de Valeria Quispe Mendoza')).toBeInTheDocument()
    // El formulario ya trae al estudiante del historial
    expect(screen.getByTestId('estudiante-elegido')).toHaveTextContent('Valeria Quispe Mendoza')
  })

  it('valida en la página y registra con los datos limpios', async () => {
    const fetch = comoRol('admin', '/admin/incidencias', {
      '/admin/incidencias': [200, incidencias],
      '/admin/incidencias/estudiantes': [200, estudiantes],
      'POST /admin/incidencias': [201, { mensaje: 'Incidencia registrada.', id: 9 }],
    })
    const guardar = await screen.findByRole('button', { name: 'Guardar incidencia' })
    await userEvent.click(guardar)
    expect(await screen.findByText('Elige al estudiante.')).toBeInTheDocument()
    expect(screen.getByText('Describe lo ocurrido.')).toBeInTheDocument()

    await userEvent.click(await screen.findByRole('button', { name: 'Elegir a Aranda Ruiz, Kevin' }))
    const descripcion = screen.getByLabelText('Descripción')
    await userEvent.type(descripcion, 'mal')
    await userEvent.click(guardar)
    expect(await screen.findByText('Describe lo ocurrido en 10 a 500 caracteres.')).toBeInTheDocument()

    await userEvent.clear(descripcion)
    await userEvent.type(descripcion, '  Se retiró   temprano por malestar. ')
    await userEvent.selectOptions(screen.getAllByLabelText('Gravedad')[1], 'MODERADA')
    await userEvent.selectOptions(screen.getAllByLabelText('Tipo')[1], '4')
    await userEvent.click(guardar)

    expect(await screen.findByText('Incidencia registrada.')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/admin/incidencias')).toMatchObject({
      matricula_id: 22,
      tipo_incidencia_id: 4,
      gravedad: 'MODERADA',
      descripcion: 'Se retiró temprano por malestar.',
    })
  })

  it('muestra el error del servidor en su campo', async () => {
    comoRol('docente', '/docente/incidencias', {
      '/docente/incidencias': [200, incidencias],
      '/docente/incidencias/estudiantes': [200, estudiantes],
      'POST /docente/incidencias': [
        422,
        {
          mensaje: 'La fecha no puede ser futura ni anterior al inicio del periodo.',
          errores: { fecha: ['La fecha no puede ser futura ni anterior al inicio del periodo.'] },
        },
      ],
    })
    await userEvent.click(await screen.findByRole('button', { name: 'Elegir a Quispe Mendoza, Valeria' }))
    await userEvent.type(screen.getByLabelText('Descripción'), 'Interrumpe la clase de forma reiterada.')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar incidencia' }))
    expect(
      await screen.findByText('La fecha no puede ser futura ni anterior al inicio del periodo.'),
    ).toBeInTheDocument()
  })

  it('el estudiante no tiene pantalla de incidencias', async () => {
    comoRol('estudiante', '/estudiante/incidencias', {})
    expect((await screen.findAllByText(/404/)).length).toBeGreaterThan(0)
  })

  it('el esquema pide estudiante, gravedad válida y 10 a 500 caracteres', () => {
    const base = { matricula_id: '1', tipo_incidencia_id: '1', gravedad: 'LEVE', fecha: '2026-10-19' } as const
    expect(esquemaIncidencia.safeParse({ ...base, descripcion: 'Llegó tarde tres veces.' }).success).toBe(true)
    expect(esquemaIncidencia.safeParse({ ...base, descripcion: 'x'.repeat(501) }).success).toBe(false)
    expect(
      esquemaIncidencia.safeParse({ ...base, gravedad: 'MEDIA', descripcion: 'Llegó tarde tres veces.' }).success,
    ).toBe(false)
  })
})
