import { zodResolver } from '@hookform/resolvers/zod'
import { Info } from 'lucide-react'
import { useForm, useWatch } from 'react-hook-form'
import { CamposPersona } from '@/components/CamposPersona'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { Selector } from '@/components/ui/selector'
import { useOpciones } from '@/lib/estructura'
import { CAMPOS_PERSONA, marcarErrores, personaParaApi } from '@/lib/formularios'
import { useCambioPersonas, type Estudiante, type RespuestaCambio } from '@/lib/personas'
import { esquemaEstudiante, type DatosEstudiante } from '@/lib/validaciones'

type Props = {
  estudiante: Estudiante | null
  onListo: () => void
  onCreado?: (r: RespuestaCambio) => void
}

const ESTADOS = [
  { valor: 'ACTIVO', texto: 'Activo' },
  { valor: 'RETIRADO', texto: 'Retirado' },
  { valor: 'TRASLADADO', texto: 'Trasladado' },
  { valor: 'EGRESADO', texto: 'Egresado' },
]

// RF-28: el código E-AAAA-NNN (y su usuario) lo genera el sistema (RN-22)
export function FormEstudiante({ estudiante, onListo, onCreado }: Props) {
  const cambio = useCambioPersonas()
  const avisos = useAvisos()
  const { data: opciones } = useOpciones()
  const {
    register,
    handleSubmit,
    setError,
    control,
    formState: { errors, isSubmitting },
  } = useForm<DatosEstudiante>({
    resolver: zodResolver(esquemaEstudiante),
    defaultValues: {
      tipo_documento: estudiante?.tipo_documento ?? 'DNI',
      numero_documento: estudiante?.numero_documento ?? '',
      nombres: estudiante?.nombres ?? '',
      apellidos: estudiante?.apellidos ?? '',
      fecha_nacimiento: estudiante?.fecha_nacimiento ?? '',
      sexo: estudiante?.sexo ?? '',
      correo: estudiante?.correo ?? '',
      celular: estudiante?.celular ?? '',
      direccion: estudiante?.direccion ?? '',
      aula_id: '',
      estado: estudiante?.estado ?? 'ACTIVO',
      fecha_ingreso: estudiante?.fecha_ingreso ?? '',
    },
  })
  const tipo = useWatch({ control, name: 'tipo_documento' })

  const guardar = handleSubmit(async (d) => {
    const datos = {
      ...personaParaApi(d as Record<string, string>),
      ...(estudiante
        ? { estado: d.estado, fecha_ingreso: d.fecha_ingreso }
        : { aula_id: d.aula_id ? Number(d.aula_id) : null }),
    }
    try {
      const r = await cambio.mutateAsync(
        estudiante
          ? { metodo: 'PUT', ruta: `/estudiantes/${estudiante.id}`, datos }
          : { metodo: 'POST', ruta: '/estudiantes', datos },
      )
      if (estudiante) {
        avisos.exito(r.mensaje)
        onListo()
      } else {
        onCreado?.(r)
      }
    } catch (e) {
      const mensaje = marcarErrores(e, setError, [...CAMPOS_PERSONA, 'aula_id', 'estado', 'fecha_ingreso'])
      if (mensaje) avisos.error(mensaje)
    }
  })

  return (
    <form onSubmit={guardar} noValidate className="flex flex-col gap-4">
      <CamposPersona registrar={(c) => register(c)} errores={errors} estudiante tipo={tipo} />
      {estudiante ? (
        <div className="grid gap-4 sm:grid-cols-2">
          <Selector etiqueta="Estado" opciones={ESTADOS} {...register('estado')} />
          <Campo
            etiqueta="Fecha de ingreso"
            type="date"
            error={errors.fecha_ingreso?.message}
            {...register('fecha_ingreso')}
          />
        </div>
      ) : (
        <div className="flex flex-col gap-1.5">
          <Selector
            etiqueta="Matricular en (opcional)"
            opciones={[
              { valor: '', texto: 'Solo registrar, sin matrícula' },
              ...(opciones?.aulas ?? []).map((a) => ({ valor: String(a.id), texto: a.nombre })),
            ]}
            {...register('aula_id')}
          />
          {errors.aula_id && (
            <p role="alert" className="text-sm font-semibold text-peligro">
              {errors.aula_id.message}
            </p>
          )}
        </div>
      )}
      <p className="flex gap-2 text-sm text-texto-suave">
        <Info className="size-5 shrink-0" aria-hidden />
        {estudiante
          ? 'Si el estado deja de ser activo, su cuenta se desactiva.'
          : 'El código del estudiante se genera solo y será su usuario, con una contraseña temporal.'}
      </p>
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variante="secundario" onClick={onListo}>
          Cancelar
        </Button>
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Guardando…' : estudiante ? 'Guardar cambios' : 'Registrar estudiante'}
        </Button>
      </div>
    </form>
  )
}
