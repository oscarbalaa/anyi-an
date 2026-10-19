import { ArrowLeft, BellRing, CalendarCheck, ClipboardList, Download, GraduationCap, ShieldAlert } from 'lucide-react'
import { Link, useSearchParams } from 'react-router'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Card, CardTitulo } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import { TarjetaKpi } from '@/components/ui/tarjeta-kpi'
import { fechaHora, NIVEL } from '@/lib/alertas'
import { descargar, type ErrorApi } from '@/lib/api'
import { fechaCorta, porcentaje } from '@/lib/asistencia'
import { nota, useFicha, type Ficha } from '@/lib/inicio'
import { cn } from '@/lib/utils'

const ACCION: Record<string, string> = {
  GENERADA: 'Alerta generada',
  ASIGNADA_DOCENTE_AULA: 'Asignada',
  REASIGNADA: 'Cambio de encargado',
  NIVEL_ACTUALIZADO: 'Nivel actualizado',
  INTERVENCION: 'Intervención',
  ESTADO_CAMBIADO: 'Cambio de estado',
  CERRADA: 'Alerta cerrada',
}

const GRAVEDAD = { LEVE: 'exito', MODERADA: 'aviso', GRAVE: 'peligro' } as const

// CU-27: ficha del estudiante 360° (mockup admin 17 y docente 11)
export default function Ficha360({ rol }: { rol: 'admin' | 'docente' }) {
  const [parametros] = useSearchParams()
  const matricula = Number(parametros.get('matricula')) || 0
  const { data, isPending, isError, refetch } = useFicha(rol, matricula)
  const avisos = useAvisos()
  const volver = (
    <Link
      to={rol === 'admin' ? '/admin/estudiantes' : '/docente/estudiantes'}
      className="flex items-center gap-2 self-start font-bold text-accion hover:underline"
    >
      <ArrowLeft className="size-5" aria-hidden /> Volver a {rol === 'admin' ? 'Estudiantes' : 'Mis estudiantes'}
    </Link>
  )

  if (!matricula) return <EstadoVacio titulo="Elige a un estudiante para ver su ficha" />
  if (isPending) return <Esqueleto className="mx-auto h-[40rem] max-w-7xl" />
  if (isError)
    return (
      <div className="mx-auto flex max-w-7xl flex-col gap-4">
        {volver}
        <EstadoVacio
          titulo="No se pudo abrir la ficha"
          accion={<Button onClick={() => refetch()}>Reintentar</Button>}
        />
      </div>
    )

  const e = data.estudiante
  const iniciales = e.nombre
    .split(' ')
    .filter((_, i, t) => i === 0 || i === t.length - 2)
    .map((p) => p[0])
    .join('')
  const boleta = () =>
    descargar(
      `/admin/fichas/${e.matricula_id}/boleta`,
      `boleta_${e.codigo.toLowerCase().replaceAll('-', '_')}.pdf`,
    ).catch((err) => avisos.error((err as ErrorApi).message))

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      {volver}
      <header className="flex flex-wrap items-center gap-4">
        <span
          className="grid size-16 shrink-0 place-items-center rounded-full bg-primario-suave text-2xl font-black text-primario-oscuro"
          aria-hidden
        >
          {iniciales}
        </span>
        <div className="min-w-0 flex-1">
          <h1 className="text-3xl font-black sm:text-4xl">{e.nombre}</h1>
          <p className="text-lg text-texto-suave">
            {[
              `${e.aula} ${e.nivel_educativo}`,
              `Código ${e.codigo}`,
              e.edad !== null && `${e.edad} años`,
              e.tutor && `Tutor(a): ${e.tutor}`,
            ]
              .filter(Boolean)
              .join(' · ')}
          </p>
        </div>
        {data.riesgo && (
          <Insignia tono={NIVEL[data.riesgo.nivel].tono} punto>
            Riesgo {NIVEL[data.riesgo.nivel].texto.toLowerCase()}
          </Insignia>
        )}
        {rol === 'admin' && (
          <Button variante="secundario" onClick={boleta}>
            <Download className="size-5" aria-hidden /> Boleta en PDF
          </Button>
        )}
      </header>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <TarjetaKpi
          icono={<GraduationCap />}
          valor={nota(data.kpis.promedio)}
          etiqueta={`Promedio${data.bimestre ? ` · ${data.bimestre.nombre}` : ''}`}
          tono="primario"
        />
        <TarjetaKpi
          icono={<CalendarCheck />}
          valor={porcentaje(data.kpis.asistencia)}
          etiqueta="Asistencia"
          tono="exito"
        />
        <TarjetaKpi icono={<ClipboardList />} valor={data.kpis.pendientes} etiqueta="Tareas pendientes" tono="aviso" />
        <TarjetaKpi
          icono={<ShieldAlert />}
          valor={data.kpis.incidencias}
          etiqueta={data.kpis.incidencias === 1 ? 'Incidencia' : 'Incidencias'}
          tono="peligro"
        />
      </div>

      <Alerta data={data} rol={rol} />

      <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
        <Card className="flex flex-col gap-3 overflow-x-auto" aria-labelledby="titulo-notas">
          <CardTitulo id="titulo-notas">Promedio por curso</CardTitulo>
          <table className="w-full text-left" aria-labelledby="titulo-notas">
            <thead>
              <tr className="border-b border-borde text-sm text-texto-suave">
                <th className="py-2 pr-3 font-semibold">Curso</th>
                {data.bimestres.map((b) => (
                  <th key={b.id} className="px-2 py-2 text-center font-semibold">
                    {b.nombre.split(' ')[0]}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data.notas.map((n) => (
                <tr key={n.curso} className="border-b border-borde last:border-0">
                  <td className="py-2 pr-3">{n.curso}</td>
                  {data.bimestres.map((b) => {
                    const v = n.promedios[b.id] ?? null
                    return (
                      <td
                        key={b.id}
                        className={cn(
                          'px-2 py-2 text-center font-semibold tabular-nums',
                          v !== null && v < 11 && 'text-peligro',
                        )}
                      >
                        {nota(v)}
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
          <p className="text-sm text-texto-suave">Incluye los promedios aún en borrador del bimestre en curso.</p>
        </Card>

        <Card className="flex flex-col gap-3" aria-labelledby="titulo-evolucion">
          <CardTitulo id="titulo-evolucion">Evolución del riesgo</CardTitulo>
          {data.evolucion.length === 0 ? (
            <p className="text-texto-suave">Aún no se calcula su riesgo.</p>
          ) : (
            <ul className="flex flex-col gap-3">
              {data.evolucion.map((v) => (
                <li
                  key={v.bimestre}
                  className="grid grid-cols-[6.5rem_minmax(0,1fr)_3rem] items-center gap-3"
                  title={`${v.bimestre}: riesgo ${NIVEL[v.nivel].texto.toLowerCase()}, ${v.probabilidad} %`}
                >
                  <span className="text-sm">{v.bimestre}</span>
                  <span className="h-3 rounded-full bg-texto/8" aria-hidden>
                    <span
                      className={cn(
                        'block h-full rounded-full',
                        { ALTO: 'bg-peligro', MEDIO: 'bg-aviso', BAJO: 'bg-exito' }[v.nivel],
                      )}
                      style={{ width: `${v.probabilidad}%` }}
                    />
                  </span>
                  <span className="text-right font-bold">{v.probabilidad} %</span>
                </li>
              ))}
            </ul>
          )}
          {data.factores.length > 0 && (
            <ul className="flex flex-col gap-1 border-t border-borde pt-3 text-sm">
              {data.factores.map((f) => (
                <li key={f.titulo}>
                  <strong>{f.titulo}:</strong> <span className="text-texto-suave">{f.descripcion}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>

      <div className="grid items-start gap-6 lg:grid-cols-3">
        <Card className="flex flex-col gap-3" aria-labelledby="titulo-pendientes">
          <CardTitulo id="titulo-pendientes">Tareas vencidas sin entregar</CardTitulo>
          {data.pendientes.length === 0 ? (
            <p className="text-texto-suave">No tiene tareas vencidas.</p>
          ) : (
            <ul className="flex flex-col divide-y divide-borde">
              {data.pendientes.map((p) => (
                <li key={p.id} className="py-2">
                  <span className="block font-semibold">
                    {p.curso} · {p.titulo}
                  </span>
                  <span className="text-sm text-texto-suave">Venció el {fechaCorta(p.fecha_limite)}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
        <Card className="flex flex-col gap-3" aria-labelledby="titulo-incidencias">
          <CardTitulo id="titulo-incidencias">Incidencias</CardTitulo>
          {data.incidencias.length === 0 ? (
            <p className="text-texto-suave">Sin incidencias en el periodo.</p>
          ) : (
            <ul className="flex flex-col divide-y divide-borde">
              {data.incidencias.map((i) => (
                <li key={i.id} className="flex flex-col gap-1 py-2">
                  <span className="flex flex-wrap items-center gap-2">
                    <strong>{i.tipo}</strong>
                    <Insignia tono={GRAVEDAD[i.gravedad as keyof typeof GRAVEDAD] ?? 'neutro'}>
                      {i.gravedad.toLowerCase()}
                    </Insignia>
                  </span>
                  <span className="text-sm">{i.descripcion}</span>
                  <span className="text-sm text-texto-suave">
                    {fechaCorta(i.fecha)} · {i.registrado_por}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Card>
        <Card className="flex flex-col gap-3" aria-labelledby="titulo-apoderados">
          <CardTitulo id="titulo-apoderados">Apoderados</CardTitulo>
          {data.apoderados.length === 0 ? (
            <p className="text-texto-suave">Sin apoderados registrados.</p>
          ) : (
            <ul className="flex flex-col divide-y divide-borde">
              {data.apoderados.map((a) => (
                <li key={a.nombre} className="py-2">
                  <span className="flex flex-wrap items-center gap-2 font-semibold">
                    {a.nombre} {a.principal && <Insignia tono="primario">Principal</Insignia>}
                  </span>
                  <span className="text-sm text-texto-suave">
                    {[a.parentesco, a.celular, a.correo].filter(Boolean).join(' · ') || 'Sin datos de contacto'}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>
    </div>
  )
}

function Alerta({ data, rol }: { data: Ficha; rol: 'admin' | 'docente' }) {
  const a = data.alerta
  return (
    <Card className="flex flex-col gap-3" aria-labelledby="titulo-alerta">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <CardTitulo id="titulo-alerta">Seguimiento de alerta</CardTitulo>
        {a && (
          <Button asChild variante="secundario" tamano="chico">
            <Link to={`/${rol}/alertas?id=${a.id}`}>
              <BellRing className="size-4" aria-hidden /> Abrir la alerta
            </Link>
          </Button>
        )}
      </div>
      {!a ? (
        <p className="text-texto-suave">No tiene alertas de riesgo.</p>
      ) : (
        <ol className="flex flex-col gap-2" aria-label="Historial de la alerta">
          {a.historial.map((h, i) => (
            <li key={i} className="flex flex-col border-l-2 border-primario pl-3">
              <span className="font-semibold">{ACCION[h.accion] ?? h.accion}</span>
              {h.detalle && <span className="text-sm">{h.detalle}</span>}
              <span className="text-sm text-texto-suave">{fechaHora(h.fecha)}</span>
            </li>
          ))}
          <li className="text-sm text-texto-suave">
            A cargo de {a.responsable} · estado: {a.estado.toLowerCase().replace('_', ' ')}
          </li>
        </ol>
      )}
    </Card>
  )
}
