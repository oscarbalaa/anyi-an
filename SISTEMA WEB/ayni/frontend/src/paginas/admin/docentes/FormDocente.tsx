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
import { useCambioPersonas, type Docente, type RespuestaCambio } from '@/lib/personas'
import { esquemaDocente, type DatosDocente } from '@/lib/validaciones'

type Props = {
  docente: Docente | null
  onListo: () => void
  /** Al crear: la respuesta trae la contraseña temporal para mostrarla */
  onCreado?: (r: RespuestaCambio) => void
}

const ESTADOS = [
  { valor: 'ACTIVO', texto: 'Activo' },
  { valor: 'LICENCIA', texto: 'De licencia' },
  { valor: 'INACTIVO', texto: 'Inactivo (ya no trabaja aquí)' },
]

// RF-27: el usuario del docente es su DNI (D5)
export function FormDocente({ docente, onListo, onCreado }: Props) {
  const cambio = useCambioPersonas()
  const avisos = useAvisos()
  const { data: opciones } = useOpciones()
  const {
    register,
    handleSubmit,
    setError,
    control,
    formState: { errors, isSubmitting },
  } = useForm<DatosDocente>({
    resolver: zodResolver(esquemaDocente),
    defaultValues: {
      tipo_documento: 'DNI',
      numero_documento: docente?.numero_documento ?? '',
      nombres: docente?.nombres ?? '',
      apellidos: docente?.apellidos ?? '',
      fecha_nacimiento: docente?.fecha_nacimiento ?? '',
      sexo: docente?.sexo ?? '',
      correo: docente?.correo ?? '',
      celular: docente?.celular ?? '',
      direccion: docente?.direccion ?? '',
      titulo: docente?.titulo ?? '',
      especialidad: docente?.especialidad ?? '',
      fecha_inicio_contrato: docente?.fecha_inicio_contrato ?? '',
      fecha_fin_contrato: docente?.fecha_fin_contrato ?? '',
      estado: docente?.estado ?? 'ACTIVO',
      coordina_aula_id: '',
    },
  })
  const dni = useWatch({ control, name: 'numero_documento' })

  const guardar = handleSubmit(async (d) => {
    const nulo = (v: string) => (v === '' ? null : v)
    const datos = {
      ...personaParaApi(d as Record<string, string>),
      titulo: nulo(d.titulo),
      especialidad: nulo(d.especialidad),
      fecha_inicio_contrato: nulo(d.fecha_inicio_contrato),
      fecha_fin_contrato: nulo(d.fecha_fin_contrato),
      ...(docente
        ? { estado: d.estado }
        : { coordina_aula_id: d.coordina_aula_id ? Number(d.coordina_aula_id) : null }),
    }
    try {
      const r = await cambio.mutateAsync(
        docente
          ? { metodo: 'PUT', ruta: `/docentes/${docente.id}`, datos }
          : { metodo: 'POST', ruta: '/docentes', datos },
      )
      if (docente) {
        avisos.exito(r.mensaje)
        onListo()
      } else {
        onCreado?.(r)
      }
    } catch (e) {
      const mensaje = marcarErrores(e, setError, [
        ...CAMPOS_PERSONA,
        'titulo',
        'especialidad',
        'fecha_inicio_contrato',
        'fecha_fin_contrato',
        'estado',
        'coordina_aula_id',
      ])
      if (mensaje) avisos.error(mensaje)
    }
  })

  return (
    <form onSubmit={guardar} noValidate className="flex flex-col gap-4">
      <CamposPersona registrar={(c) => register(c)} errores={errors} estudiante={false} tipo="DNI" />
      <div className="grid gap-4 sm:grid-cols-2">
        <Campo
          etiqueta="Especialidad (opcional)"
          placeholder="Matemática"
          maxLength={100}
          error={errors.especialidad?.message}
          {...register('especialidad')}
        />
        <Campo
          etiqueta="Título (opcional)"
          placeholder="Lic. en Educación"
          maxLength={120}
          error={errors.titulo?.message}
          {...register('titulo')}
        />
        <Campo
          etiqueta="Inicio del contrato (opcional)"
          type="date"
          error={errors.fecha_inicio_contrato?.message}
          {...register('fecha_inicio_contrato')}
        />
        <Campo
          etiqueta="Fin del contrato (opcional)"
          type="date"
          error={errors.fecha_fin_contrato?.message}
          {...register('fecha_fin_contrato')}
        />
      </div>
      {docente ? (
        <Selector etiqueta="Estado" opciones={ESTADOS} {...register('estado')} />
      ) : (
        <Selector
          etiqueta="¿Coordinará un aula? (opcional)"
          opciones={[
            { valor: '', texto: 'No' },
            ...(opciones?.aulas ?? []).map((a) => ({ valor: String(a.id), texto: `Sí, ${a.nombre}` })),
          ]}
          {...register('coordina_aula_id')}
        />
      )}
      {errors.coordina_aula_id && (
        <p role="alert" className="-mt-2 text-sm font-semibold text-peligro">
          {errors.coordina_aula_id.message}
        </p>
      )}
      <p className="flex gap-2 text-sm text-texto-suave">
        <Info className="size-5 shrink-0" aria-hidden />
        {docente
          ? 'Si corriges el DNI, también cambia su usuario. Si lo marcas como inactivo, ya no podrá ingresar.'
          : `Se creará el usuario ${dni || '(su DNI)'} con una contraseña temporal; deberá cambiarla al ingresar.`}
      </p>
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variante="secundario" onClick={onListo}>
          Cancelar
        </Button>
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Guardando…' : docente ? 'Guardar cambios' : 'Registrar docente'}
        </Button>
      </div>
    </form>
  )
}
