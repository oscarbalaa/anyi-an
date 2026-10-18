import { zodResolver } from '@hookform/resolvers/zod'
import { useState, type ReactNode } from 'react'
import { useForm } from 'react-hook-form'
import { Link, Navigate, useLocation } from 'react-router'
import { Captcha } from '@/components/Captcha'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { Casilla } from '@/components/ui/casilla'
import { enviar, type ErrorApi } from '@/lib/api'
import { useCaptcha } from '@/lib/captcha'
import { recordado, type Usuario } from '@/lib/sesion'
import { useSesion } from '@/lib/sesion-contexto'
import { esquemasIngreso, filtros, type DatosIngreso } from '@/lib/validaciones'
import { ROLES, type Rol } from '@/layout/roles'
import { AvisoFormulario, PantallaAcceso } from './PantallaAcceso'

const CONFIG: Record<
  Rol,
  {
    titulo: string
    subtitulo: string
    campo: {
      etiqueta: string
      placeholder: string
      inputMode?: 'numeric'
      maxLength: number
      filtro: (v: string) => string
    }
  }
> = {
  estudiante: {
    titulo: 'Bienvenido de nuevo',
    subtitulo: 'Ingresa a tu espacio de estudiante',
    campo: {
      etiqueta: 'Código de estudiante',
      placeholder: 'E-2026-001',
      maxLength: 10,
      filtro: filtros.codigoEstudiante,
    },
  },
  docente: {
    titulo: 'Bienvenido de nuevo',
    subtitulo: 'Ingresa a tu espacio docente',
    campo: {
      etiqueta: 'DNI',
      placeholder: 'Ingresa tus 8 dígitos',
      inputMode: 'numeric',
      maxLength: 8,
      filtro: filtros.dni,
    },
  },
  admin: {
    titulo: 'Acceso administrativo',
    subtitulo: 'Ingresa para gestionar la plataforma',
    campo: { etiqueta: 'Usuario', placeholder: 'Ingresa tu usuario', maxLength: 30, filtro: filtros.usuarioAdmin },
  },
}

const CAMPOS = ['usuario', 'contrasena', 'captcha'] as const

export default function Ingreso({ rol }: { rol: Rol }) {
  const c = CONFIG[rol]
  const sesion = useSesion()
  const desde = (useLocation().state as { desde?: string } | null)?.desde
  const captcha = useCaptcha()
  const [aviso, setAviso] = useState<ReactNode>(null)
  const {
    register,
    handleSubmit,
    setError,
    setValue,
    setFocus,
    formState: { errors, isSubmitting },
  } = useForm<DatosIngreso>({
    resolver: zodResolver(esquemasIngreso[rol]),
    defaultValues: {
      usuario: recordado.leer(rol),
      contrasena: '',
      captcha: '',
      recordarme: recordado.leer(rol) !== '',
    },
  })

  // Con sesión iniciada en este acceso pasa al panel (o a la pantalla que pidió)
  if (sesion.usuario?.acceso === rol) {
    const destino = desde?.startsWith(ROLES[rol].base) ? desde : ROLES[rol].base
    return <Navigate to={sesion.usuario.debe_cambiar_contrasena ? '/cambiar-contrasena' : destino} replace />
  }

  const ingresar = handleSubmit(async (d) => {
    setAviso(null)
    try {
      const r = await enviar<{ token: string; usuario: Usuario }>('/auth/ingresar', {
        acceso: rol,
        usuario: d.usuario,
        contrasena: d.contrasena,
        captcha_id: captcha.id,
        captcha: d.captcha,
      })
      recordado.guardar(rol, d.recordarme ? d.usuario : null)
      sesion.iniciar(r.token, r.usuario)
    } catch (e) {
      const error = e as ErrorApi
      // El captcha se usa una sola vez: siempre se pide uno nuevo
      captcha.renovar()
      setValue('captcha', '')
      setValue('contrasena', '')

      const conCampo = CAMPOS.filter((campo) => error.errores?.[campo])
      conCampo.forEach((campo) => setError(campo, { message: error.errores[campo][0] }))
      if (conCampo.length) {
        setFocus(conCampo[0])
        return
      }
      setAviso(<DetalleError error={error} />)
      setFocus('contrasena')
    }
  })

  return (
    <PantallaAcceso rol={rol} titulo={c.titulo} subtitulo={c.subtitulo}>
      <form onSubmit={ingresar} noValidate className="flex flex-col gap-5">
        {aviso && <AvisoFormulario>{aviso}</AvisoFormulario>}
        <Campo
          etiqueta={c.campo.etiqueta}
          placeholder={c.campo.placeholder}
          inputMode={c.campo.inputMode}
          maxLength={c.campo.maxLength}
          autoComplete="username"
          autoCapitalize="off"
          spellCheck={false}
          filtro={c.campo.filtro}
          error={errors.usuario?.message}
          {...register('usuario')}
        />
        <Campo
          etiqueta="Contraseña"
          type="password"
          placeholder="Ingresa tu contraseña"
          maxLength={64}
          autoComplete="current-password"
          error={errors.contrasena?.message}
          {...register('contrasena')}
        />
        <Captcha captcha={captcha} registro={register('captcha')} error={errors.captcha?.message} />
        <div className="flex flex-wrap items-center justify-between gap-3">
          <Casilla etiqueta="Recordarme" {...register('recordarme')} />
          <Link
            to={ROLES[rol].ingreso.replace('/ingresar/', '/recuperar/')}
            className="font-semibold text-acento-texto underline-offset-4 hover:underline"
          >
            ¿Olvidaste tu contraseña?
          </Link>
        </div>
        <Button type="submit" tamano="grande" disabled={isSubmitting} className="w-full">
          {isSubmitting ? 'Ingresando…' : 'Ingresar'}
        </Button>
      </form>
    </PantallaAcceso>
  )
}

function DetalleError({ error }: { error: ErrorApi }) {
  const restantes = error.datos.intentos_restantes as number | undefined
  const correcto = error.datos.acceso_correcto as Rol | undefined
  return (
    <>
      <p className="font-bold text-peligro">{error.message}</p>
      {restantes !== undefined && (
        <p className="mt-1">
          Te {restantes === 1 ? 'queda 1 intento' : `quedan ${restantes} intentos`} antes de que la cuenta se bloquee.
        </p>
      )}
      {correcto && ROLES[correcto] && (
        <Link to={ROLES[correcto].ingreso} className="mt-2 inline-block font-bold text-acento-texto underline">
          Ir al ingreso de {ROLES[correcto].etiqueta.toLowerCase()}
        </Link>
      )}
    </>
  )
}
