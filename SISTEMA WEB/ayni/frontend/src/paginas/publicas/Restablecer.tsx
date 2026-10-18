import { zodResolver } from '@hookform/resolvers/zod'
import { CircleCheck } from 'lucide-react'
import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { Link, useSearchParams } from 'react-router'
import { RequisitosContrasena } from '@/components/RequisitosContrasena'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { enviar, type ErrorApi } from '@/lib/api'
import { esquemaNuevaContrasena, type DatosNuevaContrasena } from '@/lib/validaciones'
import { ROLES, type Rol } from '@/layout/roles'
import { AvisoFormulario, PantallaAcceso } from './PantallaAcceso'

// Enlace del correo: /restablecer?token=...&acceso=docente
export default function Restablecer() {
  const [parametros] = useSearchParams()
  const token = parametros.get('token') ?? ''
  const acceso = parametros.get('acceso')
  const rol: Rol | null = acceso && acceso in ROLES ? (acceso as Rol) : null
  const [listo, setListo] = useState<string | null>(null)
  const [enlaceInvalido, setEnlaceInvalido] = useState(token.length !== 64)
  const {
    register,
    handleSubmit,
    setError,
    control,
    formState: { errors, isSubmitting },
  } = useForm<DatosNuevaContrasena>({
    resolver: zodResolver(esquemaNuevaContrasena(rol)),
    defaultValues: { contrasena: '', confirmacion: '' },
  })
  const nueva = useWatch({ control, name: 'contrasena' })

  const ingreso = rol ? ROLES[rol].ingreso : '/ingresar'
  const recuperar = rol ? ingreso.replace('/ingresar/', '/recuperar/') : '/ingresar'

  const guardar = handleSubmit(async (d) => {
    try {
      const r = await enviar<{ mensaje: string }>('/auth/restablecer', {
        token,
        contrasena: d.contrasena,
        contrasena_confirmation: d.confirmacion,
      })
      setListo(r.mensaje)
    } catch (e) {
      const error = e as ErrorApi
      if (error.errores.contrasena) setError('contrasena', { message: error.errores.contrasena[0] })
      else setEnlaceInvalido(true)
    }
  })

  if (listo) {
    return (
      <PantallaAcceso rol={rol ?? 'docente'} titulo="Contraseña actualizada" volver={null}>
        <div className="flex flex-col items-center gap-4 text-center">
          <CircleCheck className="size-14 text-exito" aria-hidden />
          <p role="status" className="text-lg">
            {listo}
          </p>
          <Button asChild tamano="grande" className="w-full">
            <Link to={ingreso}>Ir a ingresar</Link>
          </Button>
        </div>
      </PantallaAcceso>
    )
  }

  return (
    <PantallaAcceso
      rol={rol ?? 'docente'}
      titulo="Crea una nueva contraseña"
      volver={{ a: ingreso, texto: 'Volver a ingresar' }}
    >
      {enlaceInvalido ? (
        <AvisoFormulario>
          <p className="font-bold text-peligro">El enlace no es válido o ya venció. Solicita uno nuevo.</p>
          <Link to={recuperar} className="mt-2 inline-block font-bold text-acento-texto underline">
            Solicitar otro enlace
          </Link>
        </AvisoFormulario>
      ) : (
        <form onSubmit={guardar} noValidate className="flex flex-col gap-5">
          <Campo
            etiqueta="Nueva contraseña"
            type="password"
            maxLength={64}
            autoComplete="new-password"
            error={errors.contrasena?.message}
            {...register('contrasena')}
          />
          <RequisitosContrasena rol={rol} valor={nueva} />
          <Campo
            etiqueta="Repite la nueva contraseña"
            type="password"
            maxLength={64}
            autoComplete="new-password"
            error={errors.confirmacion?.message}
            {...register('confirmacion')}
          />
          <Button type="submit" tamano="grande" disabled={isSubmitting} className="w-full">
            {isSubmitting ? 'Guardando…' : 'Guardar contraseña'}
          </Button>
        </form>
      )}
    </PantallaAcceso>
  )
}
