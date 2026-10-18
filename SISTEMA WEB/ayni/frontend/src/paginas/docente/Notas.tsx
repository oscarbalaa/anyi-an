import { ArrowLeft, Download, FileDown, Info, Save } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { Link, useBlocker, useSearchParams } from 'react-router'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { DialogoConfirmacion } from '@/components/ui/dialogo'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import { TablaDatos } from '@/components/ui/tabla-datos'
import { descargar, type ErrorApi } from '@/lib/api'
import { fechaCorta } from '@/lib/asistencia'
import {
  ESTADO_ENTREGA,
  nota,
  useCambioEvaluaciones,
  useEvaluaciones,
  usePlanilla,
  type EstadoEntrega,
  type Planilla,
} from '@/lib/evaluaciones'
import { cn } from '@/lib/utils'
import { errorNota, filtros } from '@/lib/validaciones'
import { FiltrosCurso } from './FiltrosCurso'
import { useCursoElegido } from './useCursoElegido'

// CU-18 (mockup docente 07): sin ?evaluacion= se elige la evaluación
export default function Notas() {
  const [parametros] = useSearchParams()
  const evaluacion = Number(parametros.get('evaluacion')) || null
  return evaluacion ? <PlanillaNotas id={evaluacion} /> : <ElegirEvaluacion />
}

function ElegirEvaluacion() {
  const { cursos, lista, curso, bimestre, cambiar } = useCursoElegido()
  const { data, isPending } = useEvaluaciones(curso?.id ?? null, bimestre)

  if (cursos.isPending) return <Esqueleto className="mx-auto h-96 max-w-6xl" />
  if (!curso) return <EstadoVacio titulo="Aún no tienes cursos a cargo" />

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6">
      <header>
        <h1 className="text-3xl font-black sm:text-4xl">Notas</h1>
        <p className="mt-1 text-lg text-texto-suave">Elige la evaluación que vas a calificar.</p>
      </header>
      <FiltrosCurso
        cursos={lista}
        curso={curso}
        bimestres={data?.bimestres ?? []}
        bimestre={data?.bimestre ?? null}
        onCurso={(id) => cambiar({ curso: id, bimestre: null })}
        onBimestre={(id) => cambiar({ bimestre: id })}
      />
      <Card>
        {isPending ? (
          <Esqueleto className="h-64" />
        ) : (
          <TablaDatos
            filas={data?.evaluaciones.filter((e) => e.momento !== 'POR_ASIGNAR') ?? []}
            claveFila={(e) => e.id}
            descripcion="Evaluaciones para calificar"
            vacio="No hay evaluaciones asignadas en este bimestre."
            columnas={[
              { clave: 'titulo', titulo: 'Evaluación', principal: true, celda: (e) => <span className="font-bold">{e.titulo}</span> },
              { clave: 'limite', titulo: 'Fecha límite', celda: (e) => fechaCorta(e.fecha_limite) },
              {
                clave: 'calificadas',
                titulo: 'Calificadas',
                celda: (e) => (
                  <Insignia tono={e.calificadas >= e.estudiantes ? 'exito' : 'aviso'}>
                    {e.calificadas} de {e.estudiantes}
                  </Insignia>
                ),
              },
              {
                clave: 'accion',
                titulo: 'Acción',
                celda: (e) => (
                  <Button asChild variante="secundario" tamano="chico">
                    <Link to={`?evaluacion=${e.id}`} aria-label={`Calificar ${e.titulo}`}>
                      Calificar
                    </Link>
                  </Button>
                ),
              },
            ]}
          />
        )}
      </Card>
    </div>
  )
}

type Fila = { nota: string; estado_entrega: EstadoEntrega; observacion: string }

const desde = (p: Planilla): Record<number, Fila> =>
  Object.fromEntries(
    p.estudiantes.map((e) => [
      e.matricula_id,
      { nota: e.nota === null ? '' : nota(e.nota), estado_entrega: e.estado_entrega, observacion: e.observacion ?? '' },
    ]),
  )

function PlanillaNotas({ id }: { id: number }) {
  const { data, isPending, isError, error } = usePlanilla(id)
  if (isPending) return <Esqueleto className="mx-auto h-[32rem] max-w-6xl" />
  if (isError) return <EstadoVacio titulo="No se pudo abrir la evaluación" descripcion={error.message} />
  return <Formulario key={`${data.evaluacion.id}-${JSON.stringify(desde(data))}`} planilla={data} />
}

function Formulario({ planilla }: { planilla: Planilla }) {
  const original = useMemo(() => desde(planilla), [planilla])
  const [filas, setFilas] = useState(original)
  const [errores, setErrores] = useState<Record<string, string>>({})
  const cambio = useCambioEvaluaciones()
  const avisos = useAvisos()
  const { evaluacion, curso, editable } = planilla

  const sucias = Object.keys(filas).filter((k) => JSON.stringify(filas[Number(k)]) !== JSON.stringify(original[Number(k)]))
  const sinGuardar = sucias.length > 0

  // Aviso de cambios sin guardar al salir de la pantalla o cerrar la pestaña
  const bloqueo = useBlocker(({ currentLocation, nextLocation }) => sinGuardar && currentLocation.search !== nextLocation.search || (sinGuardar && currentLocation.pathname !== nextLocation.pathname))
  useEffect(() => {
    if (!sinGuardar) return
    const avisar = (e: BeforeUnloadEvent) => e.preventDefault()
    window.addEventListener('beforeunload', avisar)
    return () => window.removeEventListener('beforeunload', avisar)
  }, [sinGuardar])

  const cambiar = (matricula: number, campo: keyof Fila, valor: string) => {
    setFilas((f) => ({ ...f, [matricula]: { ...f[matricula], [campo]: valor } }))
    if (campo === 'nota') {
      const mensaje = errorNota(valor)
      setErrores((e) => {
        const { [matricula]: _, ...resto } = e
        return mensaje ? { ...resto, [matricula]: mensaje } : resto
      })
    }
  }

  const guardar = async () => {
    const conError = planilla.estudiantes.filter((e) => errorNota(filas[e.matricula_id].nota))
    if (conError.length) {
      avisos.error('Revisa las notas marcadas en rojo antes de guardar.')
      return
    }
    try {
      const r = await cambio.mutateAsync({
        metodo: 'PUT',
        ruta: `/docente/evaluaciones/${evaluacion.id}/notas`,
        datos: {
          registros: planilla.estudiantes.map((e) => ({
            matricula_id: e.matricula_id,
            nota: filas[e.matricula_id].nota || null,
            estado_entrega: filas[e.matricula_id].estado_entrega,
            observacion: filas[e.matricula_id].observacion || null,
          })),
        },
      })
      avisos.exito(r.mensaje)
    } catch (e) {
      const err = e as ErrorApi
      const porFila: Record<string, string> = {}
      for (const [campo, m] of Object.entries(err.errores ?? {})) {
        const i = Number(campo.split('.')[1])
        if (planilla.estudiantes[i]) porFila[planilla.estudiantes[i].matricula_id] = m[0]
      }
      setErrores(porFila)
      if (!Object.keys(porFila).length) avisos.error(err.message)
    }
  }

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6">
      <div>
        <Link to={`/docente/evaluaciones?curso=${curso.id}`} className="inline-flex items-center gap-2 font-bold text-accion hover:underline">
          <ArrowLeft className="size-4" aria-hidden /> Volver a Evaluaciones
        </Link>
        <h1 className="mt-2 text-3xl font-black sm:text-4xl">{evaluacion.titulo}</h1>
        <p className="mt-1 text-lg text-texto-suave">
          {curso.nombre} · Fecha límite: {fechaCorta(evaluacion.fecha_limite)} · Peso {evaluacion.peso}
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <Indicador valor={nota(planilla.resumen.promedio)} etiqueta="Promedio del aula" />
        <Indicador valor={planilla.resumen.desaprobados} etiqueta={`Desaprobados (< ${nota(planilla.nota_aprobatoria)})`} tono="text-peligro" />
        <Indicador valor={planilla.resumen.sin_entregar} etiqueta="Sin entregar" tono="text-aviso" />
      </div>

      {!editable && (
        <p className="rounded-xl bg-texto/6 px-4 py-3" role="note">
          El {planilla.bimestre.nombre} está cerrado: sus notas ya no se modifican.
        </p>
      )}
      {planilla.publicada && editable && (
        <p className="flex items-center gap-2 rounded-xl bg-info/10 px-4 py-3 text-info" role="note">
          <Info className="size-5 shrink-0" aria-hidden />
          Los promedios de este curso ya están publicados: los estudiantes verán los cambios al guardar.
        </p>
      )}

      <Card className="flex flex-col gap-4 p-0 sm:p-0">
        <div className="hidden grid-cols-[minmax(0,1.4fr)_11rem_8rem_minmax(0,1fr)] gap-4 border-b border-borde bg-primario-suave/60 px-5 py-3 text-sm font-bold md:grid" aria-hidden>
          <span>Estudiante</span>
          <span>Estado de entrega</span>
          <span>Nota (0 – 20)</span>
          <span>Observación</span>
        </div>
        <ul className="divide-y divide-borde" aria-label="Notas de los estudiantes">
          {planilla.estudiantes.map((e) => {
            const f = filas[e.matricula_id]
            const error = errores[e.matricula_id]
            const valor = Number(f.nota.replace(',', '.'))
            const baja = f.nota !== '' && !error && valor < planilla.nota_aprobatoria
            return (
              <li key={e.matricula_id} className="grid gap-3 px-5 py-3 md:grid-cols-[minmax(0,1.4fr)_11rem_8rem_minmax(0,1fr)] md:items-center md:gap-4">
                <div className="min-w-0">
                  <p className="font-bold">{e.nombre}</p>
                  {e.entrega?.archivo ? (
                    <button
                      type="button"
                      className="inline-flex cursor-pointer items-center gap-1 text-sm font-semibold text-accion hover:underline"
                      onClick={() => descargar(`/archivos/${e.entrega!.archivo!.id}`, e.entrega!.archivo!.nombre).catch((x) => avisos.error((x as ErrorApi).message))}
                      aria-label={`Descargar la entrega de ${e.nombre}`}
                    >
                      <Download className="size-4" aria-hidden /> Entregó el {fechaCorta(e.entrega.entregada_en.slice(0, 10))}
                    </button>
                  ) : (
                    <p className="text-sm text-texto-suave">{e.codigo}</p>
                  )}
                </div>
                <label className="flex flex-col gap-1 text-sm md:text-base">
                  <span className="text-texto-suave md:sr-only">Estado de entrega de {e.nombre}</span>
                  <select
                    value={f.estado_entrega}
                    disabled={!editable}
                    onChange={(x) => cambiar(e.matricula_id, 'estado_entrega', x.target.value)}
                    aria-label={`Estado de entrega de ${e.nombre}`}
                    className="h-10 cursor-pointer rounded-lg border border-borde bg-superficie px-2 outline-none focus:border-accion"
                  >
                    {(['PENDIENTE', 'ENTREGADO', 'TARDIO', 'NO_ENTREGADO'] as const).map((s) => (
                      <option key={s} value={s}>
                        {ESTADO_ENTREGA[s].texto}
                        {s === e.estado_sugerido && s !== f.estado_entrega ? ' (calculado)' : ''}
                      </option>
                    ))}
                  </select>
                </label>
                <div className="flex flex-col gap-1">
                  <input
                    type="text"
                    inputMode="decimal"
                    maxLength={5}
                    value={f.nota}
                    disabled={!editable}
                    placeholder="—"
                    onChange={(x) => cambiar(e.matricula_id, 'nota', filtros.nota(x.target.value))}
                    aria-label={`Nota de ${e.nombre}`}
                    aria-invalid={error ? true : undefined}
                    className={cn(
                      'h-10 w-24 rounded-lg border bg-superficie text-center font-bold outline-none focus:border-accion focus:ring-3 focus:ring-primario/20',
                      error ? 'border-peligro' : baja ? 'border-peligro/60 text-peligro' : 'border-borde',
                    )}
                  />
                  {error && (
                    <p role="alert" className="text-sm font-semibold text-peligro">
                      {error}
                    </p>
                  )}
                </div>
                <input
                  type="text"
                  maxLength={300}
                  value={f.observacion}
                  disabled={!editable}
                  placeholder="—"
                  onChange={(x) => cambiar(e.matricula_id, 'observacion', x.target.value)}
                  aria-label={`Observación de ${e.nombre}`}
                  className="h-10 w-full rounded-lg border border-borde bg-superficie px-3 outline-none focus:border-accion disabled:bg-transparent"
                />
              </li>
            )
          })}
        </ul>
      </Card>

      <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <p className="flex items-center gap-2 text-texto-suave">
          <Info className="size-5 shrink-0" aria-hidden /> Notas permitidas: 0 a 20, con punto o coma (15,5).
        </p>
        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
          {sinGuardar && (
            <span className="flex items-center gap-2 font-semibold text-aviso" role="status">
              <span className="size-2.5 rounded-full bg-aviso" aria-hidden /> Cambios sin guardar
            </span>
          )}
          <Button
            variante="secundario"
            onClick={() =>
              descargar(`/docente/evaluaciones/${evaluacion.id}/notas/excel`, 'notas.xlsx').catch((x) => avisos.error((x as ErrorApi).message))
            }
          >
            <FileDown className="size-5" aria-hidden /> Exportar
          </Button>
          {editable && (
            <Button onClick={guardar} disabled={cambio.isPending || !sinGuardar}>
              <Save className="size-5" aria-hidden /> {cambio.isPending ? 'Guardando…' : 'Guardar notas'}
            </Button>
          )}
        </div>
      </div>

      <DialogoConfirmacion
        abierto={bloqueo.state === 'blocked'}
        titulo="Tienes cambios sin guardar"
        textoConfirmar="Salir sin guardar"
        peligro
        onCancelar={() => bloqueo.reset?.()}
        onConfirmar={() => bloqueo.proceed?.()}
      >
        Si sales ahora, se perderán las notas que no guardaste.
      </DialogoConfirmacion>
    </div>
  )
}

function Indicador({ valor, etiqueta, tono = 'text-texto' }: { valor: string | number; etiqueta: string; tono?: string }) {
  return (
    <div className="rounded-2xl border border-borde bg-superficie p-5">
      <p className={cn('text-4xl leading-none font-black', tono)}>{valor}</p>
      <p className="mt-2 text-texto-suave">{etiqueta}</p>
    </div>
  )
}
