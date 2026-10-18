import { zodResolver } from '@hookform/resolvers/zod'
import type { ReactNode } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { z } from 'zod'
import { RequisitosContrasena } from '@/components/RequisitosContrasena'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { enviar, type ErrorApi } from '@/lib/api'
import type { Usuario } from '@/lib/sesion'
import { useSesion } from '@/lib/sesion-contexto'
import { esquemaNuevaContrasena } from '@/lib/validaciones'
import { cn } from '@/lib/utils'

type Props = {
  usuario: Usuario
  etiquetaActual?: string
  textoBoton?: string
  /** Al guardar; la sesión ya quedó actualizada */
  onGuardado?: () => void
  /** "fila": en la pantalla de perfil la nueva y su confirmación van lado a lado */
  disposicion?: 'columna' | 'fila'
  children?: ReactNode
}

/** RF-07 y RF-15: cambiar la contraseña indicando la actual (las demás sesiones se cierran) */
export function FormCambioContrasena({
  usuario,
  etiquetaActual = 'Contraseña actual',
  textoBoton = 'Guardar contraseña',
  onGuardado,
  disposicion = 'columna',
  children,
}: Props) {
  const sesion = useSesion()
  const avisos = useAvisos()
  const esquema = z
    .object({ actual: z.string().min(1, 'Ingresa tu contraseña actual.') })
    .and(esquemaNuevaContrasena(usuario.acceso, usuario.usuario))
    .refine((d) => d.contrasena !== d.actual, {
      path: ['contrasena'],
      message: 'La nueva contraseña debe ser distinta de la actual.',
    })
  type Datos = z.infer<typeof esquema>
  const {
    register,
    handleSubmit,
    setError,
    reset,
    control,
    formState: { errors, isSubmitting },
  } = useForm<Datos>({
    resolver: zodResolver(esquema),
    defaultValues: { actual: '', contrasena: '', confirmacion: '' },
  })
  const nueva = useWatch({ control, name: 'contrasena' })

  const guardar = handleSubmit(async (d) => {
    try {
      const r = await enviar<{ mensaje: string; usuario: Usuario }>('/auth/cambiar-contrasena', {
        actual: d.actual,
        contrasena: d.contrasena,
        contrasena_confirmation: d.confirmacion,
      })
      sesion.actualizar(r.usuario)
      avisos.exito(r.mensaje)
      reset()
      onGuardado?.()
    } catch (e) {
      const error = e as ErrorApi
      if (error.errores.actual) setError('actual', { message: error.errores.actual[0] })
      if (error.errores.contrasena) setError('contrasena', { message: error.errores.contrasena[0] })
      if (!error.errores.actual && !error.errores.contrasena) avisos.error(error.message)
    }
  })

  const fila = disposicion === 'fila'
  return (
    <form onSubmit={guardar} noValidate className="flex flex-col gap-5">
      <Campo
        etiqueta={etiquetaActual}
        type="password"
        maxLength={64}
        autoComplete="current-password"
        error={errors.actual?.message}
        {...register('actual')}
      />
      <div className={cn('flex flex-col gap-5', fila && 'sm:grid sm:grid-cols-2 sm:gap-4')}>
        <Campo
          etiqueta="Nueva contraseña"
          type="password"
          maxLength={64}
          autoComplete="new-password"
          error={errors.contrasena?.message}
          {...register('contrasena')}
        />
        <Campo
          etiqueta="Repite la nueva contraseña"
          type="password"
          maxLength={64}
          autoComplete="new-password"
          error={errors.confirmacion?.message}
          {...register('confirmacion')}
        />
      </div>
      <RequisitosContrasena rol={usuario.acceso} valor={nueva} />
      <Button
        type="submit"
        tamano={fila ? 'normal' : 'grande'}
        disabled={isSubmitting}
        className={fila ? 'self-end' : 'w-full'}
      >
        {isSubmitting ? 'Guardando…' : textoBoton}
      </Button>
      {children}
    </form>
  )
}
