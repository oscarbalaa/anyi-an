import { zodResolver } from '@hookform/resolvers/zod'
import { Plus, Trash2, UserRoundPen } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { Card } from '@/components/ui/card'
import { Ventana } from '@/components/ui/dialogo'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Selector } from '@/components/ui/selector'
import {
  DIAS,
  DIAS_LARGOS,
  useCambio,
  useCarga,
  useOpciones,
  type Bloque,
  type Carga,
  type CursoAula,
  type Opciones,
} from '@/lib/estructura'
import { marcarErrores } from '@/lib/formularios'
import { esquemaBloque, type DatosBloque } from '@/lib/validaciones'
import { Encabezado } from './Encabezado'
import { useConfirmar } from './useConfirmar'

// Un color por curso para reconocerlo en la grilla
const COLORES = ['#7b4b74', '#4d8b6f', '#d9803f', '#9a6b9c', '#3f8f9a', '#c4573f', '#6a7f3a', '#b3832f']

// RF-23 y RF-25 (mockup admin 12)
export function PestanaCarga({
  periodoInicial,
  aulaInicial,
}: {
  periodoInicial: number | null
  aulaInicial: number | null
}) {
  const [periodo, setPeriodo] = useState<number | null>(periodoInicial)
  const { data: opciones } = useOpciones(periodo)
  const [aula, setAula] = useState<number | null>(aulaInicial)
  const elegirPeriodo = (id: number) => {
    setPeriodo(id)
    setAula(null)
  }
  const aulaId = aula ?? opciones?.aulas[0]?.id ?? null
  const { data: carga, isPending, isError } = useCarga(aulaId)
  const [asignando, setAsignando] = useState(false)
  const [docenteDe, setDocenteDe] = useState<CursoAula | null>(null)
  const [bloque, setBloque] = useState<Bloque | 'nuevo' | null>(null)
  const { confirmar, dialogo } = useConfirmar()

  if (opciones && opciones.aulas.length === 0) {
    return (
      <div className="flex flex-col gap-6">
        <SelectorPeriodo opciones={opciones} onCambiar={elegirPeriodo} />
        <EstadoVacio titulo="Este periodo aún no tiene aulas" descripcion="Créalas en la pestaña Grados y aulas." />
      </div>
    )
  }

  const color = (cursoId: number) => COLORES[(carga?.cursos.findIndex((c) => c.id === cursoId) ?? 0) % COLORES.length]
  const editable = carga && !carga.aula.periodo_cerrado

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div className="grid w-full gap-4 sm:w-auto sm:grid-cols-[10rem_20rem]">
          {opciones && <SelectorPeriodo opciones={opciones} onCambiar={elegirPeriodo} />}
          <Selector
            etiqueta="Aula"
            opciones={(opciones?.aulas ?? []).map((a) => ({ valor: String(a.id), texto: a.nombre }))}
            value={aulaId ?? ''}
            onChange={(e) => setAula(Number(e.target.value))}
          />
        </div>
        {editable && (
          <Button variante="secundario" onClick={() => setAsignando(true)}>
            <Plus className="size-5" aria-hidden /> Asignar curso
          </Button>
        )}
      </div>

      {isPending ? (
        <Esqueleto className="h-96" />
      ) : isError ? (
        <EstadoVacio titulo="No se pudo cargar la carga del aula" />
      ) : (
        <>
          <Card>
            <Encabezado titulo="Cursos del aula" detalle={`${carga.aula.nombre} · ${carga.aula.periodo}`} />
            {carga.cursos.length === 0 ? (
              <p className="text-texto-suave">Aún no hay cursos. Usa "Asignar curso".</p>
            ) : (
              <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                {carga.cursos.map((c) => {
                  const total = c.asignatura.horas_semanales ?? 0
                  return (
                    <li
                      key={c.id}
                      className="flex flex-col gap-1 rounded-xl border border-borde border-l-6 bg-superficie p-3"
                      style={{ borderLeftColor: color(c.id) }}
                    >
                      <p className="font-extrabold">{c.asignatura.nombre}</p>
                      <p className="text-texto-suave">{c.docente.nombre}</p>
                      <p className={c.horas_programadas < total ? 'text-sm font-bold text-aviso' : 'text-sm font-bold'}>
                        {c.horas_programadas} de {total} h / semana
                      </p>
                      {editable && (
                        <div className="mt-1 flex gap-1">
                          <Button
                            variante="fantasma"
                            tamano="chico"
                            onClick={() => setDocenteDe(c)}
                            aria-label={`Cambiar el docente de ${c.asignatura.nombre}`}
                          >
                            <UserRoundPen className="size-4" aria-hidden /> Docente
                          </Button>
                          <Button
                            variante="fantasma"
                            tamano="chico"
                            aria-label={`Quitar ${c.asignatura.nombre}`}
                            onClick={() =>
                              confirmar({
                                titulo: `¿Quitar ${c.asignatura.nombre} de ${carga.aula.nombre}?`,
                                texto:
                                  'También se quitan sus clases del horario. Si ya tiene evaluaciones o asistencia, no se podrá.',
                                textoConfirmar: 'Sí, quitar',
                                peligro: true,
                                metodo: 'DELETE',
                                ruta: `/cursos/${c.id}`,
                              })
                            }
                          >
                            <Trash2 className="size-4" aria-hidden />
                          </Button>
                        </div>
                      )}
                    </li>
                  )
                })}
              </ul>
            )}
          </Card>

          <Card>
            <Encabezado
              titulo="Horario semanal"
              detalle="Un docente o un aula no pueden tener dos clases a la misma hora."
            >
              {editable && carga.cursos.length > 0 && (
                <Button onClick={() => setBloque('nuevo')}>
                  <Plus className="size-5" aria-hidden /> Agregar clase
                </Button>
              )}
            </Encabezado>
            <Grilla carga={carga} color={color} onElegir={editable ? setBloque : undefined} />
          </Card>
        </>
      )}

      <Ventana abierto={asignando} titulo="Asignar curso" onCerrar={() => setAsignando(false)} conCerrar>
        {asignando && carga && opciones && (
          <FormCurso carga={carga} opciones={opciones} onListo={() => setAsignando(false)} />
        )}
      </Ventana>
      <Ventana
        abierto={docenteDe !== null}
        titulo={`Docente de ${docenteDe?.asignatura.nombre ?? ''}`}
        onCerrar={() => setDocenteDe(null)}
        conCerrar
      >
        {docenteDe && opciones && (
          <FormDocente curso={docenteDe} opciones={opciones} onListo={() => setDocenteDe(null)} />
        )}
      </Ventana>
      <Ventana
        abierto={bloque !== null}
        titulo={bloque === 'nuevo' ? 'Agregar clase' : 'Editar clase'}
        onCerrar={() => setBloque(null)}
        conCerrar
      >
        {bloque !== null && carga && (
          <FormBloque carga={carga} bloque={bloque === 'nuevo' ? null : bloque} onListo={() => setBloque(null)} />
        )}
      </Ventana>
      {dialogo}
    </div>
  )
}

function SelectorPeriodo({ opciones, onCambiar }: { opciones: Opciones; onCambiar: (id: number) => void }) {
  return (
    <Selector
      etiqueta="Periodo"
      opciones={opciones.periodos.map((p) => ({ valor: String(p.id), texto: p.nombre }))}
      value={opciones.periodo_id ?? ''}
      onChange={(e) => onCambiar(Number(e.target.value))}
    />
  )
}

/** Días en columnas y horas de inicio en filas; en el celular, una lista por día */
function Grilla({
  carga,
  color,
  onElegir,
}: {
  carga: Carga
  color: (id: number) => string
  onElegir?: (b: Bloque) => void
}) {
  if (carga.horario.length === 0) return <p className="text-texto-suave">Aún no hay clases en el horario.</p>

  const dias = carga.horario.some((b) => b.dia_semana === 6) ? 6 : 5
  const horas = [...new Set(carga.horario.map((b) => b.hora_inicio))].sort()
  const curso = (id: number) => carga.cursos.find((c) => c.id === id)

  const celda = (b: Bloque) => {
    const c = curso(b.curso_id)
    const contenido = (
      <>
        <span className="block font-bold">{c?.asignatura.nombre}</span>
        <span className="block text-sm text-texto-suave">
          {b.hora_inicio}–{b.hora_fin}
          {b.ambiente ? ` · ${b.ambiente}` : ''}
        </span>
      </>
    )
    const clase = 'block w-full rounded-lg border-l-4 bg-fondo px-2 py-1.5 text-left'
    return onElegir ? (
      <button
        type="button"
        onClick={() => onElegir(b)}
        className={`${clase} cursor-pointer hover:bg-primario-suave`}
        style={{ borderLeftColor: color(b.curso_id) }}
        aria-label={`${c?.asignatura.nombre}, ${DIAS_LARGOS[b.dia_semana - 1]} de ${b.hora_inicio} a ${b.hora_fin}. Editar`}
      >
        {contenido}
      </button>
    ) : (
      <span className={clase} style={{ borderLeftColor: color(b.curso_id) }}>
        {contenido}
      </span>
    )
  }

  return (
    <>
      <div className="hidden overflow-x-auto md:block" tabIndex={0} role="region" aria-label="Horario semanal">
        <table className="w-full table-fixed border-separate border-spacing-1">
          <caption className="sr-only">Horario semanal de {carga.aula.nombre}</caption>
          <thead>
            <tr>
              <th scope="col" className="w-20" />
              {DIAS.slice(0, dias).map((d) => (
                <th key={d} scope="col" className="rounded-lg bg-primario-suave/60 py-2 font-bold">
                  {d}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {horas.map((h) => (
              <tr key={h}>
                <th scope="row" className="text-center font-bold">
                  {h}
                </th>
                {Array.from({ length: dias }, (_, i) => {
                  const b = carga.horario.find((x) => x.dia_semana === i + 1 && x.hora_inicio === h)
                  return (
                    <td key={i} className="align-top">
                      {b && celda(b)}
                    </td>
                  )
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="flex flex-col gap-4 md:hidden">
        {DIAS_LARGOS.slice(0, dias).map((d, i) => {
          const del = carga.horario.filter((b) => b.dia_semana === i + 1)
          return (
            <section key={d} aria-label={d}>
              <h3 className="mb-2 font-extrabold">{d}</h3>
              {del.length ? (
                <ul className="flex flex-col gap-2">
                  {del.map((b) => (
                    <li key={b.id}>{celda(b)}</li>
                  ))}
                </ul>
              ) : (
                <p className="text-sm text-texto-suave">Sin clases</p>
              )}
            </section>
          )
        })}
      </div>
    </>
  )
}

function FormCurso({ carga, opciones, onListo }: { carga: Carga; opciones: Opciones; onListo: () => void }) {
  const libres = opciones.asignaturas.filter((a) => !carga.cursos.some((c) => c.asignatura.id === a.id))
  const [asignatura, setAsignatura] = useState(String(libres[0]?.id ?? ''))
  const [docente, setDocente] = useState('')
  const [errores, setErrores] = useState<Record<string, string>>({})
  const cambio = useCambio()
  const avisos = useAvisos()

  const guardar = async () => {
    if (!docente) return setErrores({ docente_id: 'Elige un docente activo.' })
    try {
      const r = await cambio.mutateAsync({
        metodo: 'POST',
        ruta: '/cursos',
        datos: { aula_id: carga.aula.id, asignatura_id: Number(asignatura), docente_id: Number(docente) },
      })
      avisos.exito(r.mensaje)
      onListo()
    } catch (e) {
      const error = e as { errores?: Record<string, string[]>; message: string }
      if (error.errores && Object.keys(error.errores).length) {
        setErrores(Object.fromEntries(Object.entries(error.errores).map(([k, v]) => [k, v[0]])))
      } else avisos.error(error.message)
    }
  }

  if (libres.length === 0) return <p>Todas las asignaturas activas ya están en {carga.aula.nombre}.</p>

  return (
    <div className="flex flex-col gap-4">
      <Selector
        etiqueta="Asignatura"
        opciones={libres.map((a) => ({ valor: String(a.id), texto: `${a.nombre} (${a.horas_semanales} h)` }))}
        value={asignatura}
        onChange={(e) => setAsignatura(e.target.value)}
      />
      {errores.asignatura_id && (
        <p role="alert" className="-mt-2 text-sm font-semibold text-peligro">
          {errores.asignatura_id}
        </p>
      )}
      <SelectorDocente opciones={opciones} valor={docente} onCambiar={setDocente} error={errores.docente_id} />
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variante="secundario" onClick={onListo}>
          Cancelar
        </Button>
        <Button onClick={guardar} disabled={cambio.isPending}>
          Asignar
        </Button>
      </div>
    </div>
  )
}

function FormDocente({ curso, opciones, onListo }: { curso: CursoAula; opciones: Opciones; onListo: () => void }) {
  const [docente, setDocente] = useState(String(curso.docente.id))
  const cambio = useCambio()
  const avisos = useAvisos()

  const guardar = () =>
    cambio.mutate(
      { metodo: 'PUT', ruta: `/cursos/${curso.id}`, datos: { docente_id: Number(docente) } },
      {
        onSuccess: (r) => {
          avisos.exito(r.mensaje)
          onListo()
        },
        onError: (e) => avisos.error((e as Error).message),
      },
    )

  return (
    <div className="flex flex-col gap-4">
      <p className="text-texto-suave">Se revisa que sus clases no se crucen con las que el nuevo docente ya dicta.</p>
      <SelectorDocente opciones={opciones} valor={docente} onCambiar={setDocente} />
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variante="secundario" onClick={onListo}>
          Cancelar
        </Button>
        <Button onClick={guardar} disabled={cambio.isPending}>
          Guardar
        </Button>
      </div>
    </div>
  )
}

function SelectorDocente({
  opciones,
  valor,
  onCambiar,
  error,
}: {
  opciones: Opciones
  valor: string
  onCambiar: (v: string) => void
  error?: string
}) {
  return (
    <>
      <Selector
        etiqueta="Docente"
        opciones={[
          { valor: '', texto: 'Elige un docente' },
          ...opciones.docentes.map((d) => ({
            valor: String(d.id),
            texto: d.especialidad ? `${d.nombre} · ${d.especialidad}` : d.nombre,
          })),
        ]}
        value={valor}
        onChange={(e) => onCambiar(e.target.value)}
      />
      {error && (
        <p role="alert" className="-mt-2 text-sm font-semibold text-peligro">
          {error}
        </p>
      )}
    </>
  )
}

function FormBloque({ carga, bloque, onListo }: { carga: Carga; bloque: Bloque | null; onListo: () => void }) {
  const cambio = useCambio()
  const avisos = useAvisos()
  const { confirmar, dialogo } = useConfirmar()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<DatosBloque>({
    resolver: zodResolver(esquemaBloque),
    defaultValues: {
      curso_id: String(bloque?.curso_id ?? carga.cursos[0]?.id ?? ''),
      dia_semana: String(bloque?.dia_semana ?? 1),
      hora_inicio: bloque?.hora_inicio ?? '08:00',
      hora_fin: bloque?.hora_fin ?? '09:30',
      ambiente: bloque?.ambiente ?? carga.aula.ambiente ?? '',
    },
  })

  const guardar = handleSubmit(async (d) => {
    const datos = { ...d, curso_id: Number(d.curso_id), dia_semana: Number(d.dia_semana), ambiente: d.ambiente || null }
    try {
      const r = await cambio.mutateAsync(
        bloque
          ? { metodo: 'PUT', ruta: `/horarios/${bloque.id}`, datos }
          : { metodo: 'POST', ruta: '/horarios', datos },
      )
      avisos.exito(r.mensaje)
      onListo()
    } catch (e) {
      const mensaje = marcarErrores(e, setError, ['curso_id', 'dia_semana', 'hora_inicio', 'hora_fin', 'ambiente'])
      if (mensaje) avisos.error(mensaje)
    }
  })

  return (
    <form onSubmit={guardar} noValidate className="flex flex-col gap-4">
      <Selector
        etiqueta="Curso"
        opciones={carga.cursos.map((c) => ({
          valor: String(c.id),
          texto: `${c.asignatura.nombre} · ${c.docente.nombre}`,
        }))}
        {...register('curso_id')}
      />
      <Selector
        etiqueta="Día"
        opciones={DIAS_LARGOS.map((d, i) => ({ valor: String(i + 1), texto: d }))}
        {...register('dia_semana')}
      />
      <div className="grid gap-4 sm:grid-cols-2">
        <Campo
          etiqueta="Hora de inicio"
          type="time"
          min="06:00"
          max="20:00"
          step={300}
          error={errors.hora_inicio?.message}
          {...register('hora_inicio')}
        />
        <Campo
          etiqueta="Hora de fin"
          type="time"
          min="06:00"
          max="20:00"
          step={300}
          error={errors.hora_fin?.message}
          {...register('hora_fin')}
        />
      </div>
      <Campo
        etiqueta="Ambiente (opcional)"
        placeholder="Lab. 1"
        maxLength={40}
        error={errors.ambiente?.message}
        {...register('ambiente')}
      />
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
        {bloque ? (
          <Button
            variante="fantasma"
            className="text-peligro"
            onClick={() =>
              confirmar({
                titulo: '¿Quitar esta clase del horario?',
                texto: `${DIAS_LARGOS[bloque.dia_semana - 1]} de ${bloque.hora_inicio} a ${bloque.hora_fin}.`,
                textoConfirmar: 'Sí, quitar',
                peligro: true,
                metodo: 'DELETE',
                ruta: `/horarios/${bloque.id}`,
                despues: onListo,
              })
            }
          >
            <Trash2 className="size-5" aria-hidden /> Quitar
          </Button>
        ) : (
          <span />
        )}
        <div className="flex flex-col-reverse gap-2 sm:flex-row">
          <Button variante="secundario" onClick={onListo}>
            Cancelar
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'Guardando…' : 'Guardar clase'}
          </Button>
        </div>
      </div>
      {dialogo}
    </form>
  )
}
