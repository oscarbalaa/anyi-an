import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import type { DetalleUsuario, FilaUsuario } from '@/lib/usuarios'
import { conSesion, cuerpoEnviado, renderizarEn, simularApi } from '@/pruebas/utilidades'

// M5 - CU-04 y CU-05 en la interfaz

const calendario = { periodo: null, bimestre: null }

const fila = (id: number, nombre: string, extra: Partial<FilaUsuario> = {}): FilaUsuario => ({
  id,
  usuario: `usuario${id}`,
  nombre,
  rol: 'ESTUDIANTE',
  rol_nombre: 'Estudiante',
  detalle: '3.º B',
  estado: 'ACTIVO',
  correo: null,
  debe_cambiar_contrasena: false,
  ultimo_acceso: null,
  ...extra,
})

const detalle = (f: FilaUsuario, extra: Partial<DetalleUsuario> = {}): DetalleUsuario => ({
  ...f,
  documento: '71234567',
  celular: null,
  nombres: f.nombre.split(' ')[0],
  apellidos: f.nombre.split(' ').slice(1).join(' '),
  cargo: null,
  intentos_fallidos: 0,
  bloqueado_hasta: null,
  creado_en: null,
  creado_por: 'Carlos Medina',
  actualizado_en: null,
  actualizado_por: null,
  historial: [],
  ...extra,
})

const bloqueado = fila(7, 'Andrés Mori Lazo', { estado: 'BLOQUEADO', correo: 'andres@ayni.test' })
const activo = fila(8, 'Valeria Quispe Mendoza')
const lista = (datos: FilaUsuario[], extra = {}) => ({
  datos,
  pagina: 1,
  por_pagina: 10,
  total: datos.length,
  ultima_pagina: 1,
  resumen: { ACTIVO: 150, BLOQUEADO: 1 },
  ...extra,
})
// La tabla trae su versión de PC y de celular (el CSS oculta una): se usa la primera
const ver = async (nombre: string) =>
  userEvent.click((await screen.findAllByRole('button', { name: `Ver la cuenta de ${nombre}` }))[0])

const llamadas = (fetch: ReturnType<typeof simularApi>, texto: string) =>
  fetch.mock.calls.filter(([url]) => String(url).includes(texto))

function comoAdmin(respuestas: Parameters<typeof simularApi>[0]) {
  const usuario = conSesion('admin', { id: 1 })
  return simularApi({ '/auth/yo': [200, { usuario }], '/calendario/actual': [200, calendario], ...respuestas })
}

describe('Usuarios: lista (RF-11)', () => {
  it('muestra las cuentas, el resumen y filtra por estado y por texto', async () => {
    const fetch = comoAdmin({ '/admin/usuarios': [200, lista([bloqueado, activo])] })
    renderizarEn('/admin/usuarios')

    expect((await screen.findAllByText('Andrés Mori Lazo'))[0]).toBeInTheDocument()
    expect(screen.getByText('150 activas')).toBeInTheDocument()
    expect(screen.getByText('1 bloqueadas')).toBeInTheDocument()

    await userEvent.selectOptions(screen.getByLabelText('Estado'), 'BLOQUEADO')
    await waitFor(() => expect(llamadas(fetch, 'estado=BLOQUEADO')).toHaveLength(1))

    await userEvent.type(screen.getByLabelText('Buscar'), 'mori')
    // La búsqueda espera a que se deje de escribir: una sola consulta
    await waitFor(() => expect(llamadas(fetch, 'buscar=mori')).toHaveLength(1))
    expect(llamadas(fetch, 'buscar=m&')).toHaveLength(0)
  })

  it('pagina con la API', async () => {
    const fetch = comoAdmin({
      '/admin/usuarios': [200, lista([activo], { total: 25, ultima_pagina: 3 })],
    })
    renderizarEn('/admin/usuarios')

    expect(await screen.findByText('Página 1 de 3')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Página siguiente' }))
    await waitFor(() => expect(llamadas(fetch, 'pagina=2')).toHaveLength(1))
  })

  it('avisa cuando ningún usuario coincide', async () => {
    comoAdmin({ '/admin/usuarios': [200, lista([])] })
    renderizarEn('/admin/usuarios')
    await userEvent.selectOptions(await screen.findByLabelText('Rol'), 'DOCENTE')
    expect(await screen.findByText('Ningún usuario coincide con los filtros.')).toBeInTheDocument()
  })
})

describe('Usuarios: acciones (RF-12, RF-13)', () => {
  it('desbloquea con contraseña temporal y la muestra una sola vez', async () => {
    const fetch = comoAdmin({
      '/admin/usuarios': [200, lista([bloqueado])],
      '/admin/usuarios/7': [200, { usuario: detalle(bloqueado, { intentos_fallidos: 5 }) }],
      'POST /admin/usuarios/7/contrasena-temporal': [
        200,
        {
          mensaje: 'Contraseña temporal generada y enviada por correo.',
          contrasena: 'kmpa4827',
          correo_enviado: true,
          usuario: detalle({ ...bloqueado, estado: 'ACTIVO', debe_cambiar_contrasena: true }),
        },
      ],
    })
    renderizarEn('/admin/usuarios')

    await ver('Andrés Mori Lazo')
    const panel = await screen.findByRole('region', { name: 'Cuenta de Andrés Mori Lazo' })
    expect(within(panel).getByRole('heading', { name: 'Cuenta bloqueada' })).toBeInTheDocument()
    expect(panel).toHaveTextContent('Bloqueada tras 5 intentos fallidos.')

    await userEvent.click(within(panel).getByRole('button', { name: 'Desbloquear y generar contraseña temporal' }))
    const confirmar = await screen.findByRole('dialog', { name: '¿Desbloquear la cuenta?' })
    expect(confirmar).toHaveTextContent('La nueva se enviará a andres@ayni.test.')
    await userEvent.click(within(confirmar).getByRole('button', { name: 'Sí, generar' }))

    const resultado = await screen.findByRole('dialog', { name: 'Contraseña temporal' })
    expect(within(resultado).getByTestId('clave-temporal')).toHaveTextContent('kmpa4827')
    expect(resultado).toHaveTextContent('También se envió a andres@ayni.test.')
    expect(llamadas(fetch, '/contrasena-temporal')).toHaveLength(1)
  })

  it('desactiva una cuenta tras confirmar', async () => {
    const fetch = comoAdmin({
      '/admin/usuarios': [200, lista([activo])],
      '/admin/usuarios/8': [200, { usuario: detalle(activo) }],
      'PATCH /admin/usuarios/8/estado': [
        200,
        {
          mensaje: 'Cuenta desactivada. Sus sesiones se cerraron.',
          usuario: detalle({ ...activo, estado: 'INACTIVO' }),
        },
      ],
    })
    renderizarEn('/admin/usuarios')

    await ver('Valeria Quispe Mendoza')
    const panel = await screen.findByRole('region', { name: /Cuenta de Valeria/ })
    await userEvent.click(within(panel).getByRole('button', { name: 'Desactivar cuenta' }))
    await userEvent.click(await screen.findByRole('button', { name: 'Sí, desactivar' }))

    expect(await screen.findByText('Cuenta desactivada. Sus sesiones se cerraron.')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/admin/usuarios/8/estado')).toEqual({ estado: 'INACTIVO' })
    expect(await within(panel).findByRole('heading', { name: 'Cuenta inactiva' })).toBeInTheDocument()
  })

  it('en la propia cuenta no ofrece acciones', async () => {
    const yo = fila(1, 'Carlos Medina Rojas', { rol: 'ADMIN', rol_nombre: 'Administrador', usuario: 'cmedina' })
    comoAdmin({ '/admin/usuarios': [200, lista([yo])], '/admin/usuarios/1': [200, { usuario: detalle(yo) }] })
    renderizarEn('/admin/usuarios')

    await ver('Carlos Medina Rojas')
    const panel = await screen.findByRole('region', { name: /Cuenta de Carlos/ })
    expect(panel).toHaveTextContent('Es tu cuenta')
    expect(within(panel).queryByRole('button', { name: 'Desactivar cuenta' })).not.toBeInTheDocument()
  })

  it('de un docente solo deja editar el contacto', async () => {
    const docente = fila(9, 'Luis Paredes Aranda', { rol: 'DOCENTE', rol_nombre: 'Docente' })
    const fetch = comoAdmin({
      '/admin/usuarios': [200, lista([docente])],
      '/admin/usuarios/9': [200, { usuario: detalle(docente) }],
      'PUT /admin/usuarios/9': [
        200,
        { mensaje: 'Cambios guardados.', usuario: detalle(docente, { celular: '912345678' }) },
      ],
    })
    renderizarEn('/admin/usuarios')

    await ver('Luis Paredes Aranda')
    await userEvent.click(await screen.findByRole('button', { name: 'Editar datos' }))
    const ventana = await screen.findByRole('dialog', { name: 'Editar datos' })
    expect(within(ventana).queryByLabelText('Nombres')).not.toBeInTheDocument()
    expect(ventana).toHaveTextContent('Los demás datos de docentes se editan en su ficha (Docentes).')

    await userEvent.type(within(ventana).getByLabelText('Celular'), '8123')
    await userEvent.click(within(ventana).getByRole('button', { name: 'Guardar cambios' }))
    expect(await within(ventana).findByText('El celular debe tener 9 dígitos y empezar con 9.')).toBeInTheDocument()

    await userEvent.clear(within(ventana).getByLabelText('Celular'))
    await userEvent.type(within(ventana).getByLabelText('Celular'), '912 345 678')
    await userEvent.click(within(ventana).getByRole('button', { name: 'Guardar cambios' }))
    expect(await screen.findByText('Cambios guardados.')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/admin/usuarios/9')).toEqual({ correo: '', celular: '912345678' })
  })
})

describe('Usuarios: nueva cuenta administrativa (RF-12)', () => {
  const llenar = async (ventana: HTMLElement) => {
    await userEvent.type(within(ventana).getByLabelText('Nombres'), 'Patricia')
    await userEvent.type(within(ventana).getByLabelText('Apellidos'), 'Romero Salazar')
    await userEvent.type(within(ventana).getByLabelText('DNI'), '4567a8912')
    await userEvent.type(within(ventana).getByLabelText('Usuario'), 'promero')
    await userEvent.type(within(ventana).getByLabelText('Cargo'), 'Secretaría académica')
  }

  it('valida antes de enviar y muestra los errores del servidor en cada campo', async () => {
    const fetch = comoAdmin({
      '/admin/usuarios': [200, lista([])],
      'POST /admin/usuarios': [422, { mensaje: 'x', errores: { usuario: ['Ese nombre de usuario ya existe.'] } }],
    })
    renderizarEn('/admin/usuarios')

    await userEvent.click(await screen.findByRole('button', { name: 'Nueva cuenta administrativa' }))
    const ventana = await screen.findByRole('dialog', { name: 'Nueva cuenta administrativa' })
    await userEvent.click(within(ventana).getByRole('button', { name: 'Crear cuenta' }))
    expect(await within(ventana).findByText('Ingresa los nombres.')).toBeInTheDocument()
    expect(within(ventana).getByText('Ingresa el DNI.')).toBeInTheDocument()
    expect(llamadas(fetch, 'POST')).toHaveLength(0)

    await llenar(ventana)
    expect(within(ventana).getByLabelText('DNI')).toHaveValue('45678912')
    await userEvent.type(within(ventana).getByLabelText('Correo (opcional)'), 'patricia@ayni')
    await userEvent.click(within(ventana).getByRole('button', { name: 'Crear cuenta' }))
    expect(await within(ventana).findByText('El correo debe tener el formato usuario@dominio.com.')).toBeInTheDocument()

    await userEvent.clear(within(ventana).getByLabelText('Correo (opcional)'))
    await userEvent.click(within(ventana).getByRole('button', { name: 'Crear cuenta' }))
    expect(await within(ventana).findByText('Ese nombre de usuario ya existe.')).toBeInTheDocument()
  })

  it('al crearla muestra la contraseña temporal', async () => {
    const nueva = fila(30, 'Patricia Romero Salazar', { rol: 'ADMIN', usuario: 'promero' })
    const fetch = comoAdmin({
      '/admin/usuarios': [200, lista([])],
      '/admin/usuarios/30': [200, { usuario: detalle(nueva) }],
      'POST /admin/usuarios': [
        201,
        { mensaje: 'Cuenta creada.', contrasena: 'Tqmz#4821', correo_enviado: false, usuario: detalle(nueva) },
      ],
    })
    renderizarEn('/admin/usuarios')

    await userEvent.click(await screen.findByRole('button', { name: 'Nueva cuenta administrativa' }))
    await llenar(await screen.findByRole('dialog', { name: 'Nueva cuenta administrativa' }))
    await userEvent.click(screen.getByRole('button', { name: 'Crear cuenta' }))

    const resultado = await screen.findByRole('dialog', { name: 'Contraseña temporal' })
    expect(within(resultado).getByTestId('clave-temporal')).toHaveTextContent('Tqmz#4821')
    expect(resultado).toHaveTextContent('La cuenta no tiene correo: entrégasela al usuario.')
    expect(cuerpoEnviado(fetch, '/admin/usuarios')).toMatchObject({ dni: '45678912', usuario: 'promero', correo: '' })
  })
})

describe('Mi perfil (RF-14, RF-15)', () => {
  const perfilEstudiante = {
    id: 5,
    usuario: 'E-2026-001',
    rol: 'ESTUDIANTE',
    nombre: 'Valeria Quispe',
    nombre_completo: 'Valeria Quispe Mendoza',
    iniciales: 'VQ',
    detalle: '3.º B',
    correo: null,
    celular: '987321654',
    documento: 'DNI 78234567',
    estado: 'ACTIVO',
    ultimo_acceso: null,
    codigo: 'E-2026-001',
    aula: '3.º B',
    tutor: 'Carmen Rivas',
  }

  it('el estudiante ve su código, aula y tutor, y edita su contacto', async () => {
    const usuario = conSesion('estudiante', { id: 5 })
    const fetch = simularApi({
      '/auth/yo': [200, { usuario }],
      '/calendario/actual': [200, calendario],
      'GET /perfil': [200, { perfil: perfilEstudiante }],
      'PUT /perfil': [
        200,
        { mensaje: 'Tus datos se actualizaron.', perfil: { ...perfilEstudiante, correo: 'valeria@ayni.test' } },
      ],
    })
    renderizarEn('/estudiante/perfil')

    expect(await screen.findByRole('heading', { name: 'Valeria Quispe Mendoza' })).toBeInTheDocument()
    expect(screen.getByText('Estudiante · 3.º B')).toBeInTheDocument()
    expect(screen.getByText('Carmen Rivas')).toBeInTheDocument()
    expect(screen.getByText('987 321 654')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Cambiar contraseña' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Permisos principales' })).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Editar contacto' }))
    await userEvent.type(screen.getByLabelText('Correo'), 'valeria@ayni')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar cambios' }))
    expect(await screen.findByText('El correo debe tener el formato usuario@dominio.com.')).toBeInTheDocument()

    await userEvent.type(screen.getByLabelText('Correo'), '.test')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar cambios' }))
    expect(await screen.findByText('Tus datos se actualizaron.')).toBeInTheDocument()
    expect(await screen.findByText('valeria@ayni.test')).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/perfil')).toEqual({ correo: 'valeria@ayni.test', celular: '987321654' })
  })

  it('el administrativo ve sus permisos principales', async () => {
    const usuario = conSesion('admin')
    simularApi({
      '/auth/yo': [200, { usuario }],
      '/calendario/actual': [200, calendario],
      '/perfil': [
        200,
        {
          perfil: {
            ...perfilEstudiante,
            rol: 'ADMIN',
            nombre_completo: 'Carlos Medina Rojas',
            cargo: 'Dirección',
            ambito: 'Toda la institución',
            codigo: undefined,
          },
        },
      ],
    })
    renderizarEn('/admin/perfil')

    expect(await screen.findByRole('heading', { name: 'Permisos principales' })).toBeInTheDocument()
    expect(screen.getByText('Toda la institución')).toBeInTheDocument()
  })

  it('cambia la contraseña con los requisitos de su rol', async () => {
    const usuario = conSesion('docente')
    const fetch = simularApi({
      '/auth/yo': [200, { usuario }],
      '/calendario/actual': [200, calendario],
      '/perfil': [200, { perfil: { ...perfilEstudiante, rol: 'DOCENTE', cursos: [] } }],
      'POST /auth/cambiar-contrasena': [200, { mensaje: 'Tu contraseña se actualizó.', usuario }],
    })
    renderizarEn('/docente/perfil')

    await userEvent.type(await screen.findByLabelText('Contraseña actual'), 'Ayni#2026')
    await userEvent.type(screen.getByLabelText('Nueva contraseña'), 'Nueva#2027')
    await userEvent.type(screen.getByLabelText('Repite la nueva contraseña'), 'Nueva#2027')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar contraseña' }))

    expect(await screen.findByText('Tu contraseña se actualizó.')).toBeInTheDocument()
    expect(screen.getByLabelText('Contraseña actual')).toHaveValue('')
    expect(cuerpoEnviado(fetch, '/auth/cambiar-contrasena')).toEqual({
      actual: 'Ayni#2026',
      contrasena: 'Nueva#2027',
      contrasena_confirmation: 'Nueva#2027',
    })
  })
})
