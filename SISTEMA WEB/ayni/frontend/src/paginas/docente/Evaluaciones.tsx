import { zodResolver } from '@hookform/resolvers/zod'
import { ClipboardList, Clock, Download, FileText, PieChart, Plus, Trash2, Upload } from 'lucide-react'
import { useId, useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { Link } from 'react-router'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { Card } from '@/components/ui/card'
import { Casilla } from '@/components/ui/casilla'
import { DialogoConfirmacion, Ventana } from '@/components/ui/dialogo'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import { Selector } from '@/components/ui/selector'
import { TablaDatos } from '@/components/ui/tabla-datos'
import { TarjetaKpi } from '@/components/ui/tarjeta-kpi'
import { descargar, type ErrorApi } from '@/lib/api'
import { fechaCorta } from '@/lib/asistencia'
import {
  tamano,
  useCambioEvaluaciones,
  useEvaluaciones,
  type FilaEvaluacion,
  type ListaEvaluaciones,
} from '@/lib/evaluaciones'
import { esquemaEvaluacion, type DatosEvaluacion } from '@/lib/validaciones'
import { FiltrosCurso } from './FiltrosCurso'
import { useCursoElegido } from './useCursoElegido'

type Panel = { id: number | null } | null

// CU-16 (mockup docente 06)
export default function Evaluaciones() {
  const { cursos, lista, curso, bimestre, cambiar } = useCursoElegido()
  const { data, isPending, isError, refetch } = useEvaluaciones(curso?.id ?? null, bimestre)
  const [panel, setPanel] = useState<Panel>(null)

  if (cursos.isPending) return <Esqueleto className="mx-auto h-96 max-w-7xl" />
  if (!curso)
    return (
      <EstadoVacio
        titulo="Aún no tienes cursos a cargo"
        descripcion="Cuando el administrador te asigne cursos, aparecerán aquí."
      />
    )

  const editando = panel?.id ? data?.evaluaciones.find((e) => e.id === panel.id) : undefined

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-3xl font-black sm:text-4xl">Evaluaciones</h1>
          <p className="mt-1 text-lg text-texto-suave">
            {curso.nombre}
            {data && ` · ${data.bimestre.nombre}`}
          </p>
        </div>
        <Button onClick={() => setPanel({ id: null })} disabled={!data?.editable}>
          <Plus className="size-5" aria-hidden /> Nueva evaluación
        </Button>
      </header>

      <FiltrosCurso
        cursos={lista}
        curso={curso}
        bimestres={data?.bimestres ?? []}
        bimestre={data?.bimestre ?? null}
        onCurso={(id) => cambiar({ curso: id, bimestre: null })}
        onBimestre={(id) => cambiar({ bimestre: id })}
      />

      {isPending ? (
        <Esqueleto className="h-96" />
      ) : isError ? (
        <EstadoVacio
          titulo="No se pudo cargar las evaluaciones"
          accion={<Button onClick={() => refetch()}>Reintentar</Button>}
        />
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-3">
            <TarjetaKpi icono={<FileText />} valor={data.resumen.evaluaciones} etiqueta="Evaluaciones" tono="info" />
            <TarjetaKpi
              icono={<PieChart />}
              valor={data.resumen.entregas === null ? '—' : `${data.resumen.entregas} %`}
              etiqueta="Entregas"
              tono="primario"
            />
            <TarjetaKpi icono={<Clock />} valor={data.resumen.por_calificar} etiqueta="Por calificar" tono="aviso" />
          </div>
          {!data.editable && (
            <p className="rounded-xl bg-texto/6 px-4 py-3" role="note">
              El {data.bimestre.nombre} está cerrado: sus evaluaciones y notas ya no se modifican.
            </p>
          )}
          <Card>
            <TablaDatos
              filas={data.evaluaciones}
              claveFila={(e) => e.id}
              descripcion="Evaluaciones del bimestre"
              vacio="Aún no hay evaluaciones en este bimestre."
              columnas={[
                {
                  clave: 'titulo',
                  titulo: 'Evaluación',
                  principal: true,
                  celda: (e) => (
                    <span className="block leading-tight">
                      <span className="block font-bold">{e.titulo}</span>
                      {e.material.length > 0 && (
                        <span className="text-sm text-texto-suave">
                          {e.material.length} {e.material.length === 1 ? 'archivo' : 'archivos'} de apoyo
                        </span>
                      )}
                    </span>
                  ),
                },
                { clave: 'tipo', titulo: 'Tipo', celda: (e) => e.tipo },
                { clave: 'peso', titulo: 'Peso', celda: (e) => e.peso },
                { clave: 'limite', titulo: 'Fecha límite', celda: (e) => fechaCorta(e.fecha_limite) },
                { clave: 'entregas', titulo: 'Entregas', celda: (e) => <Entregas e={e} /> },
                {
                  clave: 'accion',
                  titulo: 'Acción',
                  celda: (e) => (
                    <span className="flex flex-wrap gap-2">
                      <Button asChild variante="secundario" tamano="chico">
                        <Link to={`/docente/notas?evaluacion=${e.id}`} aria-label={`Calificar ${e.titulo}`}>
                          Calificar
                        </Link>
                      </Button>
                      <Button
                        variante="fantasma"
                        tamano="chico"
                        onClick={() => setPanel({ id: e.id })}
                        aria-label={`Abrir ${e.titulo}`}
                      >
                        Abrir
                      </Button>
                    </span>
                  ),
                },
              ]}
            />
          </Card>
        </>
      )}

      <Ventana
        abierto={panel !== null && Boolean(data)}
        titulo={panel?.id ? 'Editar evaluación' : 'Nueva evaluación'}
        onCerrar={() => setPanel(null)}
        conCerrar
        className="w-[min(94vw,40rem)]"
      >
        {panel && data && panel.id !== null && !editando && <Esqueleto className="h-80" />}
        {panel && data && (panel.id === null || editando) && (
          <FormEvaluacion
            key={panel.id ?? 'nueva'}
            lista={data}
            evaluacion={editando}
            onCreada={(id) => setPanel({ id })}
            onListo={() => setPanel(null)}
          />
        )}
      </Ventana>
    </div>
  )
}

function Entregas({ e }: { e: FilaEvaluacion }) {
  if (!e.requiere_entrega) return <Insignia tono="info">En clase</Insignia>
  if (e.momento === 'POR_ASIGNAR') return <Insignia>Aún no</Insignia>
  const pct = e.estudiantes ? Math.round((100 * e.entregas) / e.estudiantes) : 0
  return (
    <span className="flex min-w-28 flex-col gap-1" title={`${e.entregas} de ${e.estudiantes} entregaron`}>
      <span className="h-2 rounded-full bg-texto/8" aria-hidden>
        <span className="block h-full rounded-full bg-primario" style={{ width: `${pct}%` }} />
      </span>
      <span className="text-sm text-texto-suave">
        {e.entregas} de {e.estudiantes}
      </span>
    </span>
  )
}

function FormEvaluacion({
  lista,
  evaluacion,
  onCreada,
  onListo,
}: {
  lista: ListaEvaluaciones
  evaluacion?: FilaEvaluacion
  onCreada: (id: number) => void
  onListo: () => void
}) {
  const cambio = useCambioEvaluaciones()
  const avisos = useAvisos()
  const idIndicaciones = useId()
  const [eliminar, setEliminar] = useState(false)
  const { bimestre, bimestres, tipos } = lista
  const {
    register,
    handleSubmit,
    setError,
    control,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<DatosEvaluacion>({
    resolver: zodResolver(esquemaEvaluacion),
    defaultValues: {
      titulo: evaluacion?.titulo ?? '',
      tipo_evaluacion_id: String(
        evaluacion?.tipo_evaluacion_id ?? tipos.find((t) => t.nombre === 'Práctica')?.id ?? tipos[0]?.id ?? '',
      ),
      subperiodo_id: String(bimestre.id),
      fecha_asignacion: evaluacion?.fecha_asignacion ?? '',
      fecha_limite: evaluacion?.fecha_limite ?? '',
      peso: String(evaluacion?.peso ?? 1),
      indicaciones: evaluacion?.indicaciones ?? '',
      requiere_entrega: evaluacion?.requiere_entrega ?? true,
    },
  })
  const subperiodo = useWatch({ control, name: 'subperiodo_id' })
  const elegido = bimestres.find((b) => String(b.id) === subperiodo) ?? bimestre

  const guardar = handleSubmit(async (d) => {
    const datos = {
      ...d,
      tipo_evaluacion_id: Number(d.tipo_evaluacion_id),
      subperiodo_id: Number(d.subperiodo_id),
      peso: Number(d.peso),
      indicaciones: d.indicaciones || null,
    }
    try {
      const r = evaluacion
        ? await cambio.mutateAsync({ metodo: 'PUT', ruta: `/docente/evaluaciones/${evaluacion.id}`, datos })
        : await cambio.mutateAsync({ metodo: 'POST', ruta: `/docente/cursos/${lista.curso.id}/evaluaciones`, datos })
      avisos.exito(r.mensaje)
      if (evaluacion) onListo()
      else if (r.id) onCreada(r.id)
    } catch (e) {
      const error = e as ErrorApi
      const campos = Object.entries(error.errores ?? {})
      for (const [campo, m] of campos) setError(campo as keyof DatosEvaluacion, { message: m[0] })
      if (!campos.length) avisos.error(error.message)
    }
  })

  return (
    <div className="flex flex-col gap-5">
      <form onSubmit={guardar} noValidate className="flex flex-col gap-4">
        <Campo etiqueta="Título" maxLength={150} error={errors.titulo?.message} {...register('titulo')} />
        <div className="grid gap-4 sm:grid-cols-2">
          <Selector
            etiqueta="Tipo"
            opciones={tipos.map((t) => ({ valor: String(t.id), texto: t.nombre }))}
            {...register('tipo_evaluacion_id', {
              // Un examen se rinde en clase: por defecto no pide archivo
              onChange: (e) =>
                setValue('requiere_entrega', tipos.find((t) => String(t.id) === e.target.value)?.nombre !== 'Examen'),
            })}
          />
          <Selector
            etiqueta="Bimestre"
            opciones={bimestres
              .filter((b) => b.estado === 'ABIERTO')
              .map((b) => ({ valor: String(b.id), texto: b.nombre }))}
            {...register('subperiodo_id')}
          />
          <Campo
            etiqueta="Fecha de asignación"
            type="date"
            min={elegido.fecha_inicio}
            max={elegido.fecha_fin}
            error={errors.fecha_asignacion?.message}
            {...register('fecha_asignacion')}
          />
          <Campo
            etiqueta="Fecha límite"
            type="date"
            min={elegido.fecha_inicio}
            max={elegido.fecha_fin}
            error={errors.fecha_limite?.message}
            ayuda="Se puede entregar hasta las 11:59 p. m."
            {...register('fecha_limite')}
          />
          <Selector
            etiqueta="Peso en el promedio"
            opciones={Array.from({ length: 10 }, (_, i) => ({ valor: String(i + 1), texto: String(i + 1) }))}
            {...register('peso')}
          />
        </div>
        {errors.peso && (
          <p role="alert" className="text-sm font-semibold text-peligro">
            {errors.peso.message}
          </p>
        )}
        <div className="flex flex-col gap-1.5">
          <label htmlFor={idIndicaciones} className="font-bold">
            Indicaciones (opcional)
          </label>
          <textarea
            id={idIndicaciones}
            rows={4}
            maxLength={2000}
            aria-invalid={Boolean(errors.indicaciones)}
            className="w-full rounded-xl border border-borde bg-superficie px-4 py-3 outline-none focus:border-accion focus:ring-3 focus:ring-primario/20 aria-invalid:border-peligro"
            {...register('indicaciones')}
          />
          {errors.indicaciones && (
            <p role="alert" className="text-sm font-semibold text-peligro">
              {errors.indicaciones.message}
            </p>
          )}
        </div>
        <Casilla etiqueta="Los estudiantes entregan un archivo (PDF)" {...register('requiere_entrega')} />
        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          {evaluacion && (
            <Button variante="fantasma" onClick={() => setEliminar(true)} className="sm:mr-auto">
              <Trash2 className="size-5" aria-hidden /> Eliminar
            </Button>
          )}
          <Button variante="secundario" onClick={onListo}>
            {evaluacion ? 'Listo' : 'Cancelar'}
          </Button>
          <Button type="submit" disabled={isSubmitting || !lista.editable}>
            {isSubmitting ? 'Guardando…' : evaluacion ? 'Guardar cambios' : 'Crear evaluación'}
          </Button>
        </div>
      </form>

      {evaluacion && <Material evaluacion={evaluacion} limite={lista.limite_archivo} editable={lista.editable} />}

      <DialogoConfirmacion
        abierto={eliminar}
        titulo="Eliminar evaluación"
        textoConfirmar="Sí, eliminar"
        peligro
        ocupado={cambio.isPending}
        onCancelar={() => setEliminar(false)}
        onConfirmar={() =>
          evaluacion &&
          cambio.mutate(
            { metodo: 'DELETE', ruta: `/docente/evaluaciones/${evaluacion.id}` },
            {
              onSuccess: (r) => {
                avisos.exito(r.mensaje)
                onListo()
              },
              onError: (e) => avisos.error((e as ErrorApi).message),
              onSettled: () => setEliminar(false),
            },
          )
        }
      >
        Se elimina «{evaluacion?.titulo}» y su material. Solo es posible si aún no tiene entregas ni notas.
      </DialogoConfirmacion>
    </div>
  )
}

/** RF-39: archivos de apoyo de la evaluación */
function Material({ evaluacion, limite, editable }: { evaluacion: FilaEvaluacion; limite: string; editable: boolean }) {
  const cambio = useCambioEvaluaciones()
  const avisos = useAvisos()
  const entrada = useRef<HTMLInputElement>(null)
  const [error, setErrorArchivo] = useState<string | null>(null)

  const subirArchivo = async (archivo: File) => {
    const datos = new FormData()
    datos.append('archivo', archivo)
    setErrorArchivo(null)
    try {
      const r = await cambio.mutateAsync({
        metodo: 'SUBIR',
        ruta: `/docente/evaluaciones/${evaluacion.id}/material`,
        datos,
      })
      avisos.exito(r.mensaje)
    } catch (e) {
      const err = e as ErrorApi
      setErrorArchivo(err.errores?.archivo?.[0] ?? err.message)
    }
    if (entrada.current) entrada.current.value = ''
  }

  return (
    <section className="flex flex-col gap-3 border-t border-borde pt-4" aria-labelledby={`material-${evaluacion.id}`}>
      <h3 id={`material-${evaluacion.id}`} className="flex items-center gap-2 text-lg font-extrabold">
        <ClipboardList className="size-5" aria-hidden /> Material de apoyo
      </h3>
      {evaluacion.material.length === 0 ? (
        <p className="text-texto-suave">Aún no hay archivos.</p>
      ) : (
        <ul className="flex flex-col gap-2">
          {evaluacion.material.map((a) => (
            <li
              key={a.id}
              className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-borde px-3 py-2"
            >
              <span className="min-w-0 truncate">
                {a.nombre} <span className="text-sm text-texto-suave">· {tamano(a.tamano)}</span>
              </span>
              <span className="flex gap-1">
                <Button
                  variante="fantasma"
                  tamano="chico"
                  onClick={() =>
                    descargar(`/archivos/${a.id}`, a.nombre).catch((e) => avisos.error((e as ErrorApi).message))
                  }
                  aria-label={`Descargar ${a.nombre}`}
                >
                  <Download className="size-4" aria-hidden />
                </Button>
                {editable && (
                  <Button
                    variante="fantasma"
                    tamano="chico"
                    aria-label={`Quitar ${a.nombre}`}
                    onClick={() =>
                      cambio.mutate(
                        { metodo: 'DELETE', ruta: `/docente/evaluaciones/${evaluacion.id}/material/${a.id}` },
                        {
                          onSuccess: (r) => avisos.exito(r.mensaje),
                          onError: (e) => avisos.error((e as ErrorApi).message),
                        },
                      )
                    }
                  >
                    <Trash2 className="size-4" aria-hidden />
                  </Button>
                )}
              </span>
            </li>
          ))}
        </ul>
      )}
      {editable && (
        <div className="flex flex-col gap-1.5">
          <input
            ref={entrada}
            type="file"
            className="sr-only"
            id={`subir-${evaluacion.id}`}
            accept=".pdf,.docx,.pptx,.xlsx,.jpg,.jpeg,.png"
            onChange={(e) => e.target.files?.[0] && subirArchivo(e.target.files[0])}
          />
          <Button
            variante="secundario"
            tamano="chico"
            onClick={() => entrada.current?.click()}
            disabled={cambio.isPending}
            className="self-start"
          >
            <Upload className="size-4" aria-hidden /> {cambio.isPending ? 'Subiendo…' : 'Agregar archivo'}
          </Button>
          <label htmlFor={`subir-${evaluacion.id}`} className="text-sm text-texto-suave">
            PDF, Word, PowerPoint, Excel, JPG o PNG · hasta {limite}
          </label>
          {error && (
            <p role="alert" className="text-sm font-semibold text-peligro">
              {error}
            </p>
          )}
        </div>
      )}
    </section>
  )
}
