import { zodResolver } from '@hookform/resolvers/zod'
import { ArrowLeft, CircleAlert, Lightbulb, Plus, UserRound } from 'lucide-react'
import { useId, useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { Card, CardTitulo } from '@/components/ui/card'
import { Ventana } from '@/components/ui/dialogo'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import { Selector } from '@/components/ui/selector'
import {
  ESTADO,
  fechaHora,
  NIVEL,
  useAccionAlerta,
  useAlerta,
  type DetalleAlerta as Detalle,
  type EstadoAlerta,
  type RolAlertas,
} from '@/lib/alertas'
import type { ErrorApi } from '@/lib/api'
import { fechaCorta, hoyIso } from '@/lib/asistencia'
import { cn } from '@/lib/utils'
import {
  esquemaIntervencion,
  esquemaJustificacion,
  type DatosIntervencion,
  type DatosJustificacion,
} from '@/lib/validaciones'

const ACCION: Record<string, string> = {
  GENERADA: 'Alerta generada',
  ASIGNADA_DOCENTE_AULA: 'Asignada',
  REASIGNADA: 'Cambio de encargado',
  NIVEL_ACTUALIZADO: 'Nivel actualizado',
  INTERVENCION: 'Intervención registrada',
  ESTADO_CAMBIADO: 'Cambio de estado',
  CERRADA: 'Alerta cerrada',
}

const BOTON: Partial<Record<EstadoAlerta, string>> = {
  EN_SEGUIMIENTO: 'Pasar a seguimiento',
  CERRADA: 'Cerrar alerta',
  DESCARTADA: 'Descartar',
}

type Dialogo = 'intervencion' | 'encargado' | 'CERRADA' | 'DESCARTADA' | null

// CU-25 (mockups admin 16 y docente 10)
export function DetalleAlerta({ rol, id }: { rol: RolAlertas; id: number }) {
  const { data, isPending, isError, refetch } = useAlerta(rol, id)
  const accion = useAccionAlerta(rol, id)
  const avisos = useAvisos()
  const [dialogo, setDialogo] = useState<Dialogo>(null)
  const volver = (
    <Link to={`/${rol}/alertas`} className="flex items-center gap-2 self-start font-bold text-accion hover:underline">
      <ArrowLeft className="size-5" aria-hidden /> Volver a Alertas
    </Link>
  )

  if (isPending) return <Esqueleto className="mx-auto h-[36rem] max-w-7xl" />
  if (isError)
    return (
      <div className="mx-auto flex max-w-7xl flex-col gap-4">
        {volver}
        <EstadoVacio
          titulo="No se pudo abrir la alerta"
          accion={<Button onClick={() => refetch()}>Reintentar</Button>}
        />
      </div>
    )

  const { alerta, estudiante, prediccion: p } = data
  const abierta = alerta.estado === 'NUEVA' || alerta.estado === 'EN_SEGUIMIENTO'
  const maximo = Math.max(0.5, ...p.factores.map((f) => f.contribucion))

  const pasarASeguimiento = () =>
    accion.mutate(
      { tipo: 'estado', datos: { estado: 'EN_SEGUIMIENTO' } },
      { onSuccess: (r) => avisos.exito(r.mensaje), onError: (e) => avisos.error((e as ErrorApi).message) },
    )

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      {volver}
      <header className="flex flex-col gap-2">
        <div className="flex flex-wrap items-center gap-3">
          <h1 className="text-3xl font-black sm:text-4xl">{estudiante.nombre}</h1>
          <Insignia tono={NIVEL[p.nivel].tono} punto>
            Riesgo {NIVEL[p.nivel].texto.toLowerCase()} · {p.probabilidad} %
          </Insignia>
          <Insignia tono={ESTADO[alerta.estado].tono}>{ESTADO[alerta.estado].texto}</Insignia>
        </div>
        <p className="text-lg text-texto-suave">
          {estudiante.aula} · {estudiante.nivel_educativo} · {alerta.bimestre} · Calculado el {fechaHora(p.fecha)} ·
          Modelo {p.modelo}
        </p>
      </header>

      <p className="flex items-start gap-3 rounded-xl border border-aviso/30 bg-aviso/8 p-4" role="note">
        <CircleAlert className="mt-0.5 size-5 shrink-0 text-aviso" aria-hidden />
        <span>
          <strong>Puntaje de riesgo: {p.probabilidad} %.</strong>{' '}
          {data.anterior
            ? `En el ${data.anterior.bimestre} era ${NIVEL[data.anterior.nivel].texto.toLowerCase()} (${data.anterior.probabilidad} %).`
            : 'Es su primera medición de riesgo en el periodo.'}{' '}
          Las reglas no reemplazan tu criterio: revisa siempre el contexto del estudiante.
        </span>
      </p>

      {!abierta && alerta.justificacion && (
        <p className="rounded-xl bg-texto/6 px-4 py-3" role="note">
          <strong>{ESTADO[alerta.estado].texto}</strong>
          {alerta.cerrada && ` el ${fechaHora(alerta.cerrada)}`}: {alerta.justificacion}
        </p>
      )}

      <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <Card className="flex flex-col gap-4" aria-labelledby="titulo-por-que">
          <div>
            <CardTitulo id="titulo-por-que">¿Por qué el sistema lo señala?</CardTitulo>
            <p className="text-texto-suave">
              Reglas que se activaron con sus datos del bimestre. Mientras más larga la barra, más pesa.
            </p>
          </div>
          {p.factores.length === 0 ? (
            <p className="text-texto-suave">
              Su riesgo bajó: ya no se activa ninguna regla. Revisa si se puede cerrar la alerta.
            </p>
          ) : (
            <ul className="flex flex-col gap-4">
              {p.factores.map((f) => (
                <li key={f.variable} className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_10rem_3.5rem] sm:items-center">
                  <span className="leading-tight">
                    <span className="block font-bold">{f.titulo}</span>
                    <span className="text-sm text-texto-suave">{f.descripcion}</span>
                  </span>
                  <span className="h-3 rounded-full bg-texto/8" aria-hidden>
                    <span
                      className={cn('block h-full rounded-full', f.contribucion >= 0.5 ? 'bg-peligro' : 'bg-aviso')}
                      style={{ width: `${(100 * f.contribucion) / maximo}%` }}
                    />
                  </span>
                  <span className="font-bold text-peligro sm:text-right">+{Math.round(f.contribucion * 100)}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>

        <div className="flex flex-col gap-6">
          <Card className="flex flex-col gap-3" aria-labelledby="titulo-recomendadas">
            <CardTitulo id="titulo-recomendadas">Acciones recomendadas</CardTitulo>
            {p.recomendaciones.length === 0 ? (
              <p className="text-texto-suave">Sin recomendaciones por ahora.</p>
            ) : (
              <ul className="flex flex-col gap-3">
                {p.recomendaciones.map((r) => (
                  <li key={r.descripcion} className="flex items-start gap-3">
                    <Lightbulb className="mt-0.5 size-5 shrink-0 text-primario" aria-hidden />
                    <span>
                      {r.descripcion}
                      {r.prioridad === 1 && (
                        <span className="ml-2 align-middle">
                          <Insignia tono="peligro">Prioritaria</Insignia>
                        </span>
                      )}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </Card>

          <Card className="flex flex-col gap-3" aria-labelledby="titulo-encargado">
            <CardTitulo id="titulo-encargado">Encargado del seguimiento</CardTitulo>
            <p className="flex items-center gap-3">
              <span
                className="grid size-10 place-items-center rounded-full bg-primario-suave text-primario-oscuro"
                aria-hidden
              >
                <UserRound className="size-5" />
              </span>
              <span className="font-bold" data-testid="encargado">
                {data.responsable.nombre}
              </span>
            </p>
            <div className="flex flex-wrap gap-2">
              {data.permisos.gestionar && abierta && (
                <Button onClick={() => setDialogo('intervencion')}>
                  <Plus className="size-5" aria-hidden /> Registrar intervención
                </Button>
              )}
              {data.permisos.reasignar && (
                <Button variante="secundario" onClick={() => setDialogo('encargado')}>
                  Cambiar encargado
                </Button>
              )}
            </div>
            {data.transiciones.length > 0 && (
              <div className="flex flex-wrap gap-2 border-t border-borde pt-3">
                {data.transiciones.map((t) => (
                  <Button
                    key={t}
                    variante={t === 'DESCARTADA' ? 'fantasma' : 'secundario'}
                    tamano="chico"
                    disabled={accion.isPending}
                    onClick={() => (t === 'CERRADA' || t === 'DESCARTADA' ? setDialogo(t) : pasarASeguimiento())}
                  >
                    {BOTON[t]}
                  </Button>
                ))}
              </div>
            )}
            {!data.permisos.gestionar && (
              <p className="text-sm text-texto-suave">
                Solo el encargado, el tutor y la coordinación del aula gestionan esta alerta.
              </p>
            )}
          </Card>
        </div>
      </div>

      <Historial data={data} />

      <Ventana
        abierto={dialogo === 'intervencion'}
        titulo="Registrar intervención"
        onCerrar={() => setDialogo(null)}
        conCerrar
        className="w-[min(94vw,36rem)]"
      >
        <FormIntervencion data={data} rol={rol} id={id} onListo={() => setDialogo(null)} />
      </Ventana>
      <Ventana
        abierto={dialogo === 'encargado'}
        titulo="Cambiar encargado"
        onCerrar={() => setDialogo(null)}
        conCerrar
        className="w-[min(94vw,32rem)]"
      >
        <FormEncargado data={data} rol={rol} id={id} onListo={() => setDialogo(null)} />
      </Ventana>
      <Ventana
        abierto={dialogo === 'CERRADA' || dialogo === 'DESCARTADA'}
        titulo={dialogo === 'DESCARTADA' ? 'Descartar alerta' : 'Cerrar alerta'}
        onCerrar={() => setDialogo(null)}
        conCerrar
        className="w-[min(94vw,32rem)]"
      >
        {(dialogo === 'CERRADA' || dialogo === 'DESCARTADA') && (
          <FormEstado estado={dialogo} rol={rol} id={id} onListo={() => setDialogo(null)} />
        )}
      </Ventana>
    </div>
  )
}

function Historial({ data }: { data: Detalle }) {
  return (
    <Card className="flex flex-col gap-4" aria-labelledby="titulo-historial">
      <CardTitulo id="titulo-historial">Historial e intervenciones</CardTitulo>
      {data.intervenciones.length > 0 && (
        <ul className="grid gap-3 md:grid-cols-2" aria-label="Intervenciones">
          {data.intervenciones.map((i) => (
            <li key={i.id} className="rounded-xl border border-borde p-4">
              <p className="flex flex-wrap items-center justify-between gap-2">
                <strong>{i.tipo}</strong>
                <span className="text-sm text-texto-suave">
                  {fechaCorta(i.fecha)} · {i.registrado_por}
                </span>
              </p>
              {i.descripcion && <p className="mt-1">{i.descripcion}</p>}
              {i.resultado && <p className="mt-1 text-sm text-texto-suave">Resultado: {i.resultado}</p>}
            </li>
          ))}
        </ul>
      )}
      <ol className="flex flex-col" aria-label="Historial de la alerta">
        {data.historial.map((h, n) => (
          <li key={n} className="relative flex gap-4 pb-4 pl-6 last:pb-0">
            <span className="absolute top-1.5 left-0 size-3 rounded-full bg-primario" aria-hidden />
            {n < data.historial.length - 1 && (
              <span className="absolute top-5 bottom-0 left-[5px] w-0.5 bg-borde" aria-hidden />
            )}
            <span className="min-w-0">
              <span className="block font-bold">
                {ACCION[h.accion] ?? h.accion} <span className="font-normal text-texto-suave">· {h.usuario}</span>
              </span>
              <span className="block text-sm text-texto-suave">{fechaHora(h.fecha)}</span>
              {h.detalle && <span className="block">{h.detalle}</span>}
            </span>
          </li>
        ))}
      </ol>
    </Card>
  )
}

type PropsForm = { rol: RolAlertas; id: number; onListo: () => void }

function FormIntervencion({ data, rol, id, onListo }: PropsForm & { data: Detalle }) {
  const accion = useAccionAlerta(rol, id)
  const avisos = useAvisos()
  const idDescripcion = useId()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<DatosIntervencion>({
    resolver: zodResolver(esquemaIntervencion),
    defaultValues: { tipo: data.tipos_intervencion[0] ?? '', descripcion: '', fecha: hoyIso(), resultado: '' },
  })

  const guardar = handleSubmit(async (d) => {
    try {
      const r = await accion.mutateAsync({
        tipo: 'intervenciones',
        datos: { ...d, resultado: d.resultado || null } as never,
      })
      avisos.exito(r.mensaje)
      onListo()
    } catch (e) {
      const error = e as ErrorApi
      const campos = Object.entries(error.errores ?? {})
      for (const [campo, m] of campos) setError(campo as keyof DatosIntervencion, { message: m[0] })
      if (!campos.length) avisos.error(error.message)
    }
  })

  return (
    <form onSubmit={guardar} noValidate className="flex flex-col gap-4">
      <div className="grid gap-4 sm:grid-cols-2">
        <Selector
          etiqueta="Tipo"
          opciones={data.tipos_intervencion.map((t) => ({ valor: t, texto: t }))}
          {...register('tipo')}
        />
        <Campo etiqueta="Fecha" type="date" max={hoyIso()} error={errors.fecha?.message} {...register('fecha')} />
      </div>
      <AreaTexto
        id={idDescripcion}
        etiqueta="¿Qué se hizo?"
        maximo={500}
        error={errors.descripcion?.message}
        {...register('descripcion')}
      />
      <Campo
        etiqueta="Resultado (opcional)"
        maxLength={300}
        error={errors.resultado?.message}
        {...register('resultado')}
      />
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variante="secundario" onClick={onListo}>
          Cancelar
        </Button>
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Guardando…' : 'Guardar intervención'}
        </Button>
      </div>
    </form>
  )
}

function FormEstado({ estado, rol, id, onListo }: PropsForm & { estado: 'CERRADA' | 'DESCARTADA' }) {
  const accion = useAccionAlerta(rol, id)
  const avisos = useAvisos()
  const idJustificacion = useId()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<DatosJustificacion>({ resolver: zodResolver(esquemaJustificacion), defaultValues: { justificacion: '' } })

  const guardar = handleSubmit(async (d) => {
    try {
      const r = await accion.mutateAsync({ tipo: 'estado', datos: { estado, justificacion: d.justificacion } })
      avisos.exito(r.mensaje)
      onListo()
    } catch (e) {
      const error = e as ErrorApi
      if (error.errores?.justificacion) setError('justificacion', { message: error.errores.justificacion[0] })
      else avisos.error(error.message)
    }
  })

  return (
    <form onSubmit={guardar} noValidate className="flex flex-col gap-4">
      <p className="text-texto-suave">
        {estado === 'CERRADA'
          ? 'Ciérrala cuando el estudiante ya no necesite seguimiento. Necesita al menos una intervención registrada.'
          : 'Descártala si la señal no corresponde (por ejemplo, faltas ya justificadas fuera del sistema).'}
      </p>
      <AreaTexto
        id={idJustificacion}
        etiqueta="Justificación"
        maximo={400}
        error={errors.justificacion?.message}
        {...register('justificacion')}
      />
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variante="secundario" onClick={onListo}>
          Cancelar
        </Button>
        <Button type="submit" variante={estado === 'DESCARTADA' ? 'peligro' : 'principal'} disabled={isSubmitting}>
          {isSubmitting ? 'Guardando…' : estado === 'CERRADA' ? 'Cerrar alerta' : 'Descartar alerta'}
        </Button>
      </div>
    </form>
  )
}

function FormEncargado({ data, rol, id, onListo }: PropsForm & { data: Detalle }) {
  const accion = useAccionAlerta(rol, id)
  const avisos = useAvisos()
  const idJustificacion = useId()
  const otros = data.candidatos.filter((c) => c.docente_id !== data.responsable.docente_id)
  const [docente, setDocente] = useState(String(otros[0]?.docente_id ?? ''))
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<DatosJustificacion>({ resolver: zodResolver(esquemaJustificacion), defaultValues: { justificacion: '' } })

  const guardar = handleSubmit(async (d) => {
    try {
      const r = await accion.mutateAsync({
        tipo: 'responsable',
        datos: { docente_id: Number(docente), justificacion: d.justificacion },
      })
      avisos.exito(r.mensaje)
      onListo()
    } catch (e) {
      const error = e as ErrorApi
      if (error.errores?.justificacion) setError('justificacion', { message: error.errores.justificacion[0] })
      else avisos.error(error.errores?.docente_id?.[0] ?? error.message)
    }
  })

  if (otros.length === 0)
    return <p className="text-texto-suave">No hay otros docentes del estudiante a quienes asignarla.</p>

  return (
    <form onSubmit={guardar} noValidate className="flex flex-col gap-4">
      <Selector
        etiqueta="Docente encargado"
        value={docente}
        onChange={(e) => setDocente(e.target.value)}
        opciones={otros.map((c) => ({ valor: String(c.docente_id), texto: `${c.nombre} · ${c.rol}` }))}
      />
      <AreaTexto
        id={idJustificacion}
        etiqueta="Justificación"
        maximo={400}
        error={errors.justificacion?.message}
        {...register('justificacion')}
      />
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variante="secundario" onClick={onListo}>
          Cancelar
        </Button>
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Guardando…' : 'Guardar encargado'}
        </Button>
      </div>
    </form>
  )
}

type PropsArea = React.ComponentProps<'textarea'> & { id: string; etiqueta: string; maximo: number; error?: string }

function AreaTexto({ id, etiqueta, maximo, error, ...props }: PropsArea) {
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="font-bold">
        {etiqueta}
      </label>
      <textarea
        id={id}
        rows={4}
        maxLength={maximo}
        aria-invalid={Boolean(error)}
        aria-describedby={error ? `${id}-error` : undefined}
        className="w-full rounded-xl border border-borde bg-superficie px-4 py-3 outline-none focus:border-accion focus:ring-3 focus:ring-primario/20 aria-invalid:border-peligro"
        {...props}
      />
      {error && (
        <p id={`${id}-error`} role="alert" className="text-sm font-semibold text-peligro">
          {error}
        </p>
      )}
    </div>
  )
}
