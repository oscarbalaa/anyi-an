import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import type { Ficha, InicioAdmin, InicioDocente, InicioEstudiante, MisEstudiantes } from '@/lib/inicio'
import { saludoDelDia } from '@/lib/inicio'
import type { Rol } from '@/layout/roles'
import { conSesion, renderizarEn, simularApi } from '@/pruebas/utilidades'

// M12 - CU-27 y CU-28 en la interfaz: inicio de cada rol, ficha 360°, mis estudiantes y reportes

const saludo = {
  nombre: 'Carmen',
  detalle: 'Directora',
  fecha: '2026-06-10',
  periodo: '2026',
  bimestre: { id: 2, nombre: 'II Bimestre', estado: 'ABIERTO', fecha_cierre_notas: '2026-07-24', dias_para_cierre: 44 },
}

const inicioAdmin: InicioAdmin = {
  ...saludo,
  kpis: { estudiantes: 312, docentes: 18, coordinadores: 2, alertas_estudiantiles: 7, alertas_administrativas: 3 },
  atencion: [
    {
      id: 5,
      tipo: 'DOCENTE_SIN_ASISTENCIA',
      severidad: 'ATENCION',
      titulo: 'Asistencia sin registrar de Luis Paredes',
      detalle: '4 clases sin registrar',
      enlace: '/admin/asistencia',
    },
  ],
  avance: {
    asistencia: { hechas: 9, total: 10, porcentaje: 90 },
    notas: { hechas: 1, total: 3, porcentaje: 33 },
    promedios: { hechas: 0, total: 0, porcentaje: null },
  },
}

const inicioDocente: InicioDocente = {
  ...saludo,
  nombre: 'Ana',
  tareas: [
    {
      tipo: 'asistencia',
      titulo: 'Matemática · 3.º B',
      detalle: '2 clases sin registrar',
      extra: '',
      enlace: '/docente/asistencia?curso=4&fecha=2026-06-09',
    },
    {
      tipo: 'calificar',
      titulo: 'Calificar Práctica 1',
      detalle: '3 sin nota',
      extra: 'Matemática · 3.º B',
      enlace: '/docente/notas?evaluacion=8',
    },
  ],
  coordinacion: null,
  hoy: [{ curso_id: 4, curso: 'Matemática · 3.º B', inicio: '08:00', fin: '09:30', ambiente: null, registrada: false }],
}

const inicioEstudiante: InicioEstudiante = {
  ...saludo,
  nombre: 'Kevin',
  aula: '3.º B',
  tutor: 'Ana Torres',
  promedio: 13.6,
  promedio_bimestre: 'I Bimestre',
  cursos: [
    { id: 1, asignatura: 'Matemática', promedio: 14 },
    { id: 2, asignatura: 'Comunicación', promedio: null },
  ],
  asistencia: 92,
  pendientes: 2,
  por_entregar: [{ id: 3, titulo: 'Tarea 1', curso: 'Matemática', fecha_limite: '2026-06-12 23:59:00' }],
  hoy: [],
}

const ficha: Ficha = {
  estudiante: {
    matricula_id: 9,
    nombre: 'Kevin Aranda Ruiz',
    codigo: 'E-2026-001',
    aula: '3.º B',
    nivel_educativo: 'Secundaria',
    edad: 14,
    tutor: 'Ana Torres',
    estado: 'ACTIVA',
  },
  bimestre: { id: 2, nombre: 'II Bimestre' },
  riesgo: { nivel: 'ALTO', probabilidad: 60, fecha: '2026-06-10 10:00:00' },
  kpis: { promedio: 10.5, asistencia: 70, pendientes: 2, incidencias: 1 },
  alerta: {
    id: 31,
    estado: 'NUEVA',
    creada: '2026-06-10 10:00:00',
    responsable: 'Ana Torres',
    historial: [{ accion: 'GENERADA', detalle: 'Riesgo alto (puntaje 60 %).', fecha: '2026-06-10 10:00:00' }],
  },
  bimestres: [
    { id: 1, nombre: 'I Bimestre' },
    { id: 2, nombre: 'II Bimestre' },
  ],
  notas: [{ curso: 'Matemática', promedios: { 1: 14, 2: 9 } }],
  evolucion: [{ bimestre: 'II Bimestre', nivel: 'ALTO', probabilidad: 60 }],
  pendientes: [],
  incidencias: [],
  apoderados: [{ nombre: 'Rosa Ruiz Vega', parentesco: 'Madre', celular: '987654321', correo: null, principal: true }],
  factores: [{ titulo: 'Faltas frecuentes', descripcion: 'Asistencia de 70 %.' }],
}

const curso = (id: number, asignatura: string) => ({
  id,
  nombre: `${asignatura} · 3.º B`,
  asignatura,
  aula: '3.º B',
  aula_id: 1,
})

const misEstudiantes: MisEstudiantes = {
  cursos: [curso(4, 'Matemática'), curso(5, 'Comunicación')],
  curso: curso(4, 'Matemática'),
  bimestre: { id: 2, nombre: 'II Bimestre' },
  total: 2,
  estudiantes: [
    { matricula_id: 9, nombre: 'Aranda Ruiz, Kevin', codigo: 'E-2026-001', promedio: 9, asistencia: 70, nivel: 'ALTO' },
    {
      matricula_id: 10,
      nombre: 'Benítez Soto, Lucía',
      codigo: 'E-2026-002',
      promedio: 16,
      asistencia: 100,
      nivel: 'BAJO',
    },
  ],
}

function comoRol(rol: Rol, ruta: string, respuestas: Parameters<typeof simularApi>[0]) {
  const usuario = conSesion(rol)
  const fetch = simularApi({
    '/auth/yo': [200, { usuario }],
    '/calendario/actual': [200, { periodo: null, bimestre: null }],
    '/notificaciones/resumen': [200, { pendientes: 0, recientes: [] }],
    ...respuestas,
  })
  renderizarEn(ruta)
  return fetch
}

function simularDescargas() {
  const crear = vi.fn(() => 'blob:x')
  vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: crear, revokeObjectURL: vi.fn() }))
  return crear
}

describe('saludo del día (hora de Lima)', () => {
  it('cambia a las 12 y a las 19', () => {
    expect(saludoDelDia(new Date('2026-06-10T16:59:00Z'))).toBe('Buenos días')
    expect(saludoDelDia(new Date('2026-06-10T17:00:00Z'))).toBe('Buenas tardes')
    expect(saludoDelDia(new Date('2026-06-11T00:00:00Z'))).toBe('Buenas noches')
  })
})

describe('Inicio de cada rol (RF-59)', () => {
  it('la administración ve los KPIs, lo que necesita su atención y el avance de registro', async () => {
    comoRol('admin', '/admin', { '/admin/inicio': [200, inicioAdmin] })

    expect(await screen.findByRole('heading', { level: 1, name: `${saludoDelDia()}, Carmen` })).toBeInTheDocument()
    expect(screen.getByText('312')).toBeInTheDocument()
    expect(screen.getByText('Docentes · 2 coordinadores')).toBeInTheDocument()
    expect(screen.getByText('Necesitan su atención (1)')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Resolver: Asistencia sin registrar de Luis Paredes' })).toHaveAttribute(
      'href',
      '/admin/asistencia',
    )
    expect(screen.getByRole('progressbar', { name: 'Notas: 1 de 3 notas de evaluaciones vencidas' })).toHaveAttribute(
      'aria-valuenow',
      '33',
    )
  })

  it('el docente ve sus tareas pendientes con su enlace y sus clases de hoy', async () => {
    comoRol('docente', '/docente', { '/docente/inicio': [200, inicioDocente] })

    const tareas = await screen.findByRole('list', { name: 'Tareas pendientes' })
    expect(within(tareas).getByRole('link', { name: 'Ir: Calificar Práctica 1 (3 sin nota)' })).toHaveAttribute(
      'href',
      '/docente/notas?evaluacion=8',
    )
    expect(within(tareas).getAllByRole('listitem')).toHaveLength(2)
    expect(screen.getByText('Su aula · Asistencia pendiente')).toBeInTheDocument()
    // Sin coordinación, ofrece ir a "Mis estudiantes"
    expect(screen.getByRole('link', { name: 'Ver mis estudiantes' })).toHaveAttribute('href', '/docente/estudiantes')
  })

  it('el docente que coordina ve el resumen de riesgo de su ámbito', async () => {
    comoRol('docente', '/docente', {
      '/docente/inicio': [
        200,
        {
          ...inicioDocente,
          coordinacion: {
            ambito: '3.º A',
            ALTO: 2,
            MEDIO: 1,
            BAJO: 20,
            destacado: { nombre: 'Diego Ramírez', factor_principal: 'Faltas frecuentes', alerta_id: 3, nivel: 'ALTO' },
          },
        },
      ],
    })
    expect(await screen.findByText('Coordinación de 3.º A')).toBeInTheDocument()
    expect(screen.getByText('Diego Ramírez')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ver panel de mi ámbito' })).toHaveAttribute(
      'href',
      '/docente/coordinacion',
    )
  })

  it('el estudiante ve su promedio, sus pendientes y descarga su boleta', async () => {
    const crear = simularDescargas()
    const fetch = comoRol('estudiante', '/estudiante', {
      '/estudiante/inicio': [200, inicioEstudiante],
      '/estudiante/boleta': [200, {}],
    })

    expect(await screen.findByRole('heading', { level: 1, name: 'Hola, Kevin' })).toBeInTheDocument()
    expect(screen.getByText('13,6')).toBeInTheDocument()
    expect(screen.getByText('Mi promedio · I Bimestre')).toBeInTheDocument()
    expect(screen.getByText('2 actividades')).toBeInTheDocument()
    expect(screen.getByText('Sin publicar')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Descargar mi boleta' }))
    await waitFor(() => expect(crear).toHaveBeenCalled())
    expect(fetch).toHaveBeenCalledWith(expect.stringContaining('/estudiante/boleta'), expect.anything())
  })

  it('si el inicio no carga, ofrece reintentar', async () => {
    comoRol('estudiante', '/estudiante', { '/estudiante/inicio': [500, { mensaje: 'Error' }] })
    expect(await screen.findByText('No se pudo cargar el inicio', {}, { timeout: 4000 })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reintentar' })).toBeInTheDocument()
  })
})

describe('Ficha 360° (RF-60)', () => {
  it('reúne riesgo, KPIs, alerta, notas por bimestre y apoderados; la administración baja la boleta', async () => {
    const crear = simularDescargas()
    const fetch = comoRol('admin', '/admin/ficha?matricula=9', {
      '/admin/fichas/9': [200, ficha],
      '/admin/fichas/9/boleta': [200, {}],
    })

    expect(await screen.findByRole('heading', { level: 1, name: 'Kevin Aranda Ruiz' })).toBeInTheDocument()
    expect(screen.getByText('Riesgo alto')).toBeInTheDocument()
    expect(screen.getByText('10,5')).toBeInTheDocument()
    const tabla = screen.getByRole('table', { name: 'Promedio por curso' })
    expect(within(tabla).getByRole('row', { name: 'Matemática 14 9' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Abrir la alerta' })).toHaveAttribute('href', '/admin/alertas?id=31')
    expect(screen.getByText('Rosa Ruiz Vega')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Boleta en PDF' }))
    await waitFor(() => expect(crear).toHaveBeenCalled())
    expect(fetch).toHaveBeenCalledWith(expect.stringContaining('/admin/fichas/9/boleta'), expect.anything())
  })

  it('el docente no ve el botón de la boleta y vuelve a "Mis estudiantes"', async () => {
    comoRol('docente', '/docente/ficha?matricula=9', { '/docente/fichas/9': [200, ficha] })

    expect(await screen.findByRole('heading', { level: 1, name: 'Kevin Aranda Ruiz' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Boleta en PDF' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Volver a Mis estudiantes' })).toHaveAttribute(
      'href',
      '/docente/estudiantes',
    )
  })

  it('sin estudiante elegido lo pide', async () => {
    comoRol('admin', '/admin/ficha', {})
    expect(await screen.findByText('Elige a un estudiante para ver su ficha')).toBeInTheDocument()
  })
})

describe('Mis estudiantes (RF-61)', () => {
  it('lista con promedio, asistencia y nivel; filtra por nivel y cambia de curso', async () => {
    const fetch = comoRol('docente', '/docente/estudiantes', { '/docente/estudiantes': [200, misEstudiantes] })

    expect(await screen.findByRole('heading', { level: 1, name: 'Mis estudiantes' })).toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: 'Ver la ficha de Aranda Ruiz, Kevin' })[0]).toHaveAttribute(
      'href',
      '/docente/ficha?matricula=9',
    )
    expect(screen.getAllByText('Benítez Soto, Lucía').length).toBeGreaterThan(0)

    await userEvent.selectOptions(screen.getByLabelText('Nivel de riesgo'), 'ALTO')
    expect(screen.queryAllByText('Benítez Soto, Lucía')).toHaveLength(0)

    await userEvent.selectOptions(screen.getByLabelText('Curso'), '5')
    await waitFor(() =>
      expect(fetch).toHaveBeenCalledWith(expect.stringContaining('/docente/estudiantes?curso_id=5'), expect.anything()),
    )
  })

  it('sin cursos a cargo lo explica', async () => {
    comoRol('docente', '/docente/estudiantes', {
      '/docente/estudiantes': [200, { cursos: [], curso: null, bimestre: null, total: 0, estudiantes: [] }],
    })
    expect(await screen.findByText('Aún no tienes cursos a cargo')).toBeInTheDocument()
  })
})

describe('Reportes (RF-63, RF-64)', () => {
  it('descarga la asistencia y el consolidado del aula y bimestre elegidos', async () => {
    simularDescargas()
    const fetch = comoRol('admin', '/admin/reportes', {
      '/admin/reportes/opciones': [
        200,
        {
          aulas: [
            { id: 1, nombre: '3.º B' },
            { id: 2, nombre: '4.º A' },
          ],
          bimestres: [
            { id: 1, nombre: 'I Bimestre' },
            { id: 2, nombre: 'II Bimestre' },
          ],
          bimestre: 2,
        },
      ],
      '/admin/reportes/asistencia': [200, {}],
      '/admin/reportes/consolidado': [200, {}],
    })

    expect(await screen.findByRole('heading', { level: 1, name: 'Reportes' })).toBeInTheDocument()
    await userEvent.selectOptions(screen.getByLabelText('Aula'), '2')
    await userEvent.click(screen.getByRole('button', { name: 'Descargar asistencia' }))
    expect(await screen.findByText('Asistencia descargada.')).toBeInTheDocument()
    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining('/admin/reportes/asistencia?aula_id=2&bimestre_id=2'),
      expect.anything(),
    )

    await userEvent.selectOptions(screen.getByLabelText('Bimestre'), '1')
    await userEvent.click(screen.getByRole('button', { name: 'Descargar consolidado' }))
    expect(await screen.findByText('Consolidado descargado.')).toBeInTheDocument()
    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining('/admin/reportes/consolidado?aula_id=2&bimestre_id=1'),
      expect.anything(),
    )
  })
})
