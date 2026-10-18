import { zodResolver } from '@hookform/resolvers/zod'
import { Info, Lock, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useForm, useWatch, type UseFormRegister } from 'react-hook-form'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { Card } from '@/components/ui/card'
import { Ventana } from '@/components/ui/dialogo'
import { Insignia } from '@/components/ui/insignia'
import type { ErrorApi } from '@/lib/api'
import {
  ESTADOS,
  useEliminarClase,
  useGuardarClase,
  type Clase,
  type EstadoAsistencia,
  type FilaClase,
  type RolAsistencia,
} from '@/lib/asistencia'
import { cn } from '@/lib/utils'
import { esquemaAsistencia, esquemaMotivo, type DatosAsistencia } from '@/lib/validaciones'

const COLOR: Record<EstadoAsistencia, string> = {
  P: 'peer-checked:border-exito peer-checked:bg-exito',
  F: 'peer-checked:border-peligro peer-checked:bg-peligro',
  T: 'peer-checked:border-aviso peer-checked:bg-aviso',
  J: 'peer-checked:border-info peer-checked:bg-info',
}

const ESTADO_CLASE = {
  SIN_REGISTRO: { texto: 'Sin registrar', tono: 'neutro' },
  BORRADOR: { texto: 'Borrador', tono: 'aviso' },
  DEFINITIVO: { texto: 'Registrada', tono: 'exito' },
} as const

const plural = (n: number, uno: string, varios: string) => `${n} ${n === 1 ? uno : varios}`

/** Lista de la clase con P/F/T/J por estudiante (CU-13; el administrador corrige con CU-14) */
export function RegistroClase({ clase, rol }: { clase: Clase; rol: RolAsistencia }) {
  const avisos = useAvisos()
  const guardar = useGuardarClase(rol, clase.curso.id, clase.fecha)
  const [eliminar, setEliminar] = useState(false)
  const { editable, estado } = clase.sesion
  const {
    register,
    handleSubmit,
    setValue,
    setError,
    control,
    formState: { errors },
  } = useForm<DatosAsistencia>({
    resolver: zodResolver(esquemaAsistencia),
    defaultValues: {
      tema: clase.sesion.tema ?? '',
      registros: clase.estudiantes.map((e) => ({
        matricula_id: e.matricula_id,
        estado: e.estado,
        observacion: e.observacion ?? '',
      })),
    },
  })
  const marcados = useWatch({ control, name: 'registros' })
  const conteo = { P: 0, F: 0, T: 0, J: 0 }
  for (const r of marcados) conteo[r.estado as EstadoAsistencia]++

  const enviar = (tipo: 'BORRADOR' | 'DEFINITIVO') =>
    handleSubmit(async (d) => {
      try {
        const r = await guardar.mutateAsync({
          estado: tipo,
          tema: d.tema || null,
          registros: d.registros.map((x) => ({ ...x, observacion: x.observacion || null })),
        })
        avisos.exito(r.mensaje)
      } catch (e) {
        const error = e as ErrorApi
        const porCampo = Object.entries(error.errores ?? {}).filter(([c]) => c.startsWith('registros.'))
        for (const [campo, mensajes] of porCampo)
          setError(campo as `registros.${number}.observacion`, { message: mensajes[0] })
        if (!porCampo.length) avisos.error(error.message)
      }
    })

  const todosPresentes = () => marcados.forEach((_, i) => setValue(`registros.${i}.estado`, 'P', { shouldDirty: true }))

  return (
    <Card className="flex flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap items-center gap-2">
          <Insignia tono={ESTADO_CLASE[estado].tono} punto>
            {ESTADO_CLASE[estado].texto}
          </Insignia>
          {clase.sesion.registrado_por && (
            <span className="text-sm text-texto-suave">por {clase.sesion.registrado_por}</span>
          )}
        </div>
        <p className="text-lg font-semibold" aria-live="polite" data-testid="conteo-asistencia">
          <span className="font-black text-exito">{conteo.P}</span> {conteo.P === 1 ? 'presente' : 'presentes'} ·{' '}
          <span className="font-black text-peligro">{conteo.F}</span> {conteo.F === 1 ? 'falta' : 'faltas'} ·{' '}
          <span className="font-black text-aviso">{conteo.T}</span> {conteo.T === 1 ? 'tardanza' : 'tardanzas'}
          {conteo.J > 0 && (
            <>
              {' '}
              · <span className="font-black text-info">{conteo.J}</span>{' '}
              {conteo.J === 1 ? 'justificada' : 'justificadas'}
            </>
          )}
        </p>
      </div>

      {editable ? (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-primario-suave/60 px-4 py-3">
          <p className="flex items-center gap-2">
            <Info className="size-5 shrink-0 text-primario-oscuro" aria-hidden />
            Todos aparecen como Presente por defecto. Cambia solo las excepciones.
          </p>
          <Button variante="fantasma" tamano="chico" onClick={todosPresentes}>
            Marcar todos presentes
          </Button>
        </div>
      ) : (
        <p className="flex items-center gap-2 rounded-xl bg-texto/6 px-4 py-3" role="note">
          <Lock className="size-5 shrink-0" aria-hidden />
          {clase.sesion.aviso}
        </p>
      )}

      <Campo
        etiqueta="Tema de la clase (opcional)"
        maxLength={200}
        disabled={!editable}
        error={errors.tema?.message}
        {...register('tema')}
      />

      {clase.estudiantes.length === 0 ? (
        <p className="py-6 text-center text-texto-suave">El aula no tiene estudiantes matriculados en esta fecha.</p>
      ) : (
        <div className="rounded-2xl border border-borde">
          <div
            className="hidden grid-cols-[minmax(0,1.3fr)_13rem_minmax(0,1fr)] gap-4 border-b border-borde bg-primario-suave/60 px-4 py-3 text-sm font-bold md:grid"
            aria-hidden
          >
            <span>Estudiante</span>
            <span className="grid grid-cols-4 text-center">
              {ESTADOS.map((e) => (
                <span key={e.valor} title={e.texto}>
                  {e.letra}
                </span>
              ))}
            </span>
            <span>Observación</span>
          </div>
          <ul className="divide-y divide-borde" aria-label="Estudiantes de la clase">
            {clase.estudiantes.map((e, i) => (
              <FilaEstudiante
                key={e.matricula_id}
                fila={e}
                indice={i}
                estado={(marcados[i]?.estado ?? 'P') as EstadoAsistencia}
                editable={editable}
                avisoDesde={clase.aviso_faltas}
                error={errors.registros?.[i]?.observacion?.message}
                register={register}
              />
            ))}
          </ul>
        </div>
      )}

      <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <p className="flex items-center gap-2 text-texto-suave">
          {editable && (
            <>
              <Info className="size-5 shrink-0" aria-hidden />
              {clase.sesion.aviso}
            </>
          )}
        </p>
        {editable && (
          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            {rol === 'admin' && estado !== 'SIN_REGISTRO' && (
              <Button variante="fantasma" onClick={() => setEliminar(true)}>
                <Trash2 className="size-5" aria-hidden /> Eliminar registro
              </Button>
            )}
            {estado !== 'DEFINITIVO' && (
              <Button variante="secundario" onClick={enviar('BORRADOR')} disabled={guardar.isPending}>
                Guardar borrador
              </Button>
            )}
            <Button onClick={enviar('DEFINITIVO')} disabled={guardar.isPending}>
              {guardar.isPending
                ? 'Guardando…'
                : estado === 'DEFINITIVO' || rol === 'admin'
                  ? 'Guardar corrección'
                  : 'Guardar asistencia'}
            </Button>
          </div>
        )}
      </div>

      {rol === 'admin' && (
        <Ventana
          abierto={eliminar}
          titulo="Eliminar registro de la clase"
          onCerrar={() => setEliminar(false)}
          conCerrar
        >
          {eliminar && <FormEliminar clase={clase} onListo={() => setEliminar(false)} />}
        </Ventana>
      )}
    </Card>
  )
}

function FilaEstudiante({
  fila,
  indice,
  estado,
  editable,
  avisoDesde,
  error,
  register,
}: {
  fila: FilaClase
  indice: number
  estado: EstadoAsistencia
  editable: boolean
  avisoDesde: number
  error?: string
  register: UseFormRegister<DatosAsistencia>
}) {
  // RF-37: esta falta sería la N.ª de la semana
  const faltas = fila.faltas_semana + (estado === 'F' ? 1 : 0)
  const alerta = estado === 'F' && faltas >= avisoDesde

  return (
    <li className="grid gap-3 px-4 py-3 md:grid-cols-[minmax(0,1.3fr)_13rem_minmax(0,1fr)] md:items-center md:gap-4">
      <div className="min-w-0">
        <p className="font-bold">{fila.nombre}</p>
        <p className="text-sm text-texto-suave">
          {fila.codigo}
          {fila.corregido_por && ` · corregido por ${fila.corregido_por}`}
        </p>
      </div>
      <fieldset className="grid grid-cols-4 justify-items-center" disabled={!editable}>
        <legend className="sr-only">Asistencia de {fila.nombre}</legend>
        {ESTADOS.map((e) => (
          <label key={e.valor} className={cn('flex flex-col items-center gap-1', editable && 'cursor-pointer')}>
            <input
              type="radio"
              value={e.valor}
              className="peer sr-only"
              aria-label={e.texto}
              {...register(`registros.${indice}.estado`)}
            />
            <span
              className={cn(
                'grid size-9 place-items-center rounded-full border-2 border-borde bg-superficie text-sm font-black text-texto-suave transition-colors peer-checked:text-white peer-focus-visible:ring-3 peer-focus-visible:ring-primario/40',
                COLOR[e.valor],
                !editable && 'opacity-80',
              )}
              aria-hidden
            >
              {e.letra}
            </span>
            <span className="text-xs text-texto-suave md:sr-only" aria-hidden>
              {e.texto.split(' ')[0]}
            </span>
          </label>
        ))}
      </fieldset>
      <div className="flex flex-col gap-1">
        <input
          type="text"
          maxLength={300}
          disabled={!editable}
          placeholder={estado === 'J' ? 'Motivo de la justificación' : '—'}
          aria-label={`Observación de ${fila.nombre}`}
          aria-invalid={error ? true : undefined}
          className={cn(
            'h-10 w-full rounded-lg border bg-superficie px-3 outline-none focus:border-accion focus:ring-3 focus:ring-primario/20 disabled:bg-transparent disabled:text-texto',
            error ? 'border-peligro' : 'border-borde',
          )}
          {...register(`registros.${indice}.observacion`)}
        />
        {error && (
          <p role="alert" className="text-sm font-semibold text-peligro">
            {error}
          </p>
        )}
        {alerta && <p className="text-sm font-bold text-peligro">{faltas}.ª falta esta semana</p>}
      </div>
    </li>
  )
}

function FormEliminar({ clase, onListo }: { clase: Clase; onListo: () => void }) {
  const eliminar = useEliminarClase(clase.curso.id, clase.fecha)
  const avisos = useAvisos()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<{ motivo: string }>({ resolver: zodResolver(esquemaMotivo), defaultValues: { motivo: '' } })

  const enviar = handleSubmit(async ({ motivo }) => {
    try {
      avisos.exito((await eliminar.mutateAsync(motivo)).mensaje)
      onListo()
    } catch (e) {
      const error = e as ErrorApi
      if (error.errores?.motivo) setError('motivo', { message: error.errores.motivo[0] })
      else avisos.error(error.message)
    }
  })

  return (
    <form onSubmit={enviar} noValidate className="flex flex-col gap-4">
      <p className="text-texto-suave">
        Se borra la asistencia de <strong className="text-texto">{clase.curso.nombre}</strong> de esta fecha (
        {plural(clase.estudiantes.length, 'estudiante', 'estudiantes')}). Úsalo solo si la clase no se dictó.
      </p>
      <Campo etiqueta="Motivo" maxLength={300} error={errors.motivo?.message} {...register('motivo')} />
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variante="secundario" onClick={onListo}>
          Cancelar
        </Button>
        <Button type="submit" variante="peligro" disabled={eliminar.isPending}>
          {eliminar.isPending ? 'Eliminando…' : 'Eliminar registro'}
        </Button>
      </div>
    </form>
  )
}
