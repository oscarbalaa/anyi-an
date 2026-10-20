import { ChevronLeft, ChevronRight, Clock } from 'lucide-react'
import { useState } from 'react'
import { Ilustracion } from '@/components/Ilustracion'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { hoyIso } from '@/lib/asistencia'
import { useMiHorario, type ClaseHorario } from '@/lib/evaluaciones'
import { IMG } from '@/lib/imagenes'

const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']

const deIso = (iso: string) => {
  const [a, m, d] = iso.split('-').map(Number)
  return new Date(a, m - 1, d)
}

/** Lunes de la semana de hoy, desplazado n semanas */
const lunes = (desplazamiento: number) => {
  const hoy = deIso(hoyIso())
  const l = new Date(hoy)
  l.setDate(hoy.getDate() - ((hoy.getDay() + 6) % 7) + desplazamiento * 7)
  return l
}

const corta = (d: Date) => d.toLocaleDateString('es-PE', { day: 'numeric', month: 'short' }).replace('.', '')

// RF-48 (mockup estudiante 07)
export default function MiHorario() {
  const { data, isPending, isError, refetch } = useMiHorario()
  const [semana, setSemana] = useState(0)

  if (isPending) return <Esqueleto className="mx-auto h-[32rem] max-w-6xl" />
  if (isError)
    return (
      <EstadoVacio
        titulo="No se pudo cargar tu horario"
        accion={<Button onClick={() => refetch()}>Reintentar</Button>}
      />
    )
  if (!data.aula) return <EstadoVacio titulo="Aún no tienes matrícula en el periodo en curso" />

  const inicio = lunes(semana)
  const dias = DIAS.map((nombre, i) => {
    const d = new Date(inicio)
    d.setDate(inicio.getDate() + i)
    return { numero: i + 1, nombre, fecha: d }
  }).filter((d) => d.numero <= 5 || data.clases.some((c) => c.dia === d.numero))
  const fin = dias[dias.length - 1].fecha
  const clase = (dia: number, inicioBloque: string) =>
    data.clases.find((c) => c.dia === dia && c.inicio === inicioBloque)
  const hoy = hoyIso()

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-3xl font-black sm:text-4xl">Mi horario</h1>
          <p className="mt-1 text-lg text-texto-suave">
            {data.aula} · Semana del {corta(inicio)} al {corta(fin)}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button
            variante="secundario"
            tamano="chico"
            onClick={() => setSemana((s) => s - 1)}
            aria-label="Semana anterior"
          >
            <ChevronLeft className="size-5" aria-hidden />
          </Button>
          <Button variante="secundario" tamano="chico" onClick={() => setSemana(0)} disabled={semana === 0}>
            Semana actual
          </Button>
          <Button
            variante="secundario"
            tamano="chico"
            onClick={() => setSemana((s) => s + 1)}
            aria-label="Semana siguiente"
          >
            <ChevronRight className="size-5" aria-hidden />
          </Button>
        </div>
      </header>

      {data.proxima && (
        <div className="flex items-center gap-4 overflow-hidden rounded-2xl bg-primario-suave px-5 py-4">
          <span className="grid size-14 shrink-0 place-items-center rounded-full bg-superficie text-accion" aria-hidden>
            <Clock className="size-7" />
          </span>
          <div className="min-w-0 flex-1">
            <p className="font-bold text-accion">Próxima clase</p>
            <p className="text-2xl font-black">{data.proxima.curso}</p>
            <p className="text-texto-suave">
              {data.proxima.fecha === hoy ? 'Hoy' : DIAS[data.proxima.dia - 1]} {data.proxima.inicio} ·{' '}
              {data.proxima.ambiente ?? data.aula} · {data.proxima.docente}
            </p>
          </div>
          <Ilustracion imagen={IMG.piki} sizes="120px" alt="" className="hidden h-24 w-auto sm:block" />
        </div>
      )}

      {data.clases.length === 0 ? (
        <EstadoVacio titulo="Tu aula aún no tiene horario" />
      ) : (
        <>
          {/* PC: cuadrícula por hora y día */}
          <Card
            className="hidden overflow-x-auto p-0 sm:p-0 md:block"
            tabIndex={0}
            role="region"
            aria-label="Horario semanal"
          >
            <table className="w-full table-fixed text-left">
              <caption className="sr-only">Horario semanal de {data.aula}</caption>
              <thead>
                <tr className="border-b border-borde">
                  <th scope="col" className="w-28 px-3 py-3 text-center">
                    Hora
                  </th>
                  {dias.map((d) => (
                    <th key={d.numero} scope="col" className="px-2 py-3 text-center">
                      <span className="block">{d.nombre}</span>
                      <span className="text-sm font-normal text-texto-suave">{corta(d.fecha)}</span>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-borde">
                {data.bloques.map((b) => (
                  <tr key={b.inicio}>
                    <th scope="row" className="px-3 py-3 text-center font-bold whitespace-nowrap">
                      {b.inicio} – {b.fin}
                    </th>
                    {dias.map((d) => {
                      const c = clase(d.numero, b.inicio)
                      return (
                        <td key={d.numero} className="p-1.5 align-top">
                          {c && <Bloque c={c} />}
                        </td>
                      )
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>

          {/* Celular: un día debajo de otro */}
          <div className="flex flex-col gap-4 md:hidden">
            {dias.map((d) => {
              const delDia = data.clases.filter((c) => c.dia === d.numero)
              return (
                <Card key={d.numero} className="flex flex-col gap-2 p-4">
                  <h2 className="font-extrabold">
                    {d.nombre} <span className="font-normal text-texto-suave">{corta(d.fecha)}</span>
                  </h2>
                  {delDia.length ? (
                    delDia.map((c) => <Bloque key={c.inicio} c={c} conHora />)
                  ) : (
                    <p className="text-texto-suave">Sin clases</p>
                  )}
                </Card>
              )
            })}
          </div>
        </>
      )}
    </div>
  )
}

function Bloque({ c, conHora = false }: { c: ClaseHorario; conHora?: boolean }) {
  return (
    <div className="rounded-lg border-l-4 border-primario bg-primario-suave/60 px-3 py-2">
      <p className="font-bold leading-tight">{c.curso}</p>
      <p className="text-sm text-texto-suave">
        {conHora && `${c.inicio} – ${c.fin} · `}
        {c.ambiente ?? '—'}
      </p>
    </div>
  )
}
