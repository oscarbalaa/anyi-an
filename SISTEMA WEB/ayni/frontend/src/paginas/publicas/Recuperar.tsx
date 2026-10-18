import { zodResolver } from '@hookform/resolvers/zod'
import { MailCheck } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Captcha } from '@/components/Captcha'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { enviar, type ErrorApi } from '@/lib/api'
import { useCaptcha } from '@/lib/captcha'
import { esquemaRecuperar, filtros, type DatosRecuperar } from '@/lib/validaciones'
import { ROLES, type Rol } from '@/layout/roles'
import { AvisoFormulario, PantallaAcceso } from './PantallaAcceso'

const CAMPO: Record<
  Rol,
  { etiqueta: string; placeholder: string; inputMode?: 'numeric'; maxLength: number; filtro: (v: string) => string }
> = {
  estudiante: {
    etiqueta: 'Código de estudiante',
    placeholder: 'E-2026-001',
    maxLength: 10,
    filtro: filtros.codigoEstudiante,
  },
  docente: {
    etiqueta: 'DNI',
    placeholder: 'Ingresa tus 8 dígitos',
    inputMode: 'numeric',
    maxLength: 8,
    filtro: filtros.dni,
  },
  admin: { etiqueta: 'Usuario', placeholder: 'Ingresa tu usuario', maxLength: 30, filtro: filtros.usuarioAdmin },
}

// RF-06: la respuesta es la misma exista o no la cuenta
export default function Recuperar({ rol }: { rol: Rol }) {
  const campo = CAMPO[rol]
  const captcha = useCaptcha()
  const [enviado, setEnviado] = useState<string | null>(null)
  const [aviso, setAviso] = useState<string | null>(null)
  const {
    register,
    handleSubmit,
    setError,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<DatosRecuperar>({
    resolver: zodResolver(esquemaRecuperar(rol)),
    defaultValues: { usuario: '', captcha: '' },
  })

  const solicitar = handleSubmit(async (d) => {
    setAviso(null)
    try {
      const r = await enviar<{ mensaje: string }>('/auth/recuperar', {
        acceso: rol,
        usuario: d.usuario,
        captcha_id: captcha.id,
        captcha: d.captcha,
      })
      setEnviado(r.mensaje)
    } catch (e) {
      const error = e as ErrorApi
      captcha.renovar()
      setValue('captcha', '')
      if (error.errores.captcha) setError('captcha', { message: error.errores.captcha[0] })
      else if (error.errores.usuario) setError('usuario', { message: error.errores.usuario[0] })
      else setAviso(error.message)
    }
  })

  const volver = { a: ROLES[rol].ingreso, texto: 'Volver a ingresar' }

  if (enviado) {
    return (
      <PantallaAcceso rol={rol} titulo="Revisa tu correo" volver={volver}>
        <div className="flex flex-col items-center gap-4 text-center">
          <span
            className="grid size-16 place-items-center rounded-full bg-primario-suave text-primario-oscuro"
            aria-hidden
          >
            <MailCheck className="size-8" />
          </span>
          <p role="status" className="text-lg">
            {enviado}
          </p>
          <p className="text-texto-suave">
            El enlace sirve una sola vez y vence en 30 minutos. Revisa también la carpeta de spam.
          </p>
        </div>
      </PantallaAcceso>
    )
  }

  return (
    <PantallaAcceso
      rol={rol}
      titulo="Recupera tu contraseña"
      subtitulo="Te enviaremos un enlace al correo registrado en tu cuenta."
      volver={volver}
    >
      <form onSubmit={solicitar} noValidate className="flex flex-col gap-5">
        {aviso && <AvisoFormulario>{aviso}</AvisoFormulario>}
        <Campo
          etiqueta={campo.etiqueta}
          placeholder={campo.placeholder}
          inputMode={campo.inputMode}
          maxLength={campo.maxLength}
          autoComplete="username"
          autoCapitalize="off"
          spellCheck={false}
          filtro={campo.filtro}
          error={errors.usuario?.message}
          {...register('usuario')}
        />
        <Captcha captcha={captcha} registro={register('captcha')} error={errors.captcha?.message} />
        <p className="text-sm text-texto-suave">
          Si no tienes un correo registrado, pide a {rol === 'estudiante' ? 'tu tutor o a ' : ''}la administración que
          restablezca tu contraseña.
        </p>
        <Button type="submit" tamano="grande" disabled={isSubmitting} className="w-full">
          {isSubmitting ? 'Enviando…' : 'Enviar enlace'}
        </Button>
      </form>
    </PantallaAcceso>
  )
}
