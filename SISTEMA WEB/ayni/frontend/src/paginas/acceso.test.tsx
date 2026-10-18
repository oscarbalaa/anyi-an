import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { token } from '@/lib/sesion'
import { CAPTCHA, conSesion, cuerpoEnviado, renderizarEn, simularApi, usuarioDe } from '@/pruebas/utilidades'

// M4 - CU-01, CU-02 y CU-03 en la interfaz

const calendario = { periodo: null, bimestre: null }
const llamadas = (fetch: ReturnType<typeof simularApi>, ruta: string) =>
  fetch.mock.calls.filter(([url]) => String(url).endsWith(ruta)).length

async function llenarIngreso(usuario: string, clave = 'Ayni#2026', captcha = 'K7MP2') {
  await userEvent.type(await screen.findByRole('textbox', { name: /DNI|Código de estudiante|Usuario/ }), usuario)
  await userEvent.type(screen.getByLabelText('Contraseña'), clave)
  await userEvent.type(screen.getByLabelText('Código de verificación'), captcha)
  await userEvent.click(screen.getByRole('button', { name: 'Ingresar' }))
}

describe('inicio de sesión (RF-03, RF-04)', () => {
  it('envía usuario, contraseña y captcha, guarda el token y entra al panel del rol', async () => {
    const usuario = usuarioDe('docente')
    const fetch = simularApi({
      'POST /captcha': [201, CAPTCHA],
      'POST /auth/ingresar': [200, { token: 'nuevo-token', usuario }],
      '/calendario/actual': [200, calendario],
    })
    renderizarEn('/ingresar/docente')

    expect(await screen.findByTestId('captcha-imagen')).toHaveAttribute('src', CAPTCHA.imagen)
    await llenarIngreso('41234567', 'Ayni#2026', 'k7mp2')

    expect(await screen.findByTestId('nombre-usuario')).toHaveTextContent('Ana Torres')
    expect(cuerpoEnviado(fetch, '/auth/ingresar')).toEqual({
      acceso: 'docente',
      usuario: '41234567',
      contrasena: 'Ayni#2026',
      captcha_id: CAPTCHA.id,
      captcha: 'K7MP2',
    })
    expect(token.leer()).toBe('nuevo-token')
  })

  it('"Recordarme" deja el usuario escrito para la próxima vez', async () => {
    simularApi({
      'POST /captcha': [201, CAPTCHA],
      'POST /auth/ingresar': [200, { token: 't', usuario: usuarioDe('estudiante') }],
      '/calendario/actual': [200, calendario],
    })
    const { unmount } = renderizarEn('/ingresar/estudiante')
    await userEvent.click(await screen.findByLabelText('Recordarme'))
    await llenarIngreso('E-2026-001', 'piki2026')
    await screen.findByTestId('nombre-usuario')
    unmount()
    token.borrar()

    renderizarEn('/ingresar/estudiante')
    expect(await screen.findByLabelText('Código de estudiante')).toHaveValue('E-2026-001')
    expect(screen.getByLabelText('Recordarme')).toBeChecked()
  })

  it('con datos incorrectos avisa los intentos restantes, pide otro captcha y borra la contraseña', async () => {
    const fetch = simularApi({
      'POST /captcha': [201, CAPTCHA],
      'POST /auth/ingresar': [422, { mensaje: 'Usuario o contraseña incorrectos.', intentos_restantes: 2 }],
    })
    renderizarEn('/ingresar/docente')
    await llenarIngreso('41234567', 'Mala#2026')

    const aviso = await screen.findByRole('alert')
    expect(aviso).toHaveTextContent('Usuario o contraseña incorrectos.')
    expect(aviso).toHaveTextContent('Te quedan 2 intentos antes de que la cuenta se bloquee.')
    expect(screen.getByLabelText('Contraseña')).toHaveValue('')
    expect(screen.getByLabelText('Código de verificación')).toHaveValue('')
    await waitFor(() => expect(llamadas(fetch, '/captcha')).toBe(2))
  })

  it('muestra el error del captcha en su campo', async () => {
    simularApi({
      'POST /captcha': [201, CAPTCHA],
      'POST /auth/ingresar': [
        422,
        { mensaje: 'x', errores: { captcha: ['El código de verificación no es correcto o ya venció.'] } },
      ],
    })
    renderizarEn('/ingresar/docente')
    await llenarIngreso('41234567')

    expect(await screen.findByText('El código de verificación no es correcto o ya venció.')).toBeInTheDocument()
    expect(screen.getByLabelText('Código de verificación')).toHaveAttribute('aria-invalid', 'true')
  })

  it('si la cuenta es de otro acceso ofrece ir al ingreso correcto', async () => {
    simularApi({
      'POST /captcha': [201, CAPTCHA],
      'POST /auth/ingresar': [
        403,
        {
          mensaje: 'Esta cuenta es de docente. Ingresa desde el acceso que le corresponde.',
          acceso_correcto: 'docente',
        },
      ],
    })
    renderizarEn('/ingresar/administracion')
    await llenarIngreso('cmedina')

    expect(await screen.findByRole('link', { name: 'Ir al ingreso de docente' })).toHaveAttribute(
      'href',
      '/ingresar/docente',
    )
  })

  it('informa si la cuenta está bloqueada (RF-05)', async () => {
    simularApi({
      'POST /captcha': [201, CAPTCHA],
      'POST /auth/ingresar': [423, { mensaje: 'Tu cuenta está bloqueada por intentos fallidos.', bloqueada: true }],
    })
    renderizarEn('/ingresar/estudiante')
    await llenarIngreso('E-2026-001', 'piki2026')

    expect(await screen.findByRole('alert')).toHaveTextContent('Tu cuenta está bloqueada')
  })

  it('el botón de la imagen pide un captcha nuevo', async () => {
    const fetch = simularApi({ 'POST /captcha': [201, CAPTCHA] })
    renderizarEn('/ingresar/docente')
    await screen.findByTestId('captcha-imagen')

    await userEvent.click(screen.getByRole('button', { name: 'Cambiar la imagen del código' }))
    await waitFor(() => expect(llamadas(fetch, '/captcha')).toBe(2))
  })
})

describe('rutas protegidas (RF-09)', () => {
  it('sin sesión lleva al ingreso del rol y luego vuelve a la pantalla pedida', async () => {
    simularApi({
      'POST /captcha': [201, CAPTCHA],
      'POST /auth/ingresar': [200, { token: 't', usuario: usuarioDe('docente') }],
      '/calendario/actual': [200, calendario],
    })
    const { router } = renderizarEn('/docente/evaluaciones')

    expect(await screen.findByRole('heading', { name: 'Bienvenido de nuevo' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/ingresar/docente')
    await llenarIngreso('41234567')
    expect(await screen.findByRole('heading', { level: 1, name: 'Evaluaciones' })).toBeInTheDocument()
  })

  it('un rol no puede entrar a la sección de otro', async () => {
    const usuario = conSesion('estudiante')
    simularApi({ '/auth/yo': [200, { usuario }] })
    const { router } = renderizarEn('/admin/usuarios')

    expect(await screen.findByRole('heading', { name: 'No tienes permiso para ver esta página' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/sin-permiso')
  })

  it('si el token ya no es válido, lo borra y pide ingresar', async () => {
    conSesion('docente')
    simularApi({ 'POST /captcha': [201, CAPTCHA], '/auth/yo': [401, { mensaje: 'Tu sesión no es válida o expiró.' }] })
    renderizarEn('/docente')

    expect(await screen.findByRole('heading', { name: 'Bienvenido de nuevo' })).toBeInTheDocument()
    expect(token.leer()).toBeNull()
  })

  it('tras 1 hora sin actividad no reutiliza el token guardado (RF-08)', async () => {
    conSesion('docente')
    localStorage.setItem('ayni.actividad', String(Date.now() - 61 * 60 * 1000))
    const fetch = simularApi({ 'POST /captcha': [201, CAPTCHA] })
    renderizarEn('/docente')

    expect(await screen.findByRole('heading', { name: 'Bienvenido de nuevo' })).toBeInTheDocument()
    expect(llamadas(fetch, '/auth/yo')).toBe(0)
    expect(token.leer()).toBeNull()
  })

  it('con sesión, la pantalla de ingreso lleva directo al panel', async () => {
    const usuario = conSesion('admin')
    simularApi({ '/auth/yo': [200, { usuario }], '/calendario/actual': [200, calendario] })
    const { router } = renderizarEn('/ingresar/administracion')

    await screen.findByTestId('nombre-usuario')
    expect(router.state.location.pathname).toBe('/admin')
  })
})

describe('cierre de sesión (RF-08)', () => {
  it('muestra el nombre real y al salir revoca el token y vuelve al ingreso', async () => {
    const usuario = conSesion('docente')
    const fetch = simularApi({
      '/auth/yo': [200, { usuario }],
      '/calendario/actual': [200, calendario],
      'POST /auth/salir': [200, { mensaje: 'Cerraste sesión.' }],
      'POST /captcha': [201, CAPTCHA],
    })
    const { router } = renderizarEn('/docente')

    const cuenta = await screen.findByRole('button', { name: 'Menú de la cuenta' })
    expect(cuenta).toHaveTextContent('AT')
    expect(cuenta).toHaveTextContent('Coordinadora 3.º A')
    await userEvent.click(cuenta)
    await userEvent.click(within(screen.getByRole('menu')).getByRole('menuitem', { name: 'Cerrar sesión' }))

    expect(await screen.findByText('Cerraste sesión. ¡Hasta pronto!')).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/ingresar/docente')
    expect(llamadas(fetch, '/auth/salir')).toBe(1)
    expect(token.leer()).toBeNull()
  })
})

describe('contraseña temporal (RF-07, RN-16)', () => {
  it('obliga a cambiarla antes de entrar y muestra los requisitos del rol', async () => {
    const usuario = conSesion('docente', { debe_cambiar_contrasena: true })
    const fetch = simularApi({
      '/auth/yo': [200, { usuario }],
      '/calendario/actual': [200, calendario],
      'POST /auth/cambiar-contrasena': [
        200,
        { mensaje: 'Tu contraseña se actualizó.', usuario: { ...usuario, debe_cambiar_contrasena: false } },
      ],
    })
    const { router } = renderizarEn('/docente')

    expect(await screen.findByRole('heading', { name: 'Crea tu contraseña' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/cambiar-contrasena')
    const requisitos = screen.getByRole('list', { name: 'Requisitos de la contraseña' })
    expect(within(requisitos).getAllByRole('listitem')).toHaveLength(4)

    await userEvent.type(screen.getByLabelText('Contraseña temporal'), 'Temp#2026')
    await userEvent.type(screen.getByLabelText('Nueva contraseña'), 'nueva2027')
    await userEvent.type(screen.getByLabelText('Repite la nueva contraseña'), 'nueva2027')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar y continuar' }))
    expect(
      await screen.findByText('La nueva contraseña debe contener al menos una mayúscula y una minúscula.'),
    ).toBeInTheDocument()
    expect(llamadas(fetch, '/auth/cambiar-contrasena')).toBe(0)

    await userEvent.clear(screen.getByLabelText('Nueva contraseña'))
    await userEvent.type(screen.getByLabelText('Nueva contraseña'), 'Nueva#2027')
    await userEvent.clear(screen.getByLabelText('Repite la nueva contraseña'))
    await userEvent.type(screen.getByLabelText('Repite la nueva contraseña'), 'Nueva#2027')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar y continuar' }))

    expect(await screen.findByTestId('nombre-usuario')).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/docente')
    expect(cuerpoEnviado(fetch, '/auth/cambiar-contrasena')).toEqual({
      actual: 'Temp#2026',
      contrasena: 'Nueva#2027',
      contrasena_confirmation: 'Nueva#2027',
    })
  })

  it('muestra el error de la contraseña actual que devuelve el servidor', async () => {
    const usuario = conSesion('estudiante', { debe_cambiar_contrasena: true })
    simularApi({
      '/auth/yo': [200, { usuario }],
      'POST /auth/cambiar-contrasena': [
        422,
        { mensaje: 'x', errores: { actual: ['La contraseña actual es incorrecta.'] } },
      ],
    })
    renderizarEn('/cambiar-contrasena')

    await userEvent.type(await screen.findByLabelText('Contraseña temporal'), 'otra1234')
    await userEvent.type(screen.getByLabelText('Nueva contraseña'), 'piki2027')
    await userEvent.type(screen.getByLabelText('Repite la nueva contraseña'), 'piki2027')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar y continuar' }))

    expect(await screen.findByText('La contraseña actual es incorrecta.')).toBeInTheDocument()
  })
})

describe('recuperación de contraseña (RF-06)', () => {
  it('pide el enlace y muestra la misma respuesta exista o no la cuenta', async () => {
    const mensaje = 'Si los datos corresponden a una cuenta con correo registrado, te enviamos un enlace.'
    const fetch = simularApi({ 'POST /captcha': [201, CAPTCHA], 'POST /auth/recuperar': [200, { mensaje }] })
    renderizarEn('/ingresar/docente')

    await userEvent.click(await screen.findByRole('link', { name: '¿Olvidaste tu contraseña?' }))
    await screen.findByRole('heading', { name: 'Recupera tu contraseña' })
    await userEvent.type(screen.getByLabelText('DNI'), '41234567')
    await userEvent.type(screen.getByLabelText('Código de verificación'), 'K7MP2')
    await userEvent.click(screen.getByRole('button', { name: 'Enviar enlace' }))

    expect(await screen.findByRole('heading', { name: 'Revisa tu correo' })).toBeInTheDocument()
    expect(screen.getByText(mensaje)).toBeInTheDocument()
    expect(cuerpoEnviado(fetch, '/auth/recuperar')).toMatchObject({ acceso: 'docente', usuario: '41234567' })
  })

  it('sin token válido en el enlace ofrece pedir otro', async () => {
    renderizarEn('/restablecer?token=corto&acceso=docente')

    expect(await screen.findByText('El enlace no es válido o ya venció. Solicita uno nuevo.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Solicitar otro enlace' })).toHaveAttribute('href', '/recuperar/docente')
  })

  it('restablece la contraseña con el enlace del correo', async () => {
    const tokenCorreo = 'a'.repeat(64)
    const fetch = simularApi({
      'POST /auth/restablecer': [200, { mensaje: 'Tu contraseña se actualizó. Ya puedes ingresar.' }],
    })
    renderizarEn(`/restablecer?token=${tokenCorreo}&acceso=estudiante`)

    await userEvent.type(await screen.findByLabelText('Nueva contraseña'), 'piki2027')
    await userEvent.type(screen.getByLabelText('Repite la nueva contraseña'), 'piki2027')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar contraseña' }))

    expect(await screen.findByText('Tu contraseña se actualizó. Ya puedes ingresar.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ir a ingresar' })).toHaveAttribute('href', '/ingresar/estudiante')
    expect(cuerpoEnviado(fetch, '/auth/restablecer')).toEqual({
      token: tokenCorreo,
      contrasena: 'piki2027',
      contrasena_confirmation: 'piki2027',
    })
  })

  it('si el enlace venció mientras escribía, lo indica', async () => {
    simularApi({
      'POST /auth/restablecer': [
        422,
        { mensaje: 'x', errores: { token: ['El enlace no es válido o ya venció. Solicita uno nuevo.'] } },
      ],
    })
    renderizarEn(`/restablecer?token=${'b'.repeat(64)}&acceso=docente`)

    await userEvent.type(await screen.findByLabelText('Nueva contraseña'), 'Nueva#2027')
    await userEvent.type(screen.getByLabelText('Repite la nueva contraseña'), 'Nueva#2027')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar contraseña' }))

    expect(await screen.findByRole('link', { name: 'Solicitar otro enlace' })).toBeInTheDocument()
  })
})
