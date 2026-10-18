import { zodResolver } from '@hookform/resolvers/zod'
import { Info } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { marcarErrores } from '@/lib/formularios'
import { useGuardarUsuario, type DetalleUsuario, type ResultadoTemporal } from '@/lib/usuarios'
import {
  esquemaContacto,
  esquemaUsuarioAdmin,
  filtros,
  type DatosContacto,
  type DatosUsuarioAdmin,
} from '@/lib/validaciones'
import { ResultadoClave } from './DetalleCuenta'

type Props = { usuario: DetalleUsuario | null; onListo: (id?: number) => void }

/** Alta de cuentas administrativas y edición de cualquier cuenta (RF-12) */
export function FormularioUsuario({ usuario, onListo }: Props) {
  // De docentes y estudiantes aquí solo se edita el contacto: sus datos personales están en el Módulo 7
  return usuario === null || usuario.rol === 'ADMIN' ? (
    <FormAdministrativo usuario={usuario} onListo={onListo} />
  ) : (
    <FormContacto usuario={usuario} onListo={onListo} />
  )
}

function FormAdministrativo({ usuario, onListo }: { usuario: DetalleUsuario | null; onListo: Props['onListo'] }) {
  const guardar = useGuardarUsuario()
  const avisos = useAvisos()
  const [resultado, setResultado] = useState<ResultadoTemporal | null>(null)
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<DatosUsuarioAdmin>({
    resolver: zodResolver(esquemaUsuarioAdmin),
    defaultValues: {
      nombres: usuario?.nombres ?? '',
      apellidos: usuario?.apellidos ?? '',
      dni: usuario?.documento ?? '',
      usuario: usuario?.usuario ?? '',
      cargo: usuario?.cargo ?? '',
      correo: usuario?.correo ?? '',
      celular: usuario?.celular ?? '',
    },
  })

  const enviar = handleSubmit(async (d) => {
    try {
      const r = await guardar.mutateAsync({ id: usuario?.id ?? null, datos: d as Record<string, string> })
      if ('contrasena' in r) {
        setResultado(r as ResultadoTemporal)
      } else {
        avisos.exito(r.mensaje)
        onListo(r.usuario.id)
      }
    } catch (e) {
      const mensaje = marcarErrores(e, setError, [
        'nombres',
        'apellidos',
        'dni',
        'usuario',
        'cargo',
        'correo',
        'celular',
      ])
      if (mensaje) avisos.error(mensaje)
    }
  })

  return (
    <>
      <form onSubmit={enviar} noValidate className="flex flex-col gap-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <Campo
            etiqueta="Nombres"
            maxLength={100}
            autoComplete="off"
            filtro={filtros.nombre}
            error={errors.nombres?.message}
            {...register('nombres')}
          />
          <Campo
            etiqueta="Apellidos"
            maxLength={100}
            autoComplete="off"
            filtro={filtros.nombre}
            error={errors.apellidos?.message}
            {...register('apellidos')}
          />
          <Campo
            etiqueta="DNI"
            inputMode="numeric"
            maxLength={8}
            autoComplete="off"
            filtro={filtros.dni}
            error={errors.dni?.message}
            {...register('dni')}
          />
          <Campo
            etiqueta="Usuario"
            maxLength={30}
            autoComplete="off"
            autoCapitalize="off"
            spellCheck={false}
            filtro={filtros.usuarioAdmin}
            ayuda="Con este nombre ingresará al sistema."
            error={errors.usuario?.message}
            {...register('usuario')}
          />
          <Campo
            etiqueta="Cargo"
            maxLength={80}
            autoComplete="off"
            placeholder="Secretaría académica"
            error={errors.cargo?.message}
            className="sm:col-span-2"
            {...register('cargo')}
          />
          <Campo
            etiqueta="Correo (opcional)"
            type="email"
            inputMode="email"
            maxLength={120}
            autoComplete="off"
            error={errors.correo?.message}
            {...register('correo')}
          />
          <Campo
            etiqueta="Celular (opcional)"
            inputMode="numeric"
            maxLength={9}
            autoComplete="off"
            filtro={filtros.celular}
            error={errors.celular?.message}
            {...register('celular')}
          />
        </div>
        {!usuario && (
          <p className="flex gap-2 text-sm text-texto-suave">
            <Info className="size-5 shrink-0" aria-hidden /> Se generará una contraseña temporal. Si registras un
            correo, también se enviará allí.
          </p>
        )}
        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button variante="secundario" onClick={() => onListo()}>
            Cancelar
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'Guardando…' : usuario ? 'Guardar cambios' : 'Crear cuenta'}
          </Button>
        </div>
      </form>
      <ResultadoClave
        resultado={resultado}
        onCerrar={() => {
          const id = resultado?.usuario.id
          setResultado(null)
          avisos.exito('Cuenta creada.')
          onListo(id)
        }}
      />
    </>
  )
}

function FormContacto({ usuario, onListo }: { usuario: DetalleUsuario; onListo: Props['onListo'] }) {
  const guardar = useGuardarUsuario()
  const avisos = useAvisos()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<DatosContacto>({
    resolver: zodResolver(esquemaContacto),
    defaultValues: { correo: usuario.correo ?? '', celular: usuario.celular ?? '' },
  })

  const enviar = handleSubmit(async (d) => {
    try {
      const r = await guardar.mutateAsync({ id: usuario.id, datos: d })
      avisos.exito(r.mensaje)
      onListo(usuario.id)
    } catch (e) {
      const mensaje = marcarErrores(e, setError, ['correo', 'celular'])
      if (mensaje) avisos.error(mensaje)
    }
  })

  return (
    <form onSubmit={enviar} noValidate className="flex flex-col gap-4">
      <p className="flex gap-2 rounded-xl bg-info/8 p-3 text-sm">
        <Info className="size-5 shrink-0 text-info" aria-hidden />
        Los demás datos de {usuario.rol === 'DOCENTE' ? 'docentes' : 'estudiantes'} se editan en su ficha (
        {usuario.rol === 'DOCENTE' ? 'Docentes' : 'Estudiantes'}).
      </p>
      <Campo
        etiqueta="Correo"
        type="email"
        inputMode="email"
        maxLength={120}
        autoComplete="off"
        ayuda="Sirve para recuperar la contraseña y recibir la temporal."
        error={errors.correo?.message}
        {...register('correo')}
      />
      <Campo
        etiqueta="Celular"
        inputMode="numeric"
        maxLength={9}
        autoComplete="off"
        filtro={filtros.celular}
        error={errors.celular?.message}
        {...register('celular')}
      />
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variante="secundario" onClick={() => onListo()}>
          Cancelar
        </Button>
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Guardando…' : 'Guardar cambios'}
        </Button>
      </div>
    </form>
  )
}
