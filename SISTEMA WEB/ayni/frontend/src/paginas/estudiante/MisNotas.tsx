import { ArrowLeft, ArrowRight, BarChart3, CheckCircle2, FileText, TrendingDown, TrendingUp } from 'lucide-react'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { Button } from '@/components/ui/button'
import { Card, CardTitulo } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import { Selector } from '@/components/ui/selector'
import { TarjetaKpi } from '@/components/ui/tarjeta-kpi'
import { fechaCorta } from '@/lib/asistencia'
import { ESTADO_ENTREGA, nota, notaSobre, useMisNotas, useNotasCurso, type NotasCurso } from '@/lib/evaluaciones'

// RF-47 (mockups estudiante 04 y 05)
export default function MisNotas() {
  const [parametros] = useSearchParams()
  const curso = Number(parametros.get('curso')) || null
  return curso ? <DetalleCurso id={curso} bimestreInicial={Number(parametros.get('bimestre')) || null} /> : <Resumen />
}

function Resumen() {
  const [bimestre, setBimestre] = useState<number | null>(null)
  const { data, isPending, isError, refetch } = useMisNotas(bimestre)

  if (isPending) return <Esqueleto className="mx-auto h-[32rem] max-w-6xl" />
  if (isError)
    return (
      <EstadoVacio
        titulo="No se pudo cargar tus notas"
        accion={<Button onClick={() => refetch()}>Reintentar</Button>}
      />
    )
  if (!data.bimestre) return <EstadoVacio titulo="Aún no tienes matrícula en el periodo en curso" />

  const publicados = data.cursos.filter((c) => c.publicado)
  const diferencia =
    data.promedio !== null && data.anterior?.promedio != null
      ? Math.round((data.promedio - data.anterior.promedio) * 10) / 10
      : null

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-3xl font-black sm:text-4xl">Mis notas</h1>
          <p className="mt-1 text-lg text-texto-suave">Calificaciones publicadas del {data.bimestre.nombre}</p>
        </div>
        <Selector
          etiqueta="Bimestre"
          className="w-48"
          value={String(data.bimestre.id)}
          onChange={(e) => setBimestre(Number(e.target.value))}
          opciones={data.bimestres.map((b) => ({ valor: String(b.id), texto: b.nombre }))}
        />
      </header>

      <div className="grid gap-4 sm:grid-cols-3">
        <TarjetaKpi icono={<BarChart3 />} valor={nota(data.promedio)} etiqueta="Promedio del bimestre" />
        <TarjetaKpi icono={<FileText />} valor={publicados.length} etiqueta="Cursos con notas" tono="exito" />
        <TarjetaKpi
          icono={diferencia !== null && diferencia < 0 ? <TrendingDown /> : <TrendingUp />}
          valor={diferencia === null ? '—' : `${diferencia > 0 ? '+' : ''}${nota(diferencia)}`}
          etiqueta={data.anterior ? `Respecto al ${data.anterior.nombre}` : 'Primer bimestre'}
          tono={diferencia !== null && diferencia < 0 ? 'peligro' : 'aviso'}
        />
      </div>

      <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <Card>
          <CardTitulo>Notas por curso</CardTitulo>
          <ul className="mt-4 divide-y divide-borde">
            {data.cursos.map((c) => (
              <li
                key={c.id}
                className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 py-3 sm:grid-cols-[minmax(0,10rem)_4rem_minmax(0,1fr)_auto]"
              >
                <span className="font-semibold">{c.asignatura}</span>
                <span className="text-right text-xl font-black sm:text-left">
                  {c.publicado ? nota(c.promedio) : '—'}
                  {c.literal && <span className="ml-1 text-sm font-semibold text-texto-suave">{c.literal}</span>}
                </span>
                <span className="col-span-2 sm:col-span-1">
                  {c.publicado && c.promedio !== null ? (
                    <span className="flex items-center gap-2">
                      <span className="h-2.5 flex-1 rounded-full bg-texto/8" aria-hidden>
                        <span
                          className="block h-full rounded-full bg-primario"
                          style={{ width: `${(c.promedio / 20) * 100}%` }}
                        />
                      </span>
                      <span className="w-12 text-right text-sm text-texto-suave">
                        {Math.round((c.promedio / 20) * 100)} %
                      </span>
                    </span>
                  ) : (
                    <Insignia>Aún no publicado</Insignia>
                  )}
                </span>
                <Button
                  asChild
                  variante="fantasma"
                  tamano="chico"
                  className="col-span-2 justify-self-start sm:col-span-1"
                >
                  <Link
                    to={`?curso=${c.id}&bimestre=${data.bimestre!.id}`}
                    aria-label={`Ver detalle de ${c.asignatura}`}
                  >
                    Ver detalle <ArrowRight className="size-4" aria-hidden />
                  </Link>
                </Button>
              </li>
            ))}
          </ul>
        </Card>

        <Card>
          <CardTitulo>Últimas publicaciones</CardTitulo>
          {data.ultimas.length === 0 ? (
            <p className="mt-3 text-texto-suave">Cuando tus docentes publiquen notas, aparecerán aquí.</p>
          ) : (
            <ul className="mt-4 divide-y divide-borde">
              {data.ultimas.map((u) => (
                <li key={u.id} className="flex items-center justify-between gap-3 py-3">
                  <span className="min-w-0">
                    <span className="block font-bold">
                      {u.titulo} <span className="font-normal text-texto-suave">· {u.curso}</span>
                    </span>
                    <span className="text-sm text-texto-suave">Calificado el {fechaCorta(u.fecha)}</span>
                  </span>
                  <span className="rounded-lg bg-exito/10 px-3 py-1 text-lg font-black text-exito">
                    {notaSobre(u.nota, u.puntaje_maximo)}
                  </span>
                </li>
              ))}
            </ul>
          )}
          {data.escala && (
            <p className="mt-4 text-sm text-texto-suave">
              Escala: {data.escala.map((e) => `${e.codigo} desde ${nota(e.desde)}`).join(' · ')}
            </p>
          )}
        </Card>
      </div>
    </div>
  )
}

function DetalleCurso({ id, bimestreInicial }: { id: number; bimestreInicial: number | null }) {
  const [bimestre, setBimestre] = useState<number | null>(bimestreInicial)
  const { data, isPending, isError, error } = useNotasCurso(id, bimestre)

  if (isPending) return <Esqueleto className="mx-auto h-[32rem] max-w-6xl" />
  if (isError) return <EstadoVacio titulo="No se encontró el curso" descripcion={error.message} />

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <Link to="/estudiante/notas" className="inline-flex items-center gap-2 font-bold text-accion hover:underline">
            <ArrowLeft className="size-4" aria-hidden /> Volver a Mis notas
          </Link>
          <h1 className="mt-2 text-3xl font-black sm:text-4xl">{data.curso.asignatura}</h1>
          <p className="mt-1 text-lg text-texto-suave">
            {data.curso.aula} · Docente: {data.curso.docente} · {data.bimestre.nombre}
          </p>
        </div>
        <Selector
          etiqueta="Bimestre"
          className="w-48"
          value={String(data.bimestre.id)}
          onChange={(e) => setBimestre(Number(e.target.value))}
          opciones={data.bimestres.map((b) => ({ valor: String(b.id), texto: b.nombre }))}
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <TarjetaKpi
          icono={<BarChart3 />}
          valor={data.publicado ? nota(data.promedio) : '—'}
          etiqueta={data.literal ? `Promedio · ${data.literal}` : 'Promedio'}
        />
        <TarjetaKpi
          icono={<FileText />}
          valor={data.resumen.calificadas}
          etiqueta="Evaluaciones calificadas"
          tono="exito"
        />
        <TarjetaKpi
          icono={<CheckCircle2 />}
          valor={`${data.resumen.entregadas} de ${data.resumen.por_entregar}`}
          etiqueta="Entregas"
          tono="aviso"
        />
      </div>

      {!data.publicado && (
        <p className="rounded-xl bg-texto/6 px-4 py-3" role="note">
          Tu docente aún no publica las notas de este bimestre. Mientras tanto ves tus actividades y entregas.
        </p>
      )}

      <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <Card>
          <CardTitulo>Evaluaciones</CardTitulo>
          {data.evaluaciones.length === 0 ? (
            <p className="mt-3 text-texto-suave">Aún no hay evaluaciones asignadas.</p>
          ) : (
            <ul className="mt-4 divide-y divide-borde">
              {data.evaluaciones.map((e) => (
                <li key={e.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                  <span className="min-w-0">
                    <Link to={`/estudiante/actividades?id=${e.id}`} className="block font-bold hover:underline">
                      {e.titulo}
                    </Link>
                    <span className="text-sm text-texto-suave">
                      {fechaCorta(e.fecha_limite)} · {e.peso_porcentaje} % del bimestre
                    </span>
                  </span>
                  {e.nota !== null ? (
                    <span className="text-xl font-black">{notaSobre(e.nota, e.puntaje_maximo)}</span>
                  ) : (
                    <Insignia tono={ESTADO_ENTREGA[e.estado].tono}>{ESTADO_ENTREGA[e.estado].texto}</Insignia>
                  )}
                </li>
              ))}
            </ul>
          )}
        </Card>
        <div className="flex flex-col gap-6">
          <Evolucion datos={data} />
          <Card>
            <CardTitulo>Próxima evaluación</CardTitulo>
            {data.proxima ? (
              <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                <span>
                  <span className="block font-bold">{data.proxima.titulo}</span>
                  <span className="text-texto-suave">Vence el {fechaCorta(data.proxima.fecha_limite)}</span>
                </span>
                <Button asChild variante="secundario" tamano="chico">
                  <Link to={`/estudiante/actividades?id=${data.proxima.id}`}>Ver indicaciones</Link>
                </Button>
              </div>
            ) : (
              <p className="mt-3 text-texto-suave">No hay evaluaciones por venir.</p>
            )}
          </Card>
        </div>
      </div>
    </div>
  )
}

/** Promedio publicado de cada bimestre (escala 0 a 20) */
function Evolucion({ datos }: { datos: NotasCurso }) {
  const puntos = datos.evolucion
  const ancho = 300
  const alto = 150
  const x = (i: number) => 30 + (i * (ancho - 50)) / Math.max(1, puntos.length - 1)
  const y = (n: number) => alto - 20 - (n / 20) * (alto - 40)
  const conNota = puntos.map((p, i) => ({ ...p, i })).filter((p) => p.promedio !== null)
  const linea = conNota.map((p) => `${x(p.i)},${y(p.promedio!)}`).join(' ')

  return (
    <Card>
      <CardTitulo>Evolución por bimestre</CardTitulo>
      {conNota.length === 0 ? (
        <p className="mt-3 text-texto-suave">Aún no hay promedios publicados.</p>
      ) : (
        <svg
          viewBox={`0 0 ${ancho} ${alto}`}
          className="mt-3 w-full"
          role="img"
          aria-label={puntos.map((p) => `${p.bimestre}: ${nota(p.promedio)}`).join(', ')}
        >
          {[0, 10, 20].map((n) => (
            <g key={n}>
              <line x1={30} x2={ancho - 20} y1={y(n)} y2={y(n)} className="stroke-borde" strokeDasharray="3 3" />
              <text x={22} y={y(n) + 4} textAnchor="end" className="fill-texto-suave text-[10px]">
                {n}
              </text>
            </g>
          ))}
          <polyline points={linea} fill="none" className="stroke-primario" strokeWidth={2} />
          {conNota.map((p) => (
            <g key={p.i}>
              <circle
                cx={x(p.i)}
                cy={y(p.promedio!)}
                r={4}
                className="fill-primario stroke-superficie"
                strokeWidth={2}
              />
              <text x={x(p.i)} y={y(p.promedio!) - 9} textAnchor="middle" className="fill-texto text-[11px] font-bold">
                {nota(p.promedio)}
              </text>
            </g>
          ))}
          {puntos.map((p, i) => (
            <text key={p.bimestre} x={x(i)} y={alto - 4} textAnchor="middle" className="fill-texto-suave text-[10px]">
              {p.bimestre.replace(' Bimestre', '')}
            </text>
          ))}
        </svg>
      )}
    </Card>
  )
}
