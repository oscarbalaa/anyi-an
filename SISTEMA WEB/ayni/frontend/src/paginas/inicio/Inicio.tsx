import {
  ArrowRight,
  BellRing,
  CalendarCheck,
  ChartColumn,
  ClipboardList,
  Clock,
  Download,
  FileText,
  GraduationCap,
  NotebookPen,
  ShieldAlert,
  Users,
} from 'lucide-react'
import { Link } from 'react-router'
import { Ilustracion } from '@/components/Ilustracion'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Card, CardTitulo } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia, type Tono } from '@/components/ui/insignia'
import { TarjetaKpi } from '@/components/ui/tarjeta-kpi'
import { NIVEL } from '@/lib/alertas'
import { descargar, type ErrorApi } from '@/lib/api'
import { fechaCorta, fechaLarga, porcentaje } from '@/lib/asistencia'
import { IMG } from '@/lib/imagenes'
import {
  nota,
  saludoDelDia,
  useInicio,
  type Avance,
  type InicioAdmin,
  type InicioDocente,
  type InicioEstudiante,
} from '@/lib/inicio'
import { useSesion } from '@/lib/sesion-contexto'
import { cn } from '@/lib/utils'
import type { Rol } from '@/layout/roles'

// CU-27: inicio de cada rol (mockups 01 de cada rol)
export default function Inicio({ rol }: { rol: Rol }) {
  if (rol === 'admin') return <InicioDeAdmin />
  if (rol === 'docente') return <InicioDeDocente />
  return <InicioDeEstudiante />
}

function Cargando() {
  return <Esqueleto className="mx-auto h-[36rem] max-w-7xl" />
}

function ErrorInicio({ reintentar }: { reintentar: () => void }) {
  return <EstadoVacio titulo="No se pudo cargar el inicio" accion={<Button onClick={reintentar}>Reintentar</Button>} />
}

const subtitulo = (
  d: { fecha: string; periodo: string | null; bimestre: { nombre: string } | null },
  ...extra: (string | null | undefined)[]
) =>
  [fechaLarga(d.fecha), d.periodo && `Periodo ${d.periodo} en curso`, d.bimestre?.nombre, ...extra]
    .filter(Boolean)
    .join(' · ')

const SEVERIDAD: Record<InicioAdmin['atencion'][number]['severidad'], { texto: string; tono: Tono }> = {
  CRITICO: { texto: 'Crítico', tono: 'peligro' },
  ATENCION: { texto: 'Atención', tono: 'aviso' },
  PENDIENTE: { texto: 'Pendiente', tono: 'info' },
}

function InicioDeAdmin() {
  const { data, isPending, isError, refetch } = useInicio<InicioAdmin>('admin')
  if (isPending) return <Cargando />
  if (isError) return <ErrorInicio reintentar={() => refetch()} />
  const k = data.kpis

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <p className="relative overflow-hidden rounded-2xl border border-borde bg-primario-suave/50 px-6 py-5 text-xl font-extrabold text-primario-oscuro sm:text-2xl">
        Información clara para decidir mejor
      </p>
      <header>
        <h1 className="text-3xl font-black sm:text-4xl">
          {saludoDelDia()}, {data.nombre}
        </h1>
        <p className="mt-1 text-lg text-texto-suave">{subtitulo(data)}</p>
      </header>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <TarjetaKpi icono={<Users />} valor={k.estudiantes} etiqueta="Estudiantes matriculados" tono="primario" />
        <TarjetaKpi
          icono={<GraduationCap />}
          valor={k.docentes}
          etiqueta={`Docentes · ${k.coordinadores} ${k.coordinadores === 1 ? 'coordinador' : 'coordinadores'}`}
          tono="exito"
        />
        <TarjetaKpi
          icono={<BellRing />}
          valor={k.alertas_estudiantiles}
          etiqueta="Alertas estudiantiles abiertas"
          tono="peligro"
        />
        <TarjetaKpi
          icono={<ShieldAlert />}
          valor={k.alertas_administrativas}
          etiqueta="Alertas administrativas"
          tono="aviso"
        />
      </div>

      <Card className="flex flex-col gap-3" aria-labelledby="titulo-atencion">
        <CardTitulo id="titulo-atencion">Necesitan su atención ({data.atencion.length})</CardTitulo>
        {data.atencion.length === 0 ? (
          <p className="text-texto-suave">Nada pendiente por ahora.</p>
        ) : (
          <ul className="flex flex-col divide-y divide-borde">
            {data.atencion.map((a) => (
              <li key={a.id} className="flex flex-wrap items-center gap-3 py-3">
                <span className="min-w-0 flex-1 basis-64">
                  <span className="block font-semibold">{a.titulo}</span>
                  {a.detalle && <span className="block text-sm text-texto-suave">{a.detalle}</span>}
                </span>
                <Insignia tono={SEVERIDAD[a.severidad].tono} punto>
                  {SEVERIDAD[a.severidad].texto}
                </Insignia>
                <Button asChild variante="secundario" tamano="chico">
                  <Link to={a.enlace} aria-label={`Resolver: ${a.titulo}`}>
                    Resolver <ArrowRight className="size-4" aria-hidden />
                  </Link>
                </Button>
              </li>
            ))}
          </ul>
        )}
        <Link
          to="/admin/alertas?pestana=administrativas"
          className="self-end text-sm font-bold text-accion hover:underline"
        >
          Ver todas las alertas administrativas
        </Link>
      </Card>

      {data.avance && data.bimestre && <AvanceRegistro avance={data.avance} bimestre={data.bimestre.nombre} />}
    </div>
  )
}

function AvanceRegistro({ avance, bimestre }: { avance: Avance; bimestre: string }) {
  const filas = [
    {
      clave: 'asistencia',
      texto: 'Asistencia',
      detalle: 'clases dictadas con asistencia',
      icono: CalendarCheck,
      color: 'bg-primario',
    },
    { clave: 'notas', texto: 'Notas', detalle: 'notas de evaluaciones vencidas', icono: FileText, color: 'bg-exito' },
    {
      clave: 'promedios',
      texto: 'Promedios',
      detalle: 'cursos con promedios publicados',
      icono: ChartColumn,
      color: 'bg-aviso',
    },
  ] as const
  return (
    <Card className="flex flex-col gap-4" aria-labelledby="titulo-avance">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <CardTitulo id="titulo-avance">Avance de registro del {bimestre}</CardTitulo>
        <Link to="/admin/asistencia" className="text-sm font-bold text-accion hover:underline">
          Ver detalle de asistencia
        </Link>
      </div>
      <ul className="grid gap-5 md:grid-cols-3">
        {filas.map((f) => {
          const a = avance[f.clave]
          return (
            <li key={f.clave} className="flex items-center gap-3" title={`${a.hechas} de ${a.total} ${f.detalle}`}>
              <span
                className="grid size-11 shrink-0 place-items-center rounded-full bg-primario-suave text-primario-oscuro"
                aria-hidden
              >
                <f.icono className="size-5" />
              </span>
              <span className="min-w-0 flex-1">
                <span className="flex items-baseline justify-between gap-2">
                  <span className="font-semibold">{f.texto}</span>
                  <span className="text-xl font-extrabold">{a.porcentaje === null ? '—' : `${a.porcentaje} %`}</span>
                </span>
                <span
                  className="mt-1 block h-2 rounded-full bg-texto/8"
                  role="progressbar"
                  aria-label={`${f.texto}: ${a.hechas} de ${a.total} ${f.detalle}`}
                  aria-valuenow={a.porcentaje ?? 0}
                  aria-valuemin={0}
                  aria-valuemax={100}
                >
                  <span
                    className={cn('block h-full rounded-full', f.color)}
                    style={{ width: `${a.porcentaje ?? 0}%` }}
                  />
                </span>
                <span className="text-sm text-texto-suave">
                  {a.hechas} de {a.total}
                </span>
              </span>
            </li>
          )
        })}
      </ul>
    </Card>
  )
}

const ICONO_TAREA = { asistencia: CalendarCheck, calificar: NotebookPen, seguimiento: BellRing } as const

function InicioDeDocente() {
  const { data, isPending, isError, refetch } = useInicio<InicioDocente>('docente')
  if (isPending) return <Cargando />
  if (isError) return <ErrorInicio reintentar={() => refetch()} />
  const c = data.coordinacion

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <header>
        <h1 className="text-3xl font-black sm:text-4xl">
          {saludoDelDia()}, {data.nombre}
        </h1>
        <p className="mt-1 text-lg text-texto-suave">{subtitulo({ ...data, periodo: null }, data.detalle)}</p>
      </header>
      <section className="relative flex min-h-36 items-center overflow-hidden rounded-2xl border border-borde bg-primario-suave/40 px-6 py-6 sm:pr-56">
        <p className="text-2xl leading-tight font-black sm:text-3xl">
          Hoy acompañamos con <span className="text-accion">información clara.</span>
        </p>
        <Ilustracion
          imagen={IMG.tambo}
          sizes="200px"
          className="pointer-events-none absolute -right-2 -bottom-10 hidden h-auto w-48 sm:block"
        />
      </section>

      <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <Card className="flex flex-col gap-3" aria-labelledby="titulo-tareas">
          <CardTitulo id="titulo-tareas">
            {data.tareas.length === 0
              ? 'No tienes tareas pendientes'
              : `Tienes ${data.tareas.length} ${data.tareas.length === 1 ? 'tarea pendiente' : 'tareas pendientes'}`}
          </CardTitulo>
          {data.tareas.length === 0 ? (
            <p className="text-texto-suave">Tu asistencia y tus notas están al día. ¡Buen trabajo!</p>
          ) : (
            <ul className="flex flex-col divide-y divide-borde" aria-label="Tareas pendientes">
              {data.tareas.map((t, i) => {
                const Icono = ICONO_TAREA[t.tipo]
                return (
                  <li key={i} className="flex items-center gap-3 py-3">
                    <span
                      className="grid size-11 shrink-0 place-items-center rounded-full bg-primario-suave text-primario-oscuro"
                      aria-hidden
                    >
                      <Icono className="size-5" />
                    </span>
                    <span className="min-w-0 flex-1">
                      <span className="block font-bold">{t.titulo}</span>
                      <span className="block text-sm text-texto-suave">
                        {t.detalle} · {t.extra}
                      </span>
                    </span>
                    <Button asChild variante="secundario" tamano="chico">
                      <Link to={t.enlace} aria-label={`Ir: ${t.titulo} (${t.detalle})`}>
                        Ir <ArrowRight className="size-4" aria-hidden />
                      </Link>
                    </Button>
                  </li>
                )
              })}
            </ul>
          )}
        </Card>

        {c ? (
          <Card className="flex flex-col gap-4" aria-labelledby="titulo-coordinacion">
            <CardTitulo id="titulo-coordinacion">Coordinación de {c.ambito}</CardTitulo>
            <dl className="grid grid-cols-3 divide-x divide-borde text-center">
              {(['ALTO', 'MEDIO', 'BAJO'] as const).map((n) => (
                <div key={n} className="px-2">
                  <dd
                    className={cn(
                      'text-3xl font-black',
                      { ALTO: 'text-peligro', MEDIO: 'text-aviso', BAJO: 'text-exito' }[n],
                    )}
                  >
                    {c[n]}
                  </dd>
                  <dt className="text-sm text-texto-suave">{NIVEL[n].texto}</dt>
                </div>
              ))}
            </dl>
            {c.destacado && (
              <p className="rounded-xl bg-fondo px-3 py-2 text-sm">
                <strong>{c.destacado.nombre}</strong>
                {c.destacado.factor_principal && (
                  <span className="block text-texto-suave">{c.destacado.factor_principal}</span>
                )}
              </p>
            )}
            <Button asChild variante="secundario">
              <Link to="/docente/coordinacion">
                <ChartColumn className="size-5" aria-hidden /> Ver panel de mi ámbito
              </Link>
            </Button>
          </Card>
        ) : (
          <Card className="flex flex-col gap-2">
            <CardTitulo>Mis estudiantes</CardTitulo>
            <p className="text-texto-suave">
              Revisa el promedio, la asistencia y el nivel de riesgo de los estudiantes de tus cursos.
            </p>
            <Button asChild variante="secundario" className="self-start">
              <Link to="/docente/estudiantes">
                <Users className="size-5" aria-hidden /> Ver mis estudiantes
              </Link>
            </Button>
          </Card>
        )}
      </div>

      <Card className="flex flex-col gap-3" aria-labelledby="titulo-hoy">
        <CardTitulo id="titulo-hoy">Mis clases de hoy</CardTitulo>
        {data.hoy.length === 0 ? (
          <p className="text-texto-suave">Hoy no tienes clases.</p>
        ) : (
          <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {data.hoy.map((h) => (
              <li
                key={`${h.curso_id}-${h.inicio}`}
                className="flex items-start gap-3 rounded-xl border border-borde p-3"
              >
                <span className="text-2xl font-black tabular-nums">{h.inicio}</span>
                <span className="min-w-0 leading-tight">
                  <span className="block font-bold">{h.curso}</span>
                  <span className="text-sm text-texto-suave">
                    {h.ambiente ?? 'Su aula'} · {h.registrada ? 'Asistencia registrada' : 'Asistencia pendiente'}
                  </span>
                </span>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  )
}

function InicioDeEstudiante() {
  const { data, isPending, isError, refetch } = useInicio<InicioEstudiante>('estudiante')
  const avisos = useAvisos()
  const { usuario } = useSesion()
  if (isPending) return <Cargando />
  if (isError) return <ErrorInicio reintentar={() => refetch()} />
  const n = data.pendientes

  // Mismo nombre que propone el servidor: boleta_e_2026_001.pdf
  const archivo = `boleta_${(usuario?.usuario ?? 'estudiante').toLowerCase().replaceAll('-', '_')}.pdf`
  const boleta = () => descargar('/estudiante/boleta', archivo).catch((e) => avisos.error((e as ErrorApi).message))

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-3xl font-black sm:text-4xl">Hola, {data.nombre}</h1>
          <p className="mt-1 text-lg text-texto-suave">
            {[data.aula, data.bimestre?.nombre, data.tutor && `Tutor(a): ${data.tutor}`].filter(Boolean).join(' · ')}
          </p>
        </div>
        {data.aula && (
          <Button variante="secundario" onClick={boleta}>
            <Download className="size-5" aria-hidden /> Descargar mi boleta
          </Button>
        )}
      </header>
      <section className="relative flex min-h-32 items-center overflow-hidden rounded-2xl border border-borde bg-primario-suave/40 px-6 py-6 sm:pr-52">
        <p className="text-2xl leading-tight font-black sm:text-3xl">
          {n === 0 ? (
            'Estás al día con tus actividades'
          ) : (
            <>
              Tienes <span className="text-accion">{n === 1 ? '1 actividad' : `${n} actividades`}</span> por entregar
            </>
          )}
        </p>
        <Ilustracion
          imagen={IMG.piki}
          sizes="180px"
          className="pointer-events-none absolute right-0 -bottom-8 hidden h-auto w-44 sm:block"
        />
      </section>

      <div className="grid gap-4 sm:grid-cols-3">
        <TarjetaKpi
          icono={<ChartColumn />}
          valor={nota(data.promedio)}
          etiqueta={data.promedio_bimestre ? `Mi promedio · ${data.promedio_bimestre}` : 'Mi promedio'}
          tono="primario"
        />
        <TarjetaKpi icono={<Users />} valor={porcentaje(data.asistencia)} etiqueta="Mi asistencia" tono="exito" />
        <TarjetaKpi icono={<ClipboardList />} valor={n} etiqueta="Actividades pendientes" tono="aviso" />
      </div>

      <div className="grid items-start gap-6 lg:grid-cols-2">
        <Card className="flex flex-col gap-3" aria-labelledby="titulo-notas">
          <CardTitulo id="titulo-notas">Mis notas por curso</CardTitulo>
          <ul className="flex flex-col divide-y divide-borde">
            {data.cursos.map((c) => (
              <li key={c.id} className="flex items-center justify-between gap-3 py-2.5">
                <span>{c.asignatura}</span>
                <span className={cn('font-extrabold', c.promedio !== null && c.promedio < 11 && 'text-peligro')}>
                  {c.promedio === null ? (
                    <span className="text-sm font-normal text-texto-suave">Sin publicar</span>
                  ) : (
                    nota(c.promedio)
                  )}
                </span>
              </li>
            ))}
          </ul>
          <Link to="/estudiante/notas" className="self-end text-sm font-bold text-accion hover:underline">
            Ver mis notas
          </Link>
        </Card>
        <div className="flex flex-col gap-6">
          <Card className="flex flex-col gap-3" aria-labelledby="titulo-por-entregar">
            <CardTitulo id="titulo-por-entregar">Por entregar</CardTitulo>
            {data.por_entregar.length === 0 ? (
              <p className="text-texto-suave">No tienes actividades pendientes.</p>
            ) : (
              <ul className="flex flex-col divide-y divide-borde">
                {data.por_entregar.map((a) => (
                  <li key={a.id}>
                    <Link
                      to={`/estudiante/actividades?id=${a.id}`}
                      className="flex items-center justify-between gap-3 py-2.5 hover:text-accion"
                    >
                      <span className="min-w-0 leading-tight">
                        <span className="block font-semibold">{a.titulo}</span>
                        <span className="text-sm text-texto-suave">{a.curso}</span>
                      </span>
                      <span className="flex shrink-0 items-center gap-1 text-sm font-bold text-accion">
                        <Clock className="size-4" aria-hidden /> {fechaCorta(a.fecha_limite)}
                      </span>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </Card>
          <Card className="flex flex-col gap-3" aria-labelledby="titulo-hoy">
            <CardTitulo id="titulo-hoy">Hoy</CardTitulo>
            {data.hoy.length === 0 ? (
              <p className="text-texto-suave">Hoy no tienes clases.</p>
            ) : (
              <ul className="flex flex-col divide-y divide-borde">
                {data.hoy.map((h) => (
                  <li key={`${h.curso}-${h.inicio}`} className="flex gap-4 py-2">
                    <span className="w-12 font-bold tabular-nums">{h.inicio}</span>
                    <span>
                      {h.curso}
                      {h.ambiente && ` · ${h.ambiente}`}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>
      </div>
    </div>
  )
}
