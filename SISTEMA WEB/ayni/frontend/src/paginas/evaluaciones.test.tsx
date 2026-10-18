import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import type {
  DetalleActividad,
  ListaEvaluaciones,
  MiHorario,
  MisNotas,
  Planilla,
  VistaCalificaciones,
} from '@/lib/evaluaciones'
import { errorNota, esquemaEvaluacion, filtros } from '@/lib/validaciones'
import { conSesion, cuerpoEnviado, renderizarEn, simularApi } from '@/pruebas/utilidades'

// M9 - CU-16 a CU-19 en la interfaz

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
const misCursos = { bimestre, cursos: [{ ...curso, dias: [2, 4], pendientes: 0 }] }

const lista: ListaEvaluaciones = {
  curso,
  bimestre,
  bimestres: [bimestre],
  tipos: [
    { id: 1, nombre: 'Examen' },
    { id: 2, nombre: 'Práctica' },
  ],
  resumen: { evaluaciones: 1, entregas: 81, por_calificar: 1 },
  evaluaciones: [
    {
      id: 50,
      titulo: 'Práctica 6 · Funciones',
      tipo: 'Práctica',
      tipo_evaluacion_id: 2,
      peso: 1,
      fecha_asignacion: '2026-10-12',
      fecha_limite: '2026-10-16',
      indicaciones: null,
      requiere_entrega: true,
      momento: 'VENCIDA',
      entregas: 25,
      calificadas: 0,
      estudiantes: 29,
      material: [],
    },
  ],
  editable: true,
  limite_archivo: '10 MB',
}

function comoRol(rol: 'docente' | 'estudiante', ruta: string, respuestas: Parameters<typeof simularApi>[0]) {
  const usuario = conSesion(rol)
  const fetch = simularApi({
    '/auth/yo': [200, { usuario }],
    '/calendario/actual': [200, { periodo: null, bimestre: null }],
    '/docente/asistencia/cursos': [200, misCursos],
    ...respuestas,
  })
  renderizarEn(ruta)
  return fetch
}

describe('Evaluaciones del docente (RF-38)', () => {
  it('lista con sus indicadores y crea una evaluación validando en la página', async () => {
    const fetch = comoRol('docente', '/docente/evaluaciones', {
      '/docente/cursos/7/evaluaciones': [200, lista],
      'POST /docente/cursos/7/evaluaciones': [201, { mensaje: 'Evaluación creada.', id: 51 }],
    })

    expect((await screen.findAllByText('Práctica 6 · Funciones'))[0]).toBeInTheDocument()
    expect(screen.getByText('81 %')).toBeInTheDocument()
    expect(screen.getAllByText('25 de 29')[0]).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Nueva evaluación' }))
    const v = await screen.findByRole('dialog', { name: 'Nueva evaluación' })
    await userEvent.type(within(v).getByLabelText('Título'), 'P7')
    await userEvent.click(within(v).getByRole('button', { name: 'Crear evaluación' }))
    expect(await within(v).findByText('El título debe tener de 3 a 150 caracteres.')).toBeInTheDocument()
    expect(within(v).getByText('Ingresa una fecha de asignación válida.')).toBeInTheDocument()

    await userEvent.clear(within(v).getByLabelText('Título'))
    await userEvent.type(within(v).getByLabelText('Título'), 'Práctica 7 · Inecuaciones')
    await userEvent.type(within(v).getByLabelText('Fecha de asignación'), '2026-10-19')
    await userEvent.type(within(v).getByLabelText('Fecha límite'), '2026-10-16')
    await userEvent.click(within(v).getByRole('button', { name: 'Crear evaluación' }))
    expect(await within(v).findByText('La fecha límite no puede ser anterior a la de asignación.')).toBeInTheDocument()

    await userEvent.clear(within(v).getByLabelText('Fecha límite'))
    await userEvent.type(within(v).getByLabelText('Fecha límite'), '2026-10-23')
    await userEvent.selectOptions(within(v).getByLabelText('Peso en el promedio'), '2')
    await userEvent.click(within(v).getByRole('button', { name: 'Crear evaluación' }))
    expect(await screen.findByText('Evaluación creada.')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/docente/cursos/7/evaluaciones')).toEqual({
      titulo: 'Práctica 7 · Inecuaciones',
      tipo_evaluacion_id: 2,
      subperiodo_id: 4,
      fecha_asignacion: '2026-10-19',
      fecha_limite: '2026-10-23',
      peso: 2,
      indicaciones: null,
      requiere_entrega: true,
    })
  })

  it('al elegir Examen deja de pedir archivo', async () => {
    comoRol('docente', '/docente/evaluaciones', { '/docente/cursos/7/evaluaciones': [200, lista] })
    await userEvent.click(await screen.findByRole('button', { name: 'Nueva evaluación' }))
    const v = await screen.findByRole('dialog', { name: 'Nueva evaluación' })
    expect(within(v).getByLabelText('Los estudiantes entregan un archivo (PDF)')).toBeChecked()
    await userEvent.selectOptions(within(v).getByLabelText('Tipo'), '1')
    expect(within(v).getByLabelText('Los estudiantes entregan un archivo (PDF)')).not.toBeChecked()
  })
})

const planilla: Planilla = {
  evaluacion: {
    id: 50,
    titulo: 'Práctica 6 · Funciones',
    tipo: 'Práctica',
    peso: 1,
    fecha_asignacion: '2026-10-12',
    fecha_limite: '2026-10-16',
    requiere_entrega: true,
  },
  curso,
  bimestre,
  nota_aprobatoria: 11,
  publicada: false,
  editable: true,
  resumen: { promedio: 13.2, desaprobados: 1, sin_entregar: 1 },
  estudiantes: [
    {
      matricula_id: 1,
      nombre: 'Aranda Ruiz, Kevin',
      codigo: 'E-2026-101',
      nota: null,
      estado_entrega: 'NO_ENTREGADO',
      estado_sugerido: 'NO_ENTREGADO',
      observacion: null,
      entrega: null,
    },
    {
      matricula_id: 2,
      nombre: 'Quispe Mendoza, Valeria',
      codigo: 'E-2026-001',
      nota: 18,
      estado_entrega: 'ENTREGADO',
      estado_sugerido: 'ENTREGADO',
      observacion: null,
      entrega: {
        entregada_en: '2026-10-15T20:00:00-05:00',
        es_tardia: false,
        archivo: { id: 9, nombre: 'tarea.pdf', tamano: 2000, tipo: 'application/pdf' },
      },
    },
  ],
}

describe('Notas (RF-43, RN-08)', () => {
  it('bloquea letras, marca notas fuera de rango, avisa cambios sin guardar y guarda con coma', async () => {
    const fetch = comoRol('docente', '/docente/notas?evaluacion=50', {
      '/docente/evaluaciones/50/notas': [200, planilla],
      'PUT /docente/evaluaciones/50/notas': [200, { ...planilla, mensaje: 'Notas guardadas.' }],
    })

    const kevin = await screen.findByLabelText('Nota de Aranda Ruiz, Kevin')
    expect(screen.getByLabelText('Nota de Quispe Mendoza, Valeria')).toHaveValue('18')
    expect(screen.getByRole('button', { name: 'Descargar la entrega de Quispe Mendoza, Valeria' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Guardar notas/ })).toBeDisabled()

    await userEvent.type(kevin, 'abc25')
    expect(kevin).toHaveValue('25')
    expect(screen.getByText('La nota debe estar entre 0 y 20.')).toBeInTheDocument()
    expect(screen.getByRole('status')).toHaveTextContent('Cambios sin guardar')

    await userEvent.clear(kevin)
    await userEvent.type(kevin, '15,5')
    expect(screen.queryByText('La nota debe estar entre 0 y 20.')).not.toBeInTheDocument()
    await userEvent.selectOptions(screen.getByLabelText('Estado de entrega de Aranda Ruiz, Kevin'), 'TARDIO')
    await userEvent.click(screen.getByRole('button', { name: /Guardar notas/ }))

    expect(await screen.findByText('Notas guardadas.')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/docente/evaluaciones/50/notas')).toEqual({
      registros: [
        { matricula_id: 1, nota: '15,5', estado_entrega: 'TARDIO', observacion: null },
        { matricula_id: 2, nota: '18', estado_entrega: 'ENTREGADO', observacion: null },
      ],
    })
  })
})

const vista: VistaCalificaciones = {
  curso,
  bimestre,
  bimestres: [bimestre],
  estado: 'BORRADOR',
  publicado_en: null,
  nota_aprobatoria: 11,
  evaluaciones: [
    { id: 50, titulo: 'Práctica 6', tipo: 'Práctica', peso: 1, fecha_limite: '2026-10-16' },
    { id: 51, titulo: 'Proyecto', tipo: 'Proyecto', peso: 2, fecha_limite: '2026-10-23' },
  ],
  estudiantes: [
    { matricula_id: 1, nombre: 'Aranda Ruiz, Kevin', notas: { 50: null, 51: 10 }, promedio: 6.7, literal: 'C' },
    { matricula_id: 2, nombre: 'Quispe Mendoza, Valeria', notas: { 50: 18, 51: 16 }, promedio: 16.7, literal: 'A' },
  ],
  resumen: { estudiantes: 2, promedio: 11.7, desaprobados: 1 },
  sin_nota: [{ id: 50, titulo: 'Práctica 6', fecha_limite: '2026-10-16', estudiantes: ['Aranda Ruiz, Kevin'] }],
  editable: true,
}

describe('Calificaciones (RF-44, RF-45)', () => {
  it('muestra el promedio ponderado y, antes de publicar, a quiénes se les pondrá 0', async () => {
    const fetch = comoRol('docente', '/docente/calificaciones', {
      '/docente/cursos/7/calificaciones': [200, vista],
      'POST /docente/cursos/7/calificaciones/publicar': [
        200,
        { ...vista, estado: 'PUBLICADA', mensaje: 'Promedios publicados. Los estudiantes ya pueden ver sus notas.' },
      ],
    })

    expect(await screen.findByRole('heading', { name: 'Calificaciones del IV Bimestre' })).toBeInTheDocument()
    expect(screen.getByText('16,7')).toBeInTheDocument()
    expect(screen.getByText(/Faltan notas/)).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Publicar promedios' }))
    const d = await screen.findByRole('dialog', { name: 'Publicar promedios' })
    expect(within(d).getByText('Se pondrá 0 en lo que falta calificar:')).toBeInTheDocument()
    expect(within(d).getByText('Aranda Ruiz, Kevin', { exact: false })).toBeInTheDocument()
    await userEvent.click(within(d).getByRole('button', { name: 'Sí, publicar' }))
    expect(
      await screen.findByText('Promedios publicados. Los estudiantes ya pueden ver sus notas.'),
    ).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/docente/cursos/7/calificaciones/publicar')).toEqual({ bimestre_id: 4 })
  })
})

const actividad: DetalleActividad = {
  id: 60,
  titulo: 'Práctica 7 · Inecuaciones',
  tipo: 'Práctica',
  curso: 'Matemática',
  curso_id: 7,
  docente: 'Luis Paredes',
  fecha_asignacion: '2026-10-12',
  fecha_limite: '2026-10-19',
  peso: 1,
  peso_porcentaje: 20,
  requiere_entrega: true,
  estado: 'PENDIENTE',
  nota: null,
  indicaciones: 'Resuelve los ejercicios 1 al 12.\nIncluye el procedimiento.',
  bimestre,
  material: [{ id: 3, nombre: 'Guía.pdf', tamano: 1258291, tipo: 'application/pdf' }],
  entrega: null,
  historial: [{ fecha: '2026-10-12T00:00:00-05:00', texto: 'Actividad asignada' }],
  puede_entregar: true,
  aviso: 'Puedes entregar hasta las 11:59 p. m. del día límite.',
  limite_archivo: '10 MB',
}

describe('Actividades del estudiante (RF-40, RF-41)', () => {
  it('lista las pendientes y entrega un PDF', async () => {
    const fetch = comoRol('estudiante', '/estudiante/actividades', {
      '/estudiante/actividades': [
        200,
        {
          bimestre,
          bimestres: [bimestre],
          resumen: { pendientes: 1, por_vencer: 1, completadas: 0 },
          primera: actividad,
          actividades: [actividad],
        },
      ],
      '/estudiante/actividades/60': [200, actividad],
      'POST /estudiante/actividades/60/entrega': [
        200,
        {
          ...actividad,
          estado: 'ENTREGADO',
          puede_entregar: false,
          aviso: 'Ya entregaste esta actividad.',
          mensaje: '¡Actividad entregada!',
        },
      ],
    })

    expect(await screen.findByText('Primero esta semana')).toBeInTheDocument()
    await userEvent.click(screen.getAllByRole('link', { name: 'Ver Práctica 7 · Inecuaciones' })[0])
    expect(await screen.findByRole('heading', { name: 'Práctica 7 · Inecuaciones', level: 1 })).toBeInTheDocument()
    expect(screen.getByText('20 % del bimestre')).toBeInTheDocument()
    expect(screen.getByText('1,2 MB')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Entregar actividad/ }))
    expect(await screen.findByText('Adjunta tu archivo PDF.')).toBeInTheDocument()

    const entrada = screen.getByLabelText('Archivo PDF de la entrega')
    await userEvent.upload(entrada, new File(['MZ'], 'virus.exe', { type: 'application/octet-stream' }), {
      applyAccept: false,
    })
    expect(await screen.findByText('El archivo debe ser un PDF.')).toBeInTheDocument()
    await userEvent.upload(entrada, new File(['%PDF-1.4'], 'tarea.pdf', { type: 'application/pdf' }))
    expect(screen.getByText('tarea.pdf')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: /Entregar actividad/ }))

    expect(await screen.findByText('¡Actividad entregada!')).toBeInTheDocument()
    const llamada = fetch.mock.calls.find(
      ([u, o]) => String(u).endsWith('/estudiante/actividades/60/entrega') && o?.method === 'POST',
    )
    const datos = llamada?.[1].body as FormData
    expect(datos.get('accion')).toBe('ENTREGAR')
    expect((datos.get('archivo') as File).name).toBe('tarea.pdf')
  })
})

describe('Mis notas y Mi horario (RF-47, RF-48)', () => {
  it('muestra solo los cursos publicados con su literal', async () => {
    const notas: MisNotas = {
      aula: '3.º B',
      bimestre,
      bimestres: [bimestre],
      cursos: [
        { id: 7, asignatura: 'Matemática', docente: 'Luis Paredes', publicado: true, promedio: 16.8, literal: 'A' },
        { id: 8, asignatura: 'Comunicación', docente: 'Carmen Rivas', publicado: false, promedio: null, literal: null },
      ],
      promedio: 16.8,
      anterior: { nombre: 'III Bimestre', promedio: 16.2 },
      ultimas: [{ id: 50, titulo: 'Práctica 6', curso: 'Matemática', nota: 18, fecha: '2026-10-16' }],
    }
    comoRol('estudiante', '/estudiante/notas', { '/estudiante/notas': [200, notas] })

    expect(await screen.findByText('Calificaciones publicadas del IV Bimestre')).toBeInTheDocument()
    expect(screen.getAllByText('16,8').length).toBeGreaterThan(0)
    expect(screen.getByText('+0,6')).toBeInTheDocument()
    expect(screen.getByText('Aún no publicado')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ver detalle de Matemática' })).toHaveAttribute(
      'href',
      '/estudiante/notas?curso=7&bimestre=4',
    )
  })

  it('muestra la próxima clase y las clases de cada día', async () => {
    const horario: MiHorario = {
      aula: '3.º B',
      bloques: [{ inicio: '08:00', fin: '09:30' }],
      clases: [
        {
          dia: 1,
          inicio: '08:00',
          fin: '09:30',
          ambiente: 'Aula 12',
          curso_id: 7,
          curso: 'Matemática',
          docente: 'Luis Paredes',
        },
      ],
      proxima: {
        dia: 1,
        inicio: '08:00',
        fin: '09:30',
        ambiente: 'Aula 12',
        curso_id: 7,
        curso: 'Matemática',
        docente: 'Luis Paredes',
        fecha: '2099-01-05',
      },
    }
    comoRol('estudiante', '/estudiante/horario', { '/estudiante/horario': [200, horario] })

    expect(await screen.findByText('Próxima clase')).toBeInTheDocument()
    expect(screen.getByRole('table', { name: 'Horario semanal de 3.º B' })).toBeInTheDocument()
    await waitFor(() => expect(screen.getAllByText('Matemática').length).toBeGreaterThan(1))
  })
})

describe('validaciones del M9', () => {
  it('nota de 0 a 20 con punto o coma (RN-08)', () => {
    expect(errorNota('15,5')).toBeNull()
    expect(errorNota('20')).toBeNull()
    expect(errorNota('')).toBeNull()
    expect(errorNota('20,5')).toBe('La nota debe estar entre 0 y 20.')
    expect(errorNota('15.555')).toBe('La nota debe ser un número de 0 a 20, con hasta 2 decimales.')
    expect(filtros.nota('1a5,,5')).toBe('15,5')
  })

  it('evaluación: peso entero de 1 a 10 y fechas en orden', () => {
    const base = {
      titulo: 'Práctica 7',
      tipo_evaluacion_id: '2',
      subperiodo_id: '4',
      fecha_asignacion: '2026-10-19',
      fecha_limite: '2026-10-23',
      peso: '2',
      indicaciones: '',
      requiere_entrega: true,
    }
    expect(esquemaEvaluacion.safeParse(base).success).toBe(true)
    expect(esquemaEvaluacion.safeParse({ ...base, peso: '11' }).success).toBe(false)
    expect(esquemaEvaluacion.safeParse({ ...base, fecha_limite: '2026-10-18' }).success).toBe(false)
  })
})
