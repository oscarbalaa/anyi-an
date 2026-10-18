import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import type { Aula, Carga, Institucion, Opciones, Periodo } from '@/lib/estructura'
import { conSesion, cuerpoEnviado, renderizarEn, simularApi } from '@/pruebas/utilidades'

// M6 - CU-06, CU-07 y CU-08 en la interfaz

const calendario = { periodo: null, bimestre: null }

const periodo2026: Periodo = {
  id: 1,
  nombre: '2026',
  fecha_inicio: '2026-02-23',
  fecha_fin: '2026-12-04',
  estado: 'EN_CURSO',
  aulas: 12,
  bimestres: [
    {
      id: 1,
      numero: 1,
      nombre: 'I Bimestre',
      fecha_inicio: '2026-02-23',
      fecha_fin: '2026-04-24',
      fecha_cierre_notas: '2026-05-01',
      estado: 'CERRADO',
      cerrado_automatico: true,
      situacion: 'CERRADO',
    },
    {
      id: 2,
      numero: 2,
      nombre: 'II Bimestre',
      fecha_inicio: '2026-05-04',
      fecha_fin: '2026-07-03',
      fecha_cierre_notas: '2026-07-10',
      estado: 'ABIERTO',
      cerrado_automatico: false,
      situacion: 'EN_CURSO',
    },
    { id: null, numero: 3, nombre: 'III Bimestre', situacion: 'SIN_DEFINIR' },
    { id: null, numero: 4, nombre: 'IV Bimestre', situacion: 'SIN_DEFINIR' },
  ],
}
const periodo2027: Periodo = {
  ...periodo2026,
  id: 2,
  nombre: '2027',
  estado: 'PLANIFICADO',
  aulas: 0,
  fecha_inicio: '2027-03-01',
  fecha_fin: '2027-12-17',
  bimestres: periodo2026.bimestres.map((b) => ({
    numero: b.numero,
    nombre: b.nombre,
    id: null,
    situacion: 'SIN_DEFINIR',
  })),
}

const opciones: Opciones = {
  periodo_id: 1,
  periodos: [
    { id: 2, nombre: '2027', estado: 'PLANIFICADO' },
    { id: 1, nombre: '2026', estado: 'EN_CURSO' },
  ],
  grados: [
    { id: 7, nivel: 'SECUNDARIA', nombre: '1.º secundaria' },
    { id: 9, nivel: 'SECUNDARIA', nombre: '3.º secundaria' },
  ],
  docentes: [
    { id: 11, nombre: 'Paredes Aranda, Luis', especialidad: 'Matemática' },
    { id: 12, nombre: 'Rivas Mendoza, Carmen', especialidad: null },
  ],
  asignaturas: [
    { id: 21, codigo: 'MAT', nombre: 'Matemática', horas_semanales: 6 },
    { id: 22, codigo: 'COM', nombre: 'Comunicación', horas_semanales: 5 },
  ],
  aulas: [{ id: 31, nombre: '3.º A', nivel: 'SECUNDARIA' }],
}

const aula3A: Aula = {
  id: 31,
  nombre: '3.º A',
  grado_id: 9,
  grado: 3,
  nivel: 'SECUNDARIA',
  seccion: 'A',
  capacidad: 30,
  ambiente: 'Aula 12',
  matriculados: 27,
  cursos: 1,
  tutor: { id: 11, nombre: 'Luis Paredes' },
  coordinador: { id: 12, nombre: 'Carmen Rivas', ambito: 'NIVEL', propio: false },
}

const carga: Carga = {
  aula: { id: 31, nombre: '3.º A', nivel: 'SECUNDARIA', periodo: '2026', periodo_cerrado: false, ambiente: 'Aula 12' },
  cursos: [
    {
      id: 41,
      asignatura: { id: 21, codigo: 'MAT', nombre: 'Matemática', horas_semanales: 6 },
      docente: { id: 11, nombre: 'Luis Paredes' },
      horas_programadas: 1.5,
    },
  ],
  horario: [{ id: 51, curso_id: 41, dia_semana: 1, hora_inicio: '08:00', hora_fin: '09:30', ambiente: 'Aula 12' }],
}

function comoAdmin(ruta: string, respuestas: Parameters<typeof simularApi>[0]) {
  const usuario = conSesion('admin')
  const fetch = simularApi({
    '/auth/yo': [200, { usuario }],
    '/calendario/actual': [200, calendario],
    '/admin/estructura/opciones': [200, opciones],
    ...respuestas,
  })
  renderizarEn(ruta)
  return fetch
}

const llamadas = (fetch: ReturnType<typeof simularApi>, texto: string, metodo = 'GET') =>
  fetch.mock.calls.filter(([url, o]) => String(url).includes(texto) && (o?.method ?? 'GET') === metodo)

describe('Estructura académica: pestañas', () => {
  it('cambia de pestaña con el teclado y recuerda la pestaña en la dirección', async () => {
    comoAdmin('/admin/estructura', { '/admin/periodos': [200, { periodos: [periodo2026] }] })

    const periodos = await screen.findByRole('tab', { name: 'Periodos' })
    expect(periodos).toHaveAttribute('aria-selected', 'true')
    periodos.focus()
    await userEvent.keyboard('{ArrowRight}')
    expect(screen.getByRole('tab', { name: 'Grados y aulas' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('tab', { name: 'Grados y aulas' })).toHaveFocus()
    await userEvent.keyboard('{End}')
    expect(screen.getByRole('tab', { name: 'Colegio' })).toHaveAttribute('aria-selected', 'true')
  })
})

describe('Periodos (RF-17, RF-18)', () => {
  it('muestra el periodo en curso con sus 4 bimestres y su situación', async () => {
    comoAdmin('/admin/estructura', { '/admin/periodos': [200, { periodos: [periodo2027, periodo2026] }] })

    const panel = await screen.findByRole('region', { name: 'Periodo 2026' })
    expect(within(panel).getByText('Cerrado')).toBeInTheDocument()
    expect(within(panel).getByText('(cerrado automáticamente)')).toBeInTheDocument()
    expect(within(panel).getByText('En curso')).toBeInTheDocument()
    expect(within(panel).getAllByText('Sin definir')).toHaveLength(2)
    expect(within(panel).getByRole('button', { name: 'Cerrar periodo' })).toBeInTheDocument()
  })

  it('valida y crea un periodo', async () => {
    const fetch = comoAdmin('/admin/estructura', {
      '/admin/periodos': [200, { periodos: [periodo2026] }],
      'POST /admin/periodos': [201, { mensaje: 'Periodo creado. Ahora define sus bimestres.', id: 2 }],
    })

    await userEvent.click(await screen.findByRole('button', { name: 'Nuevo periodo' }))
    const v = await screen.findByRole('dialog', { name: 'Nuevo periodo' })
    await userEvent.clear(within(v).getByLabelText('Año'))
    await userEvent.type(within(v).getByLabelText('Año'), '27')
    await userEvent.click(within(v).getByRole('button', { name: 'Crear periodo' }))
    expect(await within(v).findByText('El periodo es un año de 4 dígitos entre 2020 y 2100.')).toBeInTheDocument()

    await userEvent.clear(within(v).getByLabelText('Año'))
    await userEvent.type(within(v).getByLabelText('Año'), '2027')
    await userEvent.type(within(v).getByLabelText('Inicio'), '2027-03-01')
    await userEvent.type(within(v).getByLabelText('Fin'), '2027-02-01')
    await userEvent.click(within(v).getByRole('button', { name: 'Crear periodo' }))
    expect(await within(v).findByText('La fecha de fin debe ser posterior a la de inicio.')).toBeInTheDocument()

    await userEvent.clear(within(v).getByLabelText('Fin'))
    await userEvent.type(within(v).getByLabelText('Fin'), '2027-12-17')
    await userEvent.click(within(v).getByRole('button', { name: 'Crear periodo' }))
    expect(await screen.findByText('Periodo creado. Ahora define sus bimestres.')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/admin/periodos')).toEqual({
      nombre: '2027',
      fecha_inicio: '2027-03-01',
      fecha_fin: '2027-12-17',
    })
  })

  it('define un bimestre: sin fecha de cierre usa la de por defecto y muestra el error RN-02 del servidor', async () => {
    const fetch = comoAdmin('/admin/estructura', {
      '/admin/periodos': [200, { periodos: [periodo2026] }],
      'POST /admin/periodos/1/bimestres': (cuerpo) =>
        cuerpo.fecha_cierre_notas
          ? [
              422,
              {
                mensaje: 'x',
                errores: {
                  fecha_cierre_notas: [
                    'La fecha de cierre de notas no puede ser anterior al inicio del bimestre ni posterior al fin del periodo (RN-02).',
                  ],
                },
              },
            ]
          : [201, { mensaje: 'Bimestre guardado.', id: 3 }],
    })

    const panel = await screen.findByRole('region', { name: 'Periodo 2026' })
    await userEvent.click(within(panel).getAllByRole('button', { name: 'Definir' })[0])
    const v = await screen.findByRole('dialog', { name: 'III Bimestre de 2026' })
    await userEvent.type(within(v).getByLabelText('Inicio de clases'), '2026-07-20')
    await userEvent.type(within(v).getByLabelText('Fin de clases'), '2026-09-18')
    await userEvent.type(within(v).getByLabelText('Cierre de notas'), '2026-12-31')
    await userEvent.click(within(v).getByRole('button', { name: 'Guardar bimestre' }))
    expect(await within(v).findByText(/posterior al fin del periodo \(RN-02\)/)).toBeInTheDocument()

    await userEvent.clear(within(v).getByLabelText('Cierre de notas'))
    await userEvent.click(within(v).getByRole('button', { name: 'Guardar bimestre' }))
    expect(await screen.findByText('Bimestre guardado.')).toBeInTheDocument()
    const enviados = llamadas(fetch, '/bimestres', 'POST').map(([, o]) => JSON.parse(o.body))
    expect(enviados.at(-1)).toEqual({
      numero: 3,
      fecha_inicio: '2026-07-20',
      fecha_fin: '2026-09-18',
      fecha_cierre_notas: null,
    })
  })

  it('inicia un periodo planificado tras confirmar y avisa si ya hay otro en curso (RN-01)', async () => {
    comoAdmin('/admin/estructura', {
      '/admin/periodos': [200, { periodos: [periodo2027, periodo2026] }],
      'PATCH /admin/periodos/2/estado': [
        422,
        { mensaje: 'Ya hay un periodo en curso (2026). Ciérralo antes de iniciar otro (RN-01).' },
      ],
    })

    await userEvent.click((await screen.findAllByRole('button', { name: 'Ver el periodo 2027' }))[0])
    const panel = await screen.findByRole('region', { name: 'Periodo 2027' })
    await userEvent.click(within(panel).getByRole('button', { name: 'Iniciar periodo' }))
    await userEvent.click(await screen.findByRole('button', { name: 'Sí, iniciar' }))
    expect(
      await screen.findByText('Ya hay un periodo en curso (2026). Ciérralo antes de iniciar otro (RN-01).'),
    ).toBeInTheDocument()
  })
})

describe('Grados y aulas (RF-19, RF-20, RF-24)', () => {
  it('lista las aulas con tutor, coordinador heredado y matriculados', async () => {
    comoAdmin('/admin/estructura?pestana=aulas', {
      '/admin/aulas': [
        200,
        {
          aulas: [aula3A],
          coordinaciones: [
            { id: 1, docente: { id: 12, nombre: 'Carmen Rivas' }, ambito: 'NIVEL', alcance: 'Secundaria' },
          ],
        },
      ],
    })

    expect((await screen.findAllByText('3.º A'))[0]).toBeInTheDocument()
    expect(screen.getAllByText('del nivel')[0]).toBeInTheDocument()
    expect(screen.getAllByText('27')[0]).toBeInTheDocument()
    expect(screen.getByText('Secundaria', { selector: 'strong' })).toBeInTheDocument()
  })

  it('crea un aula y muestra el error RN-03 bajo la sección', async () => {
    const fetch = comoAdmin('/admin/estructura?pestana=aulas', {
      '/admin/aulas': [200, { aulas: [], coordinaciones: [] }],
      'POST /admin/aulas': [
        422,
        {
          mensaje: 'x',
          errores: { seccion: ['Este grado tiene sección Única: no puede tener otras secciones (RN-03).'] },
        },
      ],
    })

    await userEvent.click(await screen.findByRole('button', { name: 'Nueva aula' }))
    const v = await screen.findByRole('dialog', { name: 'Nueva aula' })
    await userEvent.selectOptions(within(v).getByLabelText('Sección'), 'B')
    await userEvent.clear(within(v).getByLabelText('Capacidad'))
    await userEvent.type(within(v).getByLabelText('Capacidad'), '7a0')
    await userEvent.click(within(v).getByRole('button', { name: 'Crear aula' }))
    expect(await within(v).findByText('La capacidad es un número entero de 1 a 60.')).toBeInTheDocument()

    await userEvent.clear(within(v).getByLabelText('Capacidad'))
    await userEvent.type(within(v).getByLabelText('Capacidad'), '28')
    await userEvent.selectOptions(within(v).getByLabelText('Docente de aula (tutor)'), '11')
    await userEvent.click(within(v).getByRole('button', { name: 'Crear aula' }))
    expect(
      await within(v).findByText('Este grado tiene sección Única: no puede tener otras secciones (RN-03).'),
    ).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/admin/aulas')).toEqual({
      capacidad: 28,
      tutor_docente_id: 11,
      coordinador_docente_id: null,
      ambiente: null,
      periodo_id: 1,
      grado_id: 7,
      seccion: 'B',
    })
  })
})

describe('Asignaturas y tipos de evaluación (RF-21, RF-22)', () => {
  it('busca, valida y crea con el código en mayúsculas', async () => {
    const fetch = comoAdmin('/admin/estructura?pestana=asignaturas', {
      '/admin/asignaturas': [
        200,
        {
          asignaturas: [
            { id: 21, codigo: 'MAT', nombre: 'Matemática', horas_semanales: 6, estado: 'ACTIVO', cursos: 12 },
          ],
        },
      ],
      '/admin/tipos-evaluacion': [200, { tipos: [{ id: 1, nombre: 'Práctica', estado: 'ACTIVO', evaluaciones: 3 }] }],
      'POST /admin/asignaturas': [201, { mensaje: 'Asignatura creada.', id: 22 }],
    })

    expect((await screen.findAllByText('Matemática'))[0]).toBeInTheDocument()
    expect(screen.getByText('Práctica')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Nueva asignatura' }))
    const v = await screen.findByRole('dialog', { name: 'Nueva asignatura' })
    await userEvent.type(within(v).getByLabelText('Código'), 'raz v!')
    expect(within(v).getByLabelText('Código')).toHaveValue('RAZV')
    await userEvent.type(within(v).getByLabelText('Nombre'), 'C++')
    await userEvent.type(within(v).getByLabelText('Horas por semana'), '40')
    await userEvent.click(within(v).getByRole('button', { name: 'Crear asignatura' }))
    expect(
      await within(v).findByText('El nombre tiene de 3 a 100 caracteres: letras, números y espacios.'),
    ).toBeInTheDocument()
    expect(within(v).getByText('Las horas semanales son un número entero de 1 a 20.')).toBeInTheDocument()

    await userEvent.clear(within(v).getByLabelText('Nombre'))
    await userEvent.type(within(v).getByLabelText('Nombre'), 'Razonamiento Verbal')
    await userEvent.clear(within(v).getByLabelText('Horas por semana'))
    await userEvent.type(within(v).getByLabelText('Horas por semana'), '2')
    await userEvent.click(within(v).getByRole('button', { name: 'Crear asignatura' }))
    expect(await screen.findByText('Asignatura creada.')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/admin/asignaturas')).toEqual({
      codigo: 'RAZV',
      nombre: 'Razonamiento Verbal',
      horas_semanales: 2,
      estado: 'ACTIVO',
    })
  })
})

describe('Carga y horarios (RF-23, RF-25)', () => {
  it('muestra los cursos y la grilla, y lleva el cruce de horario al campo (RN-05)', async () => {
    const fetch = comoAdmin('/admin/estructura?pestana=carga', {
      '/admin/aulas/31/carga': [200, carga],
      'POST /admin/horarios': [
        422,
        {
          mensaje: 'x',
          errores: { hora_inicio: ['Cruce de horario: 3.º A ya tiene Matemática el lunes de 08:00 a 09:30.'] },
        },
      ],
    })

    expect(await screen.findByText('1.5 de 6 h / semana')).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: /Matemática, Lunes de 08:00 a 09:30/ })[0]).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Agregar clase' }))
    const v = await screen.findByRole('dialog', { name: 'Agregar clase' })
    await userEvent.clear(within(v).getByLabelText('Hora de inicio'))
    await userEvent.type(within(v).getByLabelText('Hora de inicio'), '05:00')
    await userEvent.click(within(v).getByRole('button', { name: 'Guardar clase' }))
    expect(await within(v).findByText('La hora debe estar entre 06:00 y 20:00.')).toBeInTheDocument()
    expect(llamadas(fetch, '/admin/horarios', 'POST')).toHaveLength(0)

    await userEvent.clear(within(v).getByLabelText('Hora de inicio'))
    await userEvent.type(within(v).getByLabelText('Hora de inicio'), '08:30')
    await userEvent.clear(within(v).getByLabelText('Hora de fin'))
    await userEvent.type(within(v).getByLabelText('Hora de fin'), '10:00')
    await userEvent.click(within(v).getByRole('button', { name: 'Guardar clase' }))
    expect(
      await within(v).findByText('Cruce de horario: 3.º A ya tiene Matemática el lunes de 08:00 a 09:30.'),
    ).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/admin/horarios')).toEqual({
      curso_id: 41,
      dia_semana: 1,
      hora_inicio: '08:30',
      hora_fin: '10:00',
      ambiente: 'Aula 12',
    })
  })
})

describe('Colegio (RF-26, RN-11)', () => {
  const institucion: Institucion = {
    institucion: {
      nombre: 'IEP El Señor es mi Pastor',
      codigo_modular: null,
      ugel: 'UGEL Santa',
      direccion: 'Nuevo Chimbote, Áncash',
      telefono: null,
      correo: null,
      nota_aprobatoria: '11.00',
      umbral_asistencia_alto: 85,
      umbral_asistencia_medio: 90,
      umbral_promedio_alto: '11.00',
      umbral_promedio_medio: '12.00',
      umbral_pendientes_alto: 3,
      dias_cierre_bimestre: 7,
    },
    escala: [
      { codigo: 'AD', descripcion: 'Logro destacado', nota_minima: '18.00', nota_maxima: '20.00' },
      { codigo: 'A', descripcion: 'Logro esperado', nota_minima: '14.00', nota_maxima: '17.99' },
      { codigo: 'B', descripcion: 'En proceso', nota_minima: '11.00', nota_maxima: '13.99' },
      { codigo: 'C', descripcion: 'En inicio', nota_minima: '0.00', nota_maxima: '10.99' },
    ],
  }

  it('valida umbrales y escala antes de enviar, y guarda', async () => {
    const fetch = comoAdmin('/admin/estructura?pestana=colegio', {
      '/admin/institucion': [200, institucion],
      'PUT /admin/institucion': [200, { mensaje: 'Datos del colegio guardados.', ...institucion }],
    })

    const alto = await screen.findByLabelText('Asistencia para riesgo ALTO (%)')
    await userEvent.clear(alto)
    await userEvent.type(alto, '95')
    await userEvent.clear(screen.getByLabelText('A: desde'))
    await userEvent.type(screen.getByLabelText('A: desde'), '15')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar datos del colegio' }))
    expect(await screen.findByText('El umbral de asistencia ALTO debe ser menor que el MEDIO.')).toBeInTheDocument()
    expect(screen.getByText(/Los rangos deben ir de 0 a 20/)).toBeInTheDocument()
    expect(llamadas(fetch, '/admin/institucion', 'PUT')).toHaveLength(0)

    await userEvent.clear(alto)
    await userEvent.type(alto, '80')
    await userEvent.clear(screen.getByLabelText('A: desde'))
    await userEvent.type(screen.getByLabelText('A: desde'), '14')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar datos del colegio' }))
    expect(await screen.findByText('Datos del colegio guardados.')).toBeInTheDocument()
    const enviado = cuerpoEnviado(fetch, '/admin/institucion')
    expect(enviado).toMatchObject({ umbral_asistencia_alto: '80', codigo_modular: null })
    expect(enviado.escala).toHaveLength(4)
  })
})

describe('aviso de cierre para docentes (RF-18)', () => {
  const conCierreEn = (dias: number | null) => {
    const usuario = conSesion('docente')
    simularApi({
      '/auth/yo': [200, { usuario }],
      '/calendario/actual': [
        200,
        {
          periodo: { id: 1, nombre: '2026', fecha_inicio: '2026-02-23', fecha_fin: '2026-12-04' },
          bimestre: {
            id: 4,
            numero: 4,
            nombre: 'IV Bimestre',
            fecha_inicio: '2026-09-28',
            fecha_fin: '2026-11-27',
            fecha_cierre_notas: '2026-12-04',
            estado: 'ABIERTO',
            dias_para_cierre: dias,
          },
        },
      ],
    })
    renderizarEn('/docente')
  }

  it('avisa cuando faltan 7 días o menos', async () => {
    conCierreEn(3)
    expect(
      await screen.findByText(/El cierre de notas del IV Bimestre es el 4 de diciembre \(faltan 3 días\)\./),
    ).toBeInTheDocument()
  })

  it('dice "es hoy" el último día', async () => {
    conCierreEn(0)
    expect(await screen.findByText(/El cierre de notas del IV Bimestre es hoy\./)).toBeInTheDocument()
  })

  it('no avisa si falta más de una semana', async () => {
    conCierreEn(10)
    await screen.findByTestId('nombre-usuario')
    await waitFor(() => expect(screen.getByText('IV Bimestre')).toBeInTheDocument())
    expect(screen.queryByText(/El cierre de notas/)).not.toBeInTheDocument()
  })
})
