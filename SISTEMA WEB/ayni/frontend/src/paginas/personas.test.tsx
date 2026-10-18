import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import type { Opciones } from '@/lib/estructura'
import type { Docente, Estudiante, FilaDocente, FilaEstudiante, FilaMatricula } from '@/lib/personas'
import { conSesion, cuerpoEnviado, renderizarEn, simularApi } from '@/pruebas/utilidades'

// M7 - CU-09 a CU-12 en la interfaz

const opciones: Opciones = {
  periodo_id: 1,
  periodos: [{ id: 1, nombre: '2026', estado: 'EN_CURSO' }],
  grados: [],
  docentes: [],
  asignaturas: [],
  aulas: [
    { id: 31, nombre: '3.º A', nivel: 'SECUNDARIA' },
    { id: 32, nombre: '3.º B', nivel: 'SECUNDARIA' },
  ],
}

const pagina = <T,>(datos: T[]) => ({ datos, pagina: 1, por_pagina: 10, total: datos.length, ultima_pagina: 1 })

const filaDocente: FilaDocente = {
  id: 5,
  nombre: 'Luis Paredes Aranda',
  documento: 'DNI 41234567',
  especialidad: 'Matemática',
  estado: 'ACTIVO',
  cuenta: 'ACTIVO',
  coordina: 'Coordinador 3.º A',
  cursos: 3,
}

const docente: Docente = {
  ...filaDocente,
  tipo_documento: 'DNI',
  numero_documento: '41234567',
  nombres: 'Luis',
  apellidos: 'Paredes Aranda',
  fecha_nacimiento: '1985-06-15',
  sexo: 'M',
  celular: null,
  correo: 'luis@ayni.test',
  direccion: null,
  titulo: null,
  fecha_inicio_contrato: null,
  fecha_fin_contrato: null,
  usuario: '41234567',
  ultimo_acceso: null,
  tutoria: '5.º A',
  cursos: ['Matemática · 3.º B'],
}

const filaEstudiante: FilaEstudiante = {
  id: 9,
  nombre: 'Valeria Quispe Rojas',
  documento: 'DNI 71234567',
  codigo: 'E-2026-001',
  estado: 'ACTIVO',
  cuenta: 'ACTIVO',
  origen: 'EXCEL',
  registrado_por: 'Importación Excel',
  aula: '3.º A',
}

const estudiante: Estudiante = {
  ...filaEstudiante,
  tipo_documento: 'DNI',
  numero_documento: '71234567',
  nombres: 'Valeria',
  apellidos: 'Quispe Rojas',
  fecha_nacimiento: '2012-04-02',
  sexo: 'F',
  celular: null,
  correo: null,
  direccion: null,
  fecha_ingreso: '2026-02-10',
  usuario: 'E-2026-001',
  ultimo_acceso: null,
  matriculas: [
    { id: 70, periodo: '2026', en_curso: true, aula: '3.º A', fecha: '2026-02-10', estado: 'ACTIVA', motivo: null },
  ],
  apoderados: [
    { id: 80, nombres: 'Rosa Rojas', celular: '987654321', correo: null, parentesco: 'Madre', es_principal: true },
    { id: 81, nombres: 'Juan Quispe', celular: null, correo: null, parentesco: 'Padre', es_principal: false },
  ],
}

const matricula: FilaMatricula = {
  id: 70,
  estudiante: { id: 9, nombre: 'Valeria Quispe Rojas', codigo: 'E-2026-001' },
  aula: '3.º A',
  fecha: '2026-02-10',
  estado: 'ACTIVA',
  motivo: null,
}

function comoAdmin(ruta: string, respuestas: Parameters<typeof simularApi>[0]) {
  const usuario = conSesion('admin')
  const fetch = simularApi({
    '/auth/yo': [200, { usuario }],
    '/calendario/actual': [200, { periodo: null, bimestre: null }],
    '/admin/estructura/opciones': [200, opciones],
    ...respuestas,
  })
  renderizarEn(ruta)
  return fetch
}

const llamadas = (fetch: ReturnType<typeof simularApi>, texto: string, metodo = 'GET') =>
  fetch.mock.calls.filter(([url, o]) => String(url).includes(texto) && (o?.method ?? 'GET') === metodo)

describe('Docentes (RF-27)', () => {
  it('lista con los conteos por estado y filtra', async () => {
    const fetch = comoAdmin('/admin/docentes', {
      '/admin/docentes': [200, { ...pagina([filaDocente]), resumen: { ACTIVO: 15, LICENCIA: 1 } }],
    })

    expect((await screen.findAllByText('Luis Paredes Aranda'))[0]).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Activos (15)' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'De licencia (1)' })).toBeInTheDocument()
    await userEvent.selectOptions(screen.getByLabelText('Estado'), 'LICENCIA')
    await waitFor(() => expect(llamadas(fetch, '/admin/docentes?estado=LICENCIA')).toHaveLength(1))
  })

  it('valida y registra un docente; muestra su usuario y la contraseña temporal', async () => {
    const fetch = comoAdmin('/admin/docentes', {
      '/admin/docentes': [200, { ...pagina([]), resumen: {} }],
      'POST /admin/docentes': [
        201,
        {
          mensaje: 'Docente registrado.',
          docente: { ...docente, id: 6, nombre: 'Ana Torres Vega', usuario: '45678912', correo: null },
          contrasena: 'Kp7#mQ2x',
          correo_enviado: false,
        },
      ],
      '/admin/docentes/6': [200, { docente: { ...docente, id: 6 } }],
    })

    await userEvent.click(await screen.findByRole('button', { name: 'Nuevo docente' }))
    const v = await screen.findByRole('dialog', { name: 'Nuevo docente' })
    await userEvent.click(within(v).getByRole('button', { name: 'Registrar docente' }))
    expect(await within(v).findByText('Ingresa el número de documento.')).toBeInTheDocument()

    await userEvent.type(within(v).getByLabelText('DNI'), '4567891a2')
    expect(within(v).getByLabelText('DNI')).toHaveValue('45678912')
    expect(within(v).getByText(/Se creará el usuario 45678912/)).toBeInTheDocument()
    await userEvent.type(within(v).getByLabelText('Nombres'), 'ana')
    await userEvent.type(within(v).getByLabelText('Apellidos'), 'torres vega')
    await userEvent.type(within(v).getByLabelText('Fecha de nacimiento (opcional)'), '2015-01-01')
    await userEvent.click(within(v).getByRole('button', { name: 'Registrar docente' }))
    expect(await within(v).findByText(/el personal tiene de 18 a 75 años/)).toBeInTheDocument()

    await userEvent.clear(within(v).getByLabelText('Fecha de nacimiento (opcional)'))
    await userEvent.selectOptions(within(v).getByLabelText('¿Coordinará un aula? (opcional)'), '31')
    await userEvent.click(within(v).getByRole('button', { name: 'Registrar docente' }))

    const clave = await screen.findByRole('dialog', { name: 'Contraseña temporal' })
    expect(within(clave).getByTestId('clave-temporal')).toHaveTextContent('Kp7#mQ2x')
    expect(within(clave).getByText('45678912')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/admin/docentes')).toMatchObject({
      tipo_documento: 'DNI',
      numero_documento: '45678912',
      // el servidor los pasa a "Ana" y "Torres Vega"
      nombres: 'ana',
      apellidos: 'torres vega',
      fecha_nacimiento: null,
      correo: null,
      coordina_aula_id: 31,
    })
  })

  it('abre la ficha con su carga y la edita (marcar inactivo)', async () => {
    const fetch = comoAdmin('/admin/docentes', {
      '/admin/docentes': [200, { ...pagina([filaDocente]), resumen: { ACTIVO: 1 } }],
      '/admin/docentes/5': [200, { docente }],
      'PUT /admin/docentes/5': [200, { mensaje: 'Datos del docente guardados.', docente }],
    })

    await userEvent.click((await screen.findAllByRole('button', { name: 'Abrir a Luis Paredes Aranda' }))[0])
    const ficha = await screen.findByRole('dialog', { name: 'Docente' })
    expect(await within(ficha).findByText('Matemática · 3.º B')).toBeInTheDocument()
    expect(within(ficha).getByText('Coordinador 3.º A')).toBeInTheDocument()

    await userEvent.click(within(ficha).getByRole('button', { name: 'Editar datos' }))
    await userEvent.selectOptions(within(ficha).getByLabelText('Estado'), 'INACTIVO')
    await userEvent.click(within(ficha).getByRole('button', { name: 'Guardar cambios' }))
    expect(await screen.findByText('Datos del docente guardados.')).toBeInTheDocument()
    const enviado = JSON.parse(llamadas(fetch, '/admin/docentes/5', 'PUT')[0][1].body)
    expect(enviado).toMatchObject({ estado: 'INACTIVO', numero_documento: '41234567' })
    expect(enviado).not.toHaveProperty('coordina_aula_id')
  })
})

describe('Estudiantes (RF-28, RF-29)', () => {
  it('filtra por aula, incluida "Sin matrícula", y marca los que vienen de Excel', async () => {
    const fetch = comoAdmin('/admin/estudiantes', {
      '/admin/estudiantes': [200, { ...pagina([filaEstudiante]), registrados: 152 }],
    })

    expect(await screen.findByText('152 estudiantes registrados en total.')).toBeInTheDocument()
    expect(screen.getAllByText('Excel')[0]).toBeInTheDocument()
    await userEvent.selectOptions(screen.getByLabelText('Aula'), 'sin')
    await waitFor(() => expect(llamadas(fetch, 'aula=sin')).toHaveLength(1))
    await userEvent.type(screen.getByLabelText('Buscar'), 'quispe')
    await waitFor(() => expect(llamadas(fetch, 'buscar=quispe')).toHaveLength(1))
  })

  it('registra un estudiante con carné de extranjería y lo matricula', async () => {
    const fetch = comoAdmin('/admin/estudiantes', {
      '/admin/estudiantes': [200, { ...pagina([]), registrados: 0 }],
      'POST /admin/estudiantes': [
        201,
        {
          mensaje: 'Estudiante registrado con el código E-2026-153.',
          estudiante: { ...estudiante, id: 20, codigo: 'E-2026-153', nombre: 'Lía Pérez' },
          contrasena: 'p7Kx2m',
          correo_enviado: false,
        },
      ],
      '/admin/estudiantes/20': [200, { estudiante: { ...estudiante, id: 20 } }],
    })

    await userEvent.click(await screen.findByRole('button', { name: 'Registrar estudiante' }))
    const v = await screen.findByRole('dialog', { name: 'Registrar estudiante' })
    await userEvent.click(within(v).getByRole('button', { name: 'Registrar estudiante' }))
    expect(await within(v).findByText('Ingresa la fecha de nacimiento.')).toBeInTheDocument()
    expect(within(v).getByText('Elige el sexo.')).toBeInTheDocument()

    await userEvent.selectOptions(within(v).getByLabelText('Tipo de documento'), 'CE')
    await userEvent.type(within(v).getByLabelText('Número de documento'), 'ab-123456789')
    expect(within(v).getByLabelText('Número de documento')).toHaveValue('AB123456789')
    await userEvent.type(within(v).getByLabelText('Nombres'), 'Lía')
    await userEvent.type(within(v).getByLabelText('Apellidos'), 'Pérez')
    await userEvent.type(within(v).getByLabelText('Fecha de nacimiento'), '2012-04-02')
    await userEvent.selectOptions(within(v).getByLabelText('Sexo'), 'F')
    await userEvent.selectOptions(within(v).getByLabelText('Matricular en (opcional)'), '32')
    await userEvent.click(within(v).getByRole('button', { name: 'Registrar estudiante' }))

    const clave = await screen.findByRole('dialog', { name: 'Contraseña temporal' })
    expect(within(clave).getByText('E-2026-153')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/admin/estudiantes')).toMatchObject({
      tipo_documento: 'CE',
      numero_documento: 'AB123456789',
      sexo: 'F',
      aula_id: 32,
    })
  })

  it('muestra la ficha con matrículas y gestiona apoderados', async () => {
    const fetch = comoAdmin('/admin/estudiantes', {
      '/admin/estudiantes': [200, { ...pagina([filaEstudiante]), registrados: 1 }],
      '/admin/estudiantes/9': [200, { estudiante }],
      'POST /admin/estudiantes/9/apoderados': [201, { mensaje: 'Apoderado agregado.' }],
      'DELETE /admin/apoderados/81': [200, { mensaje: 'Apoderado quitado.' }],
    })

    await userEvent.click((await screen.findAllByRole('button', { name: 'Ver ficha de Valeria Quispe Rojas' }))[0])
    const ficha = await screen.findByRole('dialog', { name: 'Estudiante' })
    expect(await within(ficha).findByText('2026 · 3.º A')).toBeInTheDocument()
    expect(within(ficha).getByText('Principal')).toBeInTheDocument()
    expect(within(ficha).getByText('Importación Excel')).toBeInTheDocument()

    await userEvent.click(within(ficha).getByRole('button', { name: 'Agregar' }))
    const v = await screen.findByRole('dialog', { name: 'Agregar apoderado' })
    expect(within(v).getByLabelText('Es el apoderado principal')).not.toBeChecked()
    await userEvent.click(within(v).getByRole('button', { name: 'Guardar apoderado' }))
    expect(await within(v).findByText('Ingresa el nombre del apoderado.')).toBeInTheDocument()
    await userEvent.type(within(v).getByLabelText('Nombres y apellidos'), 'abuela  carmen rojas')
    await userEvent.selectOptions(within(v).getByLabelText('Parentesco'), 'Abuelo(a)')
    await userEvent.type(within(v).getByLabelText('Celular (opcional)'), '912 345 678')
    await userEvent.click(within(v).getByRole('button', { name: 'Guardar apoderado' }))
    expect(await screen.findByText('Apoderado agregado.')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/admin/estudiantes/9/apoderados')).toEqual({
      nombres: 'abuela carmen rojas',
      parentesco: 'Abuelo(a)',
      celular: '912345678',
      correo: null,
      es_principal: false,
    })

    await userEvent.click(within(ficha).getByRole('button', { name: 'Quitar a Juan Quispe' }))
    await userEvent.click(await screen.findByRole('button', { name: 'Quitar' }))
    expect(await screen.findByText('Apoderado quitado.')).toBeInTheDocument()
  })
})

describe('Importar desde Excel (RF-30)', () => {
  const archivo = () => new File(['xlsx'], 'matricula_3B.xlsx', { type: 'application/vnd.ms-excel' })

  it('revisa el archivo, explica los errores y luego importa y entrega las credenciales', async () => {
    const crear = vi.fn(() => 'blob:x')
    vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: crear, revokeObjectURL: vi.fn() }))
    const fetch = comoAdmin('/admin/estudiantes?pestana=importar', {
      'POST /admin/estudiantes/importar/revisar': [
        200,
        {
          filas: 3,
          validas: 2,
          periodo: '2026',
          errores: [{ fila: 4, mensajes: ['El DNI debe tener 8 dígitos.', 'El sexo es M o F.'] }],
        },
      ],
      'POST /admin/estudiantes/importar': [
        200,
        {
          mensaje: 'Se importaron 2 estudiantes.',
          importados: 2,
          con_error: 1,
          credenciales: [
            { codigo: 'E-2026-153', nombre: 'Ramírez, Diego', contrasena: 'a1b2c3' },
            { codigo: 'E-2026-154', nombre: 'Soto, "Mía"', contrasena: 'd4e5f6' },
          ],
        },
      ],
    })

    expect((await screen.findAllByText('tipo_documento'))[0]).toBeInTheDocument()
    expect(screen.getAllByText('codigo')[0]).toBeInTheDocument()
    await userEvent.upload(screen.getByLabelText('Archivo de Excel'), archivo())

    expect(await screen.findByText('Fila 4')).toBeInTheDocument()
    expect(screen.getByText('El sexo es M o F.')).toBeInTheDocument()
    expect(screen.getByText('Se matricularán en el periodo 2026.')).toBeInTheDocument()
    const enviado = llamadas(fetch, '/importar/revisar', 'POST')[0][1].body as FormData
    expect(enviado.get('matricular')).toBe('1')
    expect((enviado.get('archivo') as File).name).toBe('matricula_3B.xlsx')

    // al quitar "matricular" se vuelve a revisar sin validar aulas
    await userEvent.click(screen.getByLabelText(/Matricular en el periodo en curso/))
    await waitFor(() => expect(llamadas(fetch, '/importar/revisar', 'POST')).toHaveLength(2))
    expect((llamadas(fetch, '/importar/revisar', 'POST')[1][1].body as FormData).get('matricular')).toBe('0')

    await userEvent.click(await screen.findByRole('button', { name: 'Importar 2 estudiantes' }))
    const resultado = await screen.findByRole('region', { name: 'Resultado de la importación' })
    expect(within(resultado).getByText('1 fila tenía errores y no se importó.')).toBeInTheDocument()
    expect(within(resultado).getByText('Se importaron 2 estudiantes')).toBeInTheDocument()
    expect(within(resultado).getAllByText('a1b2c3')[0]).toBeInTheDocument()

    await userEvent.click(within(resultado).getByRole('button', { name: /Descargar credenciales/ }))
    const csv = await (crear.mock.calls[0] as unknown as [Blob])[0].text()
    expect(csv).toContain('codigo,nombre,contrasena_temporal')
    expect(csv).toContain('"E-2026-154","Soto, ""Mía""","d4e5f6"')
    vi.unstubAllGlobals()
  })

  it('muestra el motivo si el archivo no es válido', async () => {
    comoAdmin('/admin/estudiantes?pestana=importar', {
      'POST /admin/estudiantes/importar/revisar': [
        422,
        {
          mensaje: 'x',
          errores: { archivo: ['Al archivo le faltan columnas: sexo, nivel. Usa la plantilla.'] },
        },
      ],
    })

    await userEvent.upload(await screen.findByLabelText('Archivo de Excel'), archivo())
    expect(await screen.findByRole('alert')).toHaveTextContent('Al archivo le faltan columnas: sexo, nivel.')
    expect(screen.queryByRole('button', { name: /^Importar/ })).not.toBeInTheDocument()
  })
})

describe('Matrículas (RF-31, RF-32)', () => {
  const respuestas = {
    '/admin/matriculas': [
      200,
      {
        ...pagina([matricula]),
        aulas: [
          { id: 31, nombre: '3.º A', nivel: 'SECUNDARIA', capacidad: 30, matriculados: 30 },
          { id: 32, nombre: '3.º B', nivel: 'SECUNDARIA', capacidad: 30, matriculados: 12 },
        ],
      },
    ],
  } satisfies Parameters<typeof simularApi>[0]

  it('muestra los cupos y retira una matrícula con su motivo', async () => {
    const fetch = comoAdmin('/admin/estudiantes?pestana=matriculas', {
      ...respuestas,
      'POST /admin/matriculas/70/retirar': [200, { mensaje: 'Matrícula retirada.' }],
    })

    const cupos = await screen.findByRole('list', { name: 'Cupos por aula' })
    expect(within(cupos).getByText(/30 \/ 30/)).toHaveTextContent('lleno')
    await userEvent.click((await screen.findAllByRole('button', { name: 'Retirar a Valeria Quispe Rojas' }))[0])
    const v = await screen.findByRole('dialog', { name: 'Retirar matrícula' })
    await userEvent.type(within(v).getByLabelText('Motivo'), 'corto')
    await userEvent.click(within(v).getByRole('button', { name: 'Retirar' }))
    expect(await within(v).findByText('Explica el motivo en 10 a 300 caracteres.')).toBeInTheDocument()

    await userEvent.type(within(v).getByLabelText('Motivo'), ': traslado a Lima')
    await userEvent.click(within(v).getByRole('button', { name: 'Retirar' }))
    expect(await screen.findByText('Matrícula retirada.')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/matriculas/70/retirar')).toEqual({ motivo: 'corto: traslado a Lima' })
  })

  it('no anula si el servidor indica que ya hay registros académicos', async () => {
    comoAdmin('/admin/estudiantes?pestana=matriculas', {
      ...respuestas,
      'POST /admin/matriculas/70/anular': [
        422,
        { mensaje: 'No se puede anular: ya tiene asistencias o notas registradas. Usa "Retirar".' },
      ],
    })

    await userEvent.click(
      (await screen.findAllByRole('button', { name: 'Anular la matrícula de Valeria Quispe Rojas' }))[0],
    )
    const v = await screen.findByRole('dialog', { name: 'Anular matrícula' })
    await userEvent.type(within(v).getByLabelText('Motivo'), 'Se registró en el aula equivocada')
    await userEvent.click(within(v).getByRole('button', { name: 'Anular matrícula' }))
    expect(await screen.findByText(/No se puede anular: ya tiene asistencias/)).toBeInTheDocument()
  })

  it('matricula a un candidato en un aula con cupos', async () => {
    const fetch = comoAdmin('/admin/estudiantes?pestana=matriculas', {
      ...respuestas,
      '/admin/matriculas/candidatos': [
        200,
        { estudiantes: [{ id: 12, nombre: 'Flores, Mateo', codigo: 'E-2026-002' }] },
      ],
      'POST /admin/matriculas': [201, { mensaje: 'Matrícula registrada.', id: 90 }],
    })

    await userEvent.click(await screen.findByRole('button', { name: 'Nueva matrícula' }))
    const v = await screen.findByRole('dialog', { name: 'Nueva matrícula' })
    await userEvent.click(within(v).getByRole('button', { name: 'Matricular' }))
    expect(await within(v).findByText('Elige un estudiante.')).toBeInTheDocument()
    expect(within(v).getByText('Elige un aula.')).toBeInTheDocument()

    await within(v).findByRole('option', { name: 'Flores, Mateo (E-2026-002)' })
    await userEvent.selectOptions(within(v).getByLabelText('Estudiante sin matrícula en el periodo'), '12')
    await userEvent.selectOptions(within(v).getByLabelText('Aula'), '32')
    expect(within(v).getByRole('option', { name: '3.º A (0 cupos)' })).toBeInTheDocument()
    await userEvent.clear(within(v).getByLabelText('Fecha de matrícula'))
    await userEvent.type(within(v).getByLabelText('Fecha de matrícula'), '2026-03-02')
    await userEvent.click(within(v).getByRole('button', { name: 'Matricular' }))
    expect(await screen.findByText('Matrícula registrada.')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/admin/matriculas')).toEqual({ estudiante_id: 12, aula_id: 32, fecha: '2026-03-02' })
  })
})
