import { CalendarCheck, ChevronLeft, ChevronRight, Clock, FileCheck, FileX } from 'lucide-react'
import { useMemo, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardTitulo } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import { Selector } from '@/components/ui/selector'
import { TablaDatos } from '@/components/ui/tabla-datos'
import { TarjetaKpi } from '@/components/ui/tarjeta-kpi'
import {
  NOMBRE_ESTADO,
  TONO_ESTADO,
  fechaCorta,
  nombreMes,
  porcentaje,
  useMiAsistencia,
  type EstadoAsistencia,
  type MiAsistencia as Datos,
} from '@/lib/asistencia'
import { cn } from '@/lib/utils'

const PUNTO: Record<EstadoAsistencia, string> = { P: 'bg-exito', F: 'bg-peligro', T: 'bg-aviso', J: 'bg-info' }

// Para el calendario: el día toma el estado más serio de sus clases
const GRAVEDAD: EstadoAsistencia[] = ['F', 'J', 'T', 'P']

// CU-15 (mockup estudiante 06)
export default function MiAsistencia() {
  const [bimestre, setBimestre] = useState<number | null>(null)
  const { data, isPending, isError, refetch } = useMiAsistencia(bimestre)

  if (isPending) return <Esqueleto className="mx-auto h-[36rem] max-w-6xl" />
  if (isError)
    return (
      <EstadoVacio
        titulo="No se pudo cargar tu asistencia"
        accion={<Button onClick={() => refetch()}>Reintentar</Button>}
      />
    )

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-3xl font-black sm:text-4xl">Mi asistencia</h1>
          <p className="mt-1 text-lg text-texto-suave">
            {data.bimestre
              ? `Resumen del ${data.bimestre.nombre} · ${data.aula}`
              : 'Aún no tienes matrícula en el periodo en curso.'}
          </p>
        </div>
        {data.bimestres.length > 0 && (
          <Selector
            etiqueta="Bimestre"
            className="w-48"
            value={String(data.bimestre?.id ?? '')}
            onChange={(e) => setBimestre(Number(e.target.value))}
            opciones={data.bimestres.map((b) => ({ valor: String(b.id), texto: b.nombre }))}
          />
        )}
      </header>

      {data.resumen && <Contenido datos={data} />}
    </div>
  )
}

function Contenido({ datos }: { datos: Datos }) {
  const r = datos.resumen!
  if (r.clases === 0)
    return (
      <Card>
        <EstadoVacio
          titulo="Aún no hay asistencia registrada"
          descripcion="Cuando tus docentes registren la asistencia de este bimestre, la verás aquí."
        />
      </Card>
    )

  return (
    <>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <TarjetaKpi icono={<CalendarCheck />} valor={porcentaje(r.porcentaje)} etiqueta="Asistencia" tono="exito" />
        <TarjetaKpi icono={<FileX />} valor={r.faltas} etiqueta={r.faltas === 1 ? 'Falta' : 'Faltas'} tono="peligro" />
        <TarjetaKpi
          icono={<Clock />}
          valor={r.tardanzas}
          etiqueta={r.tardanzas === 1 ? 'Tardanza' : 'Tardanzas'}
          tono="aviso"
        />
        <TarjetaKpi
          icono={<FileCheck />}
          valor={r.justificadas}
          etiqueta={r.justificadas === 1 ? 'Falta justificada' : 'Faltas justificadas'}
          tono="info"
        />
      </div>

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
        <PorMes datos={datos} />
        <PorCurso datos={datos} />
      </div>

      <Calendario key={datos.bimestre?.id} datos={datos} />
      <Registros datos={datos} />
    </>
  )
}

/** Barras verticales de 0 a 100 % con la meta del colegio como línea punteada */
function PorMes({ datos }: { datos: Datos }) {
  const meta = datos.meta ?? 90
  return (
    <Card>
      <CardTitulo>Asistencia por mes</CardTitulo>
      <div
        className="relative mt-6 h-52"
        role="img"
        aria-label={datos.por_mes.map((m) => `${nombreMes(m.mes)}: ${porcentaje(m.porcentaje)}`).join(', ')}
      >
        <div
          className="absolute inset-x-0 border-t-2 border-dashed border-texto-suave/50"
          style={{ bottom: `${meta}%` }}
        >
          <span className="absolute -top-6 right-0 bg-superficie px-1 text-xs text-texto-suave">Meta {meta} %</span>
        </div>
        <div className="absolute inset-0 flex items-end justify-around gap-4 border-b border-borde">
          {datos.por_mes.map((m) => (
            <div
              key={m.mes}
              className="flex h-full w-full max-w-20 flex-col items-center justify-end"
              title={`${nombreMes(m.mes)}: ${porcentaje(m.porcentaje)} de ${m.clases} clases`}
            >
              <span className="mb-1 text-sm font-bold">{porcentaje(m.porcentaje)}</span>
              <span className="w-full rounded-t bg-primario" style={{ height: `${m.porcentaje ?? 0}%` }} />
            </div>
          ))}
        </div>
      </div>
      <div className="mt-2 flex justify-around gap-4 text-sm text-texto-suave" aria-hidden>
        {datos.por_mes.map((m) => (
          <span key={m.mes} className="w-full max-w-20 text-center">
            {nombreMes(m.mes)}
          </span>
        ))}
      </div>
    </Card>
  )
}

function PorCurso({ datos }: { datos: Datos }) {
  return (
    <Card>
      <CardTitulo>Resumen por curso</CardTitulo>
      <ul className="mt-4 flex flex-col gap-3">
        {datos.por_curso.map((c) => (
          <li key={c.curso_id} className="grid grid-cols-[minmax(0,9rem)_minmax(0,1fr)_3.5rem] items-center gap-3">
            <span className="truncate" title={c.asignatura}>
              {c.asignatura}
            </span>
            <span className="h-2.5 rounded-full bg-texto/8" aria-hidden>
              <span className="block h-full rounded-full bg-primario" style={{ width: `${c.porcentaje ?? 0}%` }} />
            </span>
            <span
              className="text-right font-bold"
              title={`${c.faltas} faltas, ${c.tardanzas} tardanzas en ${c.clases} clases`}
            >
              {porcentaje(c.porcentaje)}
            </span>
          </li>
        ))}
      </ul>
    </Card>
  )
}

const DIAS = ['L', 'M', 'M', 'J', 'V', 'S', 'D']

function Calendario({ datos }: { datos: Datos }) {
  const porDia = useMemo(() => {
    const mapa = new Map<string, EstadoAsistencia[]>()
    for (const r of datos.registros) mapa.set(r.fecha, [...(mapa.get(r.fecha) ?? []), r.estado])
    return mapa
  }, [datos.registros])
  const meses = useMemo(() => [...new Set(datos.registros.map((r) => r.fecha.slice(0, 7)))].sort(), [datos.registros])
  const [indice, setIndice] = useState(meses.length - 1)
  const mes = meses[Math.min(indice, meses.length - 1)]
  if (!mes) return null

  const [a, m] = mes.split('-').map(Number)
  const vacios = (new Date(a, m - 1, 1).getDay() + 6) % 7
  const dias = new Date(a, m, 0).getDate()

  return (
    <Card>
      <div className="flex items-center justify-between gap-3">
        <CardTitulo>Calendario</CardTitulo>
        <div className="flex items-center gap-1">
          <button
            type="button"
            onClick={() => setIndice((i) => i - 1)}
            disabled={indice <= 0}
            className="grid size-10 cursor-pointer place-items-center rounded-lg border border-borde disabled:cursor-default disabled:opacity-40"
            aria-label="Mes anterior"
          >
            <ChevronLeft className="size-5" aria-hidden />
          </button>
          <span className="w-28 text-center font-bold" aria-live="polite">
            {nombreMes(mes)} {a}
          </span>
          <button
            type="button"
            onClick={() => setIndice((i) => i + 1)}
            disabled={indice >= meses.length - 1}
            className="grid size-10 cursor-pointer place-items-center rounded-lg border border-borde disabled:cursor-default disabled:opacity-40"
            aria-label="Mes siguiente"
          >
            <ChevronRight className="size-5" aria-hidden />
          </button>
        </div>
      </div>
      <div className="mt-4 grid grid-cols-7 gap-1 text-center sm:gap-2">
        {DIAS.map((d, i) => (
          <span key={i} className="text-sm font-bold text-texto-suave" aria-hidden>
            {d}
          </span>
        ))}
        {Array.from({ length: vacios }, (_, i) => (
          <span key={`v${i}`} />
        ))}
        {Array.from({ length: dias }, (_, i) => {
          const iso = `${mes}-${String(i + 1).padStart(2, '0')}`
          const estados = porDia.get(iso) ?? []
          const peor = GRAVEDAD.find((g) => estados.includes(g))
          const detalle = estados.length
            ? `${fechaCorta(iso)}: ${estados.map((e) => NOMBRE_ESTADO[e].toLowerCase()).join(', ')}`
            : undefined
          return (
            <span
              key={iso}
              title={detalle}
              className={cn(
                'flex aspect-square flex-col items-center justify-center gap-1 rounded-lg text-sm sm:text-base',
                estados.length ? 'bg-fondo font-bold' : 'text-texto-suave/70',
              )}
            >
              {i + 1}
              {peor && <span className={cn('size-2 rounded-full', PUNTO[peor])} aria-hidden />}
              {detalle && <span className="sr-only">{detalle}</span>}
            </span>
          )
        })}
      </div>
      <ul className="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm text-texto-suave" aria-label="Leyenda">
        {(['P', 'T', 'J', 'F'] as const).map((e) => (
          <li key={e} className="flex items-center gap-1.5">
            <span className={cn('size-2.5 rounded-full', PUNTO[e])} aria-hidden />
            {NOMBRE_ESTADO[e]}
          </li>
        ))}
      </ul>
    </Card>
  )
}

function Registros({ datos }: { datos: Datos }) {
  const [filtro, setFiltro] = useState('')
  const filas = datos.registros.filter((r) => !filtro || r.estado === filtro)
  return (
    <Card className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <CardTitulo>Registros</CardTitulo>
        <Selector
          etiqueta="Mostrar"
          className="w-52"
          value={filtro}
          onChange={(e) => setFiltro(e.target.value)}
          opciones={[
            { valor: '', texto: 'Todos' },
            { valor: 'F', texto: 'Solo faltas' },
            { valor: 'T', texto: 'Solo tardanzas' },
            { valor: 'J', texto: 'Solo justificadas' },
          ]}
        />
      </div>
      <TablaDatos
        key={filtro}
        filas={filas}
        claveFila={(r) => `${r.fecha}-${r.curso}`}
        descripcion="Registros de asistencia"
        vacio="No hay registros con ese filtro."
        columnas={[
          { clave: 'fecha', titulo: 'Fecha', principal: true, celda: (r) => fechaCorta(r.fecha) },
          { clave: 'curso', titulo: 'Curso', celda: (r) => r.curso },
          { clave: 'hora', titulo: 'Hora', celda: (r) => r.hora ?? '—' },
          {
            clave: 'estado',
            titulo: 'Estado',
            celda: (r) => (
              <Insignia tono={TONO_ESTADO[r.estado]} punto>
                {NOMBRE_ESTADO[r.estado]}
              </Insignia>
            ),
          },
          { clave: 'obs', titulo: 'Observación', celda: (r) => r.observacion ?? '—' },
        ]}
      />
    </Card>
  )
}
