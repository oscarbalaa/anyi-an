import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import type { DetalleAlerta, ListaAdministrativas, ListaAlertas, Panel } from '@/lib/alertas'
import { esquemaIntervencion, esquemaJustificacion } from '@/lib/validaciones'
import type { Rol } from '@/layout/roles'
import { conSesion, cuerpoEnviado, renderizarEn, simularApi } from '@/pruebas/utilidades'

// M11 - CU-24 a CU-26 en la interfaz

const ahora = new Date().toISOString()

const panel: Panel = {
  bimestre: { id: 4, nombre: 'IV Bimestre', estado: 'ABIERTO' },
  bimestres: [{ id: 4, nombre: 'IV Bimestre' }],
  ambito: 'Toda la institución',
  es_coordinador: false,
  modelo: 'reglas v0',
  actualizado: ahora,
  kpis: {
    ALTO: 3,
    MEDIO: 5,
    BAJO: 40,
    evaluados: 48,
    estudiantes: 48,
    sin_atender: 2,
    mas_antigua: ahora,
    abiertas: 8,
  },
  anterior: { bimestre: 'III Bimestre', ALTO: 1, MEDIO: 6 },
  por_aula: [{ id: 1, nombre: '3.º B', nivel: 'SECUNDARIA', ALTO: 2, MEDIO: 3, BAJO: 9 }],
  factores: [{ variable: 'asistencia', titulo: 'Faltas frecuentes', estudiantes: 6, porcentaje: 75 }],
  atencion: [
    {
      matricula_id: 9,
      nombre: 'Diego Ramírez Torres',
      aula: '3.º B',
      nivel: 'ALTO',
      probabilidad: 60,
      factor_principal: 'Asistencia de 78 % (menos de 85 %): 6 faltas en 28 clases.',
      alerta_id: 31,
    },
  ],
  aulas: [{ id: 1, nombre: '3.º B', nivel: 'SECUNDARIA' }],
}

const fila = {
  id: 31,
  estudiante: { matricula_id: 9, nombre: 'Diego Ramírez Torres', codigo: 'E-2026-105' },
  aula: '3.º B',
  bimestre: 'IV Bimestre',
  nivel: 'ALTO',
  probabilidad: 60,
  estado: 'NUEVA',
  factor_principal: 'Asistencia de 78 % (menos de 85 %): 6 faltas en 28 clases.',
  responsable: 'Carmen Rivas',
  a_mi_cargo: true,
  intervenciones: 0,
  creada: ahora,
} as const

const lista: ListaAlertas = {
  datos: [fila],
  pagina: 1,
  por_pagina: 10,
  total: 1,
  ultima_pagina: 1,
  resumen: { abiertas: 1, nuevas: 1, en_seguimiento: 0, sin_intervencion: 1, cerradas: 0 },
  aulas: [{ id: 1, nombre: '3.º B' }],
}

const detalle: DetalleAlerta = {
  alerta: { id: 31, estado: 'NUEVA', creada: ahora, cerrada: null, justificacion: null, bimestre: 'IV Bimestre' },
  estudiante: {
    matricula_id: 9,
    nombre: 'Diego Ramírez Torres',
    codigo: 'E-2026-105',
    aula: '3.º B',
    nivel_educativo: 'Secundaria',
  },
  prediccion: {
    nivel: 'ALTO',
    probabilidad: 60,
    fecha: ahora,
    modelo: 'reglas v0',
    resumen: null,
    factores: [
      {
        variable: 'asistencia',
        titulo: 'Faltas frecuentes',
        valor: '78 %',
        contribucion: 0.5,
        descripcion: 'Asistencia de 78 % (menos de 85 %): 6 faltas en 28 clases.',
      },
    ],
    recomendaciones: [
      { descripcion: 'Contactar al apoderado para conversar sobre las faltas y acordar apoyos.', prioridad: 1 },
    ],
  },
  anterior: { bimestre: 'III Bimestre', nivel: 'BAJO', probabilidad: 10 },
  responsable: { docente_id: 2, nombre: 'Carmen Rivas' },
  permisos: { gestionar: true, reasignar: true },
  transiciones: ['EN_SEGUIMIENTO', 'DESCARTADA'],
  candidatos: [
    { docente_id: 2, nombre: 'Carmen Rivas', rol: 'Tutor(a), Comunicación' },
    { docente_id: 5, nombre: 'Luis Paredes', rol: 'Matemática' },
  ],
  intervenciones: [],
  historial: [
    { accion: 'GENERADA', detalle: 'Riesgo alto (puntaje 60 %).', usuario: 'Sistema', fecha: ahora },
    {
      accion: 'ASIGNADA_DOCENTE_AULA',
      detalle: 'Asignada a Carmen Rivas por ser tutor(a) de 3.º B.',
      usuario: 'Sistema',
      fecha: ahora,
    },
  ],
  tipos_intervencion: ['Reunión con el apoderado', 'Entrevista con el estudiante'],
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

describe('Panel de riesgo (RF-57)', () => {
  it('muestra los niveles con su variación, la distribución, los factores y a quién atender primero', async () => {
    comoRol('admin', '/admin/riesgo', { '/admin/riesgo': [200, panel] })

    expect(await screen.findByRole('heading', { name: 'Panel de riesgo académico', level: 1 })).toBeInTheDocument()
    expect(screen.getByText('+2 respecto al III Bimestre')).toBeInTheDocument()
    expect(screen.getByText('de 48 estudiantes')).toBeInTheDocument()
    expect(
      screen.getByRole('img', { name: '3.º B: 2 en riesgo alto, 3 en riesgo medio y 9 en riesgo bajo' }),
    ).toBeInTheDocument()
    expect(screen.getByText('75 %')).toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: 'Abrir la alerta de Diego Ramírez Torres' })[0]).toHaveAttribute(
      'href',
      '/admin/alertas?id=31',
    )
  })

  it('recalcula con el botón y lo avisa', async () => {
    const fetch = comoRol('admin', '/admin/riesgo', {
      '/admin/riesgo': [200, panel],
      'POST /admin/riesgo/recalcular': [
        200,
        { mensaje: 'Riesgo recalculado para 48 estudiantes: 3 en riesgo alto y 5 en riesgo medio (0 alertas nuevas).' },
      ],
    })
    await userEvent.click(await screen.findByRole('button', { name: 'Recalcular' }))

    expect(await screen.findByText(/Riesgo recalculado para 48 estudiantes/)).toBeInTheDocument()
    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining('/admin/riesgo/recalcular'),
      expect.objectContaining({ method: 'POST' }),
    )
  })

  it('el docente que no coordina ve un aviso en lugar del panel', async () => {
    comoRol('docente', '/docente/coordinacion', {
      '/docente/coordinacion': [200, { bimestre: null, bimestres: [], ambito: null, es_coordinador: false }],
    })
    expect(await screen.findByText('No coordinas ningún aula en este periodo')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Recalcular' })).not.toBeInTheDocument()
  })

  it('el coordinador ve el panel de su ámbito', async () => {
    comoRol('docente', '/docente/coordinacion', {
      '/docente/coordinacion': [200, { ...panel, ambito: '3.º A', es_coordinador: true }],
    })
    expect(await screen.findByRole('heading', { name: 'Panel de coordinación · 3.º A', level: 1 })).toBeInTheDocument()
    expect(screen.getByText(/La coordinación solo muestra las aulas de tu ámbito/)).toBeInTheDocument()
  })
})

describe('Alertas (RF-54, RF-55)', () => {
  it('el docente ve sus alertas asignadas con sus indicadores y puede ver las de sus aulas', async () => {
    const fetch = comoRol('docente', '/docente/alertas', { '/docente/alertas': [200, lista] })

    expect(await screen.findByRole('heading', { name: 'Alertas asignadas', level: 1 })).toBeInTheDocument()
    expect(await screen.findByText('Sin primera intervención')).toBeInTheDocument()
    expect(screen.getAllByText('Alto · 60 %').length).toBeGreaterThan(0)
    expect(screen.getAllByRole('link', { name: 'Abrir la alerta de Diego Ramírez Torres' })[0]).toHaveAttribute(
      'href',
      '/docente/alertas?id=31',
    )

    await userEvent.selectOptions(screen.getByLabelText('Encargado'), 'todas')
    await waitFor(() =>
      expect(fetch).toHaveBeenCalledWith(expect.stringContaining('encargado=todas'), expect.anything()),
    )
  })

  it('la administración tiene también las alertas administrativas con su enlace para resolver', async () => {
    const administrativas: ListaAdministrativas = {
      datos: [
        {
          id: 1,
          tipo: 'USUARIO_BLOQUEADO',
          categoria: 'Acceso',
          severidad: 'CRITICO',
          titulo: 'Cuenta bloqueada: jcastillo',
          detalle: 'Bloqueada por intentos fallidos de ingreso.',
          estado: 'ABIERTA',
          creada: ahora,
          resuelta: null,
          enlace: '/admin/usuarios',
        },
      ],
      conteo: { abiertas: 1, resueltas: 0 },
      tipos: [{ valor: 'USUARIO_BLOQUEADO', texto: 'Cuentas bloqueadas' }],
    }
    comoRol('admin', '/admin/alertas', {
      '/admin/alertas': [200, lista],
      '/admin/alertas-administrativas': [200, administrativas],
    })
    await userEvent.click(await screen.findByRole('tab', { name: /Administrativas/ }))

    expect(await screen.findAllByText('Cuenta bloqueada: jcastillo')).not.toHaveLength(0)
    expect(screen.getAllByRole('link', { name: 'Resolver: Cuenta bloqueada: jcastillo' })[0]).toHaveAttribute(
      'href',
      '/admin/usuarios',
    )
  })

  it('el detalle explica por qué y permite pasar a seguimiento', async () => {
    const fetch = comoRol('docente', '/docente/alertas?id=31', {
      '/docente/alertas/31': [200, detalle],
      'POST /docente/alertas/31/estado': [
        200,
        {
          ...detalle,
          mensaje: 'La alerta pasó a seguimiento.',
          alerta: { ...detalle.alerta, estado: 'EN_SEGUIMIENTO' },
          transiciones: ['CERRADA', 'DESCARTADA'],
        },
      ],
    })

    expect(await screen.findByRole('heading', { name: 'Diego Ramírez Torres', level: 1 })).toBeInTheDocument()
    expect(screen.getByText('Asistencia de 78 % (menos de 85 %): 6 faltas en 28 clases.')).toBeInTheDocument()
    expect(screen.getByText(/En el III Bimestre era bajo \(10 %\)/)).toBeInTheDocument()
    expect(screen.getByText('Prioritaria')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Pasar a seguimiento' }))
    expect(await screen.findByText('La alerta pasó a seguimiento.')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/docente/alertas/31/estado')).toEqual({ estado: 'EN_SEGUIMIENTO' })
    expect(await screen.findByRole('button', { name: 'Cerrar alerta' })).toBeInTheDocument()
  })

  it('registra una intervención validando en la página', async () => {
    const fetch = comoRol('docente', '/docente/alertas?id=31', {
      '/docente/alertas/31': [200, detalle],
      'POST /docente/alertas/31/intervenciones': [201, { ...detalle, mensaje: 'Intervención registrada.' }],
    })
    await userEvent.click(await screen.findByRole('button', { name: 'Registrar intervención' }))
    const v = screen.getByRole('dialog', { name: 'Registrar intervención' })
    await userEvent.click(within(v).getByRole('button', { name: 'Guardar intervención' }))
    expect(await within(v).findByText('Describe la intervención.')).toBeInTheDocument()

    await userEvent.type(within(v).getByLabelText('¿Qué se hizo?'), '  Se llamó   a la madre por las faltas. ')
    await userEvent.click(within(v).getByRole('button', { name: 'Guardar intervención' }))
    expect(await screen.findByText('Intervención registrada.')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/docente/alertas/31/intervenciones')).toMatchObject({
      tipo: 'Reunión con el apoderado',
      descripcion: 'Se llamó a la madre por las faltas.',
      resultado: null,
    })
  })

  it('descartar y cambiar de encargado piden justificación', async () => {
    const fetch = comoRol('admin', '/admin/alertas?id=31', {
      '/admin/alertas/31': [200, detalle],
      'POST /admin/alertas/31/responsable': [200, { ...detalle, mensaje: 'Encargado actualizado.' }],
    })
    await userEvent.click(await screen.findByRole('button', { name: 'Descartar' }))
    const descartar = screen.getByRole('dialog', { name: 'Descartar alerta' })
    await userEvent.click(within(descartar).getByRole('button', { name: 'Descartar alerta' }))
    expect(await within(descartar).findByText('Escribe la justificación.')).toBeInTheDocument()
    await userEvent.click(within(descartar).getByRole('button', { name: 'Cancelar' }))

    await userEvent.click(screen.getByRole('button', { name: 'Cambiar encargado' }))
    const encargado = screen.getByRole('dialog', { name: 'Cambiar encargado' })
    expect(within(encargado).getByLabelText('Docente encargado')).toHaveValue('5')
    await userEvent.type(within(encargado).getByLabelText('Justificación'), 'Dicta el curso con la nota más baja.')
    await userEvent.click(within(encargado).getByRole('button', { name: 'Guardar encargado' }))

    expect(await screen.findByText('Encargado actualizado.')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/admin/alertas/31/responsable')).toEqual({
      docente_id: 5,
      justificacion: 'Dicta el curso con la nota más baja.',
    })
  })

  it('sin permiso no muestra las acciones', async () => {
    comoRol('docente', '/docente/alertas?id=31', {
      '/docente/alertas/31': [200, { ...detalle, permisos: { gestionar: false, reasignar: false }, transiciones: [] }],
    })
    expect(await screen.findByText(/Solo el encargado, el tutor y la coordinación/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Registrar intervención' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Cambiar encargado' })).not.toBeInTheDocument()
  })

  it('los esquemas validan como el servidor', () => {
    expect(
      esquemaIntervencion.safeParse({ tipo: 'Otro', descripcion: 'corto', fecha: '2026-10-19', resultado: '' }).success,
    ).toBe(false)
    expect(
      esquemaIntervencion.safeParse({
        tipo: 'Otro',
        descripcion: 'Se conversó con él.',
        fecha: '2026-10-19',
        resultado: '',
      }).success,
    ).toBe(true)
    expect(esquemaJustificacion.safeParse({ justificacion: 'x'.repeat(401) }).success).toBe(false)
  })
})
