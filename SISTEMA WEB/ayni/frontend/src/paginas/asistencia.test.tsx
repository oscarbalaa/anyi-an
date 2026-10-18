import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import type { Clase, MiAsistencia, ResumenAdmin } from '@/lib/asistencia'
import { esquemaAsistencia } from '@/lib/validaciones'
import { conSesion, cuerpoEnviado, renderizarEn, simularApi } from '@/pruebas/utilidades'

// M8 - CU-13 a CU-15 en la interfaz

const bimestre = {
  id: 4,
  nombre: 'IV Bimestre',
  estado: 'ABIERTO',
  fecha_inicio: '2026-10-05',
  fecha_fin: '2026-12-04',
} as const
const curso = {
  id: 7,
  nombre: 'Matemática · 3.º B',
  asignatura: 'Matemática',
  aula: '3.º B',
  aula_id: 32,
  docente: 'Luis Paredes',
}

const clase = (extra: Partial<Clase['sesion']> = {}): Clase => ({
  curso,
  fecha: '2026-10-15',
  bimestre,
  sesion: {
    estado: 'SIN_REGISTRO',
    tema: null,
    registrado_por: null,
    actualizado_en: null,
    editable: true,
    aviso: 'Puedes corregir este registro hasta las 11:59 p. m. de hoy.',
    ...extra,
  },
  estudiantes: [
    {
      matricula_id: 1,
      nombre: 'Aranda Ruiz, Kevin',
      codigo: 'E-2026-101',
      estado: 'P',
      observacion: null,
      faltas_semana: 0,
      corregido_por: null,
    },
    {
      matricula_id: 2,
      nombre: 'Ramírez Torres, Diego',
      codigo: 'E-2026-102',
      estado: 'P',
      observacion: null,
      faltas_semana: 2,
      corregido_por: null,
    },
    {
      matricula_id: 3,
      nombre: 'Salas Vega, Paolo',
      codigo: 'E-2026-103',
      estado: 'P',
      observacion: null,
      faltas_semana: 0,
      corregido_por: null,
    },
  ],
  resumen: { P: 3, F: 0, T: 0, J: 0 },
  aviso_faltas: 2,
})

const fechas = {
  curso,
  bimestre,
  bimestres: [bimestre],
  fechas: [
    { fecha: '2026-10-15', estado: 'SIN_REGISTRO', hoy: true },
    { fecha: '2026-10-13', estado: 'DEFINITIVO', hoy: false },
  ],
}

function comoDocente(respuestas: Parameters<typeof simularApi>[0]) {
  const usuario = conSesion('docente')
  const fetch = simularApi({
    '/auth/yo': [200, { usuario }],
    '/calendario/actual': [200, { periodo: null, bimestre: null }],
    '/docente/asistencia/cursos': [200, { bimestre, cursos: [{ ...curso, dias: [2, 4], pendientes: 1 }] }],
    '/docente/asistencia/cursos/7/fechas': [200, fechas],
    ...respuestas,
  })
  renderizarEn('/docente/asistencia')
  return fetch
}

const fila = (nombre: string) => screen.getByRole('group', { name: `Asistencia de ${nombre}` })

describe('Registro de asistencia del docente (RF-33, RF-37)', () => {
  it('muestra a todos presentes, avisa la falta acumulada y exige el motivo de la justificación', async () => {
    const fetch = comoDocente({
      '/docente/asistencia/cursos/7/2026-10-15': [200, clase()],
      'PUT /docente/asistencia/cursos/7/2026-10-15': [
        200,
        { ...clase({ estado: 'DEFINITIVO' }), mensaje: 'Asistencia guardada.' },
      ],
    })

    expect(await screen.findByText('Matemática · 3.º B · Jueves 15 de octubre de 2026')).toBeInTheDocument()
    await screen.findByRole('group', { name: 'Asistencia de Aranda Ruiz, Kevin' })
    expect(screen.getByTestId('conteo-asistencia')).toHaveTextContent('3 presentes · 0 faltas · 0 tardanzas')
    expect(within(fila('Aranda Ruiz, Kevin')).getByRole('radio', { name: 'Presente' })).toBeChecked()

    await userEvent.click(within(fila('Ramírez Torres, Diego')).getByRole('radio', { name: 'Falta' }))
    expect(screen.getByText('3.ª falta esta semana')).toBeInTheDocument()
    await userEvent.click(within(fila('Salas Vega, Paolo')).getByRole('radio', { name: 'Falta justificada' }))
    expect(screen.getByTestId('conteo-asistencia')).toHaveTextContent(
      '1 presente · 1 falta · 0 tardanzas · 1 justificada',
    )

    await userEvent.click(screen.getByRole('button', { name: 'Guardar asistencia' }))
    expect(await screen.findByText('Escribe el motivo de la justificación.')).toBeInTheDocument()
    expect(fetch.mock.calls.some(([, o]) => o?.method === 'PUT')).toBe(false)

    await userEvent.type(screen.getByLabelText('Observación de Salas Vega, Paolo'), '  Cita   médica ')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar asistencia' }))
    expect(await screen.findByText('Asistencia guardada.')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/docente/asistencia/cursos/7/2026-10-15')).toEqual({
      estado: 'DEFINITIVO',
      tema: null,
      registros: [
        { matricula_id: 1, estado: 'P', observacion: null },
        { matricula_id: 2, estado: 'F', observacion: null },
        { matricula_id: 3, estado: 'J', observacion: 'Cita médica' },
      ],
    })
  })

  it('una clase cerrada para el docente queda de solo lectura (RN-07)', async () => {
    const aviso =
      'Ya pasó la medianoche del día de la clase: solo el administrador puede corregir esta asistencia (RN-07).'
    comoDocente({
      '/docente/asistencia/cursos/7/2026-10-15': [
        200,
        clase({ estado: 'DEFINITIVO', editable: false, aviso, registrado_por: 'Luis Paredes' }),
      ],
    })

    expect(await screen.findByText(aviso)).toBeInTheDocument()
    expect(screen.getByText('Registrada')).toBeInTheDocument()
    expect(within(fila('Aranda Ruiz, Kevin')).getByRole('radio', { name: 'Falta' })).toBeDisabled()
    expect(screen.queryByRole('button', { name: /Guardar/ })).not.toBeInTheDocument()
  })

  it('muestra junto al estudiante el error que devuelve el servidor', async () => {
    comoDocente({
      '/docente/asistencia/cursos/7/2026-10-15': [200, clase()],
      'PUT /docente/asistencia/cursos/7/2026-10-15': [
        422,
        {
          mensaje: 'Los datos enviados no son válidos.',
          errores: { 'registros.1.observacion': ['La observación no debe tener más de 300 caracteres.'] },
        },
      ],
    })
    await userEvent.click(await screen.findByRole('button', { name: 'Guardar borrador' }))
    expect(await screen.findByText('La observación no debe tener más de 300 caracteres.')).toBeInTheDocument()
    expect(screen.getByLabelText('Observación de Ramírez Torres, Diego')).toHaveAttribute('aria-invalid', 'true')
  })
})

describe('Mi asistencia del estudiante (RF-36)', () => {
  const datos: MiAsistencia = {
    periodo: '2026',
    aula: '3.º B',
    bimestre,
    bimestres: [bimestre],
    meta: 90,
    resumen: { clases: 4, presentes: 1, faltas: 1, tardanzas: 1, justificadas: 1, porcentaje: 75 },
    por_curso: [
      {
        curso_id: 7,
        asignatura: 'Matemática',
        clases: 4,
        presentes: 1,
        faltas: 1,
        tardanzas: 1,
        justificadas: 1,
        porcentaje: 75,
      },
      {
        curso_id: 8,
        asignatura: 'Comunicación',
        clases: 0,
        presentes: 0,
        faltas: 0,
        tardanzas: 0,
        justificadas: 0,
        porcentaje: null,
      },
    ],
    por_mes: [{ mes: '2026-10', clases: 4, presentes: 1, faltas: 1, tardanzas: 1, justificadas: 1, porcentaje: 75 }],
    registros: [
      { fecha: '2026-10-15', curso: 'Matemática', hora: '08:00', estado: 'P', observacion: null },
      { fecha: '2026-10-13', curso: 'Matemática', hora: '09:45', estado: 'F', observacion: null },
      { fecha: '2026-10-08', curso: 'Matemática', hora: '08:00', estado: 'T', observacion: 'Llegó 8:20' },
      { fecha: '2026-10-06', curso: 'Matemática', hora: '09:45', estado: 'J', observacion: 'Cita médica' },
    ],
  }

  it('muestra el porcentaje, los conteos, el calendario y filtra los registros', async () => {
    const usuario = conSesion('estudiante')
    simularApi({
      '/auth/yo': [200, { usuario }],
      '/calendario/actual': [200, { periodo: null, bimestre: null }],
      '/estudiante/asistencia': [200, datos],
    })
    renderizarEn('/estudiante/asistencia')

    expect(await screen.findByText('Resumen del IV Bimestre · 3.º B')).toBeInTheDocument()
    expect(screen.getAllByText('75 %').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Falta justificada').length).toBeGreaterThan(0)
    expect(screen.getByText('Octubre 2026')).toBeInTheDocument()
    expect(screen.getByText('Meta 90 %')).toBeInTheDocument()

    const registros = screen.getByRole('table', { name: 'Registros de asistencia' })
    expect(within(registros).getAllByRole('row')).toHaveLength(5)
    await userEvent.selectOptions(screen.getByLabelText('Mostrar'), 'F')
    const filtrados = screen.getByRole('table', { name: 'Registros de asistencia' })
    expect(within(filtrados).getAllByRole('row')).toHaveLength(2)
    expect(within(filtrados).getByText('Falta')).toBeInTheDocument()
  })
})

describe('Asistencia del administrador (RF-35)', () => {
  const resumen: ResumenAdmin = {
    bimestre,
    bimestres: [bimestre],
    meta: 90,
    aulas: [
      {
        id: 32,
        nombre: '3.º B',
        estudiantes: 7,
        clases: 40,
        faltas: 30,
        tardanzas: 4,
        justificadas: 2,
        porcentaje: 89.3,
      },
      {
        id: 33,
        nombre: '4.º A',
        estudiantes: 14,
        clases: 38,
        faltas: 5,
        tardanzas: 2,
        justificadas: 1,
        porcentaje: 99.1,
      },
    ],
    sin_registro: [
      {
        docente_id: 3,
        docente: 'Ana Lozano',
        total: 3,
        desde: '2026-10-07',
        cursos: [{ id: 7, nombre: 'Matemática · 3.º B', fechas: ['2026-10-07', '2026-10-09', '2026-10-14'] }],
      },
    ],
  }

  it('resume por aula, lista las clases sin registrar y abre la corrección', async () => {
    const usuario = conSesion('admin')
    const fetch = simularApi({
      '/auth/yo': [200, { usuario }],
      '/calendario/actual': [200, { periodo: null, bimestre: null }],
      '/admin/asistencia/resumen': [200, resumen],
      '/admin/asistencia/cursos': [200, { periodo: '2026', aulas: [{ id: 32, nombre: '3.º B', cursos: [curso] }] }],
      '/admin/asistencia/cursos/7/fechas': [
        200,
        { ...fechas, fechas: [...fechas.fechas, { fecha: '2026-10-14', estado: 'SIN_REGISTRO', hoy: false }] },
      ],
      '/admin/asistencia/cursos/7/2026-10-14': [
        200,
        clase({
          aviso:
            'Como administrador puedes corregir este registro y justificar faltas. El cambio queda registrado con tu nombre.',
        }),
      ],
    })
    renderizarEn('/admin/asistencia')

    expect((await screen.findAllByText('89,3 %'))[0]).toBeInTheDocument()
    expect(screen.getAllByText('Bajo la meta (90 %)')).toHaveLength(2)
    expect(screen.getByText('Ana Lozano')).toBeInTheDocument()
    expect(screen.getByText('3 clases · desde mié 7 oct')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Revisar Matemática · 3.º B' }))
    expect(await screen.findByRole('tab', { name: 'Corregir asistencia', selected: true })).toBeInTheDocument()
    expect(await screen.findByText('Matemática · 3.º B · Miércoles 14 de octubre de 2026')).toBeInTheDocument()
    expect(await screen.findByRole('button', { name: 'Guardar corrección' })).toBeInTheDocument()
    await waitFor(() =>
      expect(fetch.mock.calls.some(([u]) => String(u).endsWith('/admin/asistencia/cursos/7/2026-10-14'))).toBe(true),
    )
  })
})

describe('esquemaAsistencia', () => {
  it('pide el motivo de la justificación y limita la observación', () => {
    const r = (estado: string, observacion: string) =>
      esquemaAsistencia.safeParse({ tema: '', registros: [{ matricula_id: 1, estado, observacion }] })
    expect(r('J', '  ').success).toBe(false)
    expect(r('J', 'Cita médica').success).toBe(true)
    expect(r('T', 'a'.repeat(301)).success).toBe(false)
    expect(r('X', '').success).toBe(false)
  })
})
