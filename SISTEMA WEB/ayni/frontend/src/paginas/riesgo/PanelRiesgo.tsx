import { BellRing, ChartColumn, RefreshCw, ShieldAlert, ShieldCheck } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Card, CardTitulo } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import { Selector } from '@/components/ui/selector'
import { TablaDatos } from '@/components/ui/tabla-datos'
import { TarjetaKpi } from '@/components/ui/tarjeta-kpi'
import { fechaHora, NIVEL, usePanel, useRecalcular, type Panel, type RolAlertas } from '@/lib/alertas'
import type { ErrorApi } from '@/lib/api'
import { hace } from '@/lib/notificaciones'

const plural = (n: number, uno: string, varios: string) => `${n} ${n === 1 ? uno : varios}`

// CU-26: panel de riesgo del colegio (mockup admin 13) y de coordinación (mockup docente 12)
export default function PanelRiesgo({ rol }: { rol: RolAlertas }) {
  const [nivel, setNivel] = useState('')
  const [aula, setAula] = useState('')
  const [bimestre, setBimestre] = useState('')
  const { data, isPending, isError, refetch } = usePanel(rol, { nivel, aula_id: aula, bimestre_id: bimestre })
  const recalcular = useRecalcular(rol)
  const avisos = useAvisos()
  const base = `/${rol}`

  if (isPending) return <Esqueleto className="mx-auto h-[36rem] max-w-7xl" />
  if (isError)
    return (
      <EstadoVacio titulo="No se pudo cargar el panel" accion={<Button onClick={() => refetch()}>Reintentar</Button>} />
    )
  if (!data.bimestre || !data.kpis)
    return (
      <div className="mx-auto max-w-7xl">
        <h1 className="text-3xl font-black sm:text-4xl">
          {rol === 'admin' ? 'Panel de riesgo académico' : 'Coordinación'}
        </h1>
        <EstadoVacio
          titulo={rol === 'admin' ? 'No hay un bimestre en curso' : 'No coordinas ningún aula en este periodo'}
          descripcion={
            rol === 'admin'
              ? 'El riesgo se calcula sobre el bimestre en curso.'
              : 'El panel de coordinación aparece cuando la administración te asigna una coordinación de aula, grado o nivel.'
          }
        />
      </div>
    )

  const k = data.kpis
  const aulas = (data.aulas ?? []).filter((a) => !nivel || a.nivel === nivel)
  const titulo = rol === 'admin' ? 'Panel de riesgo académico' : `Panel de coordinación · ${data.ambito}`
  const variacion = (n: number, antes?: number) =>
    data.anterior && antes !== undefined
      ? `${n - antes >= 0 ? '+' : ''}${n - antes} respecto al ${data.anterior.bimestre}`
      : undefined

  const pulsarRecalcular = () =>
    recalcular.mutate(undefined, {
      onSuccess: (r) => avisos.exito(r.mensaje),
      onError: (e) => avisos.error((e as ErrorApi).message),
    })

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-3xl font-black sm:text-4xl">{titulo}</h1>
          <p className="mt-1 text-lg text-texto-suave">
            {data.bimestre.nombre}
            {data.actualizado ? ` · Última actualización: ${fechaHora(data.actualizado)}` : ' · Aún sin calcular'}
            {data.modelo && ` · Modelo ${data.modelo}`}
          </p>
        </div>
        <Button onClick={pulsarRecalcular} disabled={recalcular.isPending}>
          <RefreshCw className={recalcular.isPending ? 'size-5 animate-spin' : 'size-5'} aria-hidden />
          {recalcular.isPending ? 'Recalculando…' : 'Recalcular'}
        </Button>
      </header>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {rol === 'admin' && (
          <Selector
            etiqueta="Ámbito"
            value={nivel}
            onChange={(e) => {
              setNivel(e.target.value)
              setAula('')
            }}
            opciones={[
              { valor: '', texto: 'Toda la institución' },
              { valor: 'PRIMARIA', texto: 'Primaria' },
              { valor: 'SECUNDARIA', texto: 'Secundaria' },
            ]}
          />
        )}
        {aulas.length > 1 && (
          <Selector
            etiqueta="Aula"
            value={aula}
            onChange={(e) => setAula(e.target.value)}
            opciones={[{ valor: '', texto: 'Todas' }, ...aulas.map((a) => ({ valor: String(a.id), texto: a.nombre }))]}
          />
        )}
        {data.bimestres.length > 1 && (
          <Selector
            etiqueta="Bimestre"
            value={bimestre || String(data.bimestre.id)}
            onChange={(e) => setBimestre(e.target.value)}
            opciones={data.bimestres.map((b) => ({ valor: String(b.id), texto: b.nombre }))}
          />
        )}
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Kpi
          icono={<ShieldAlert />}
          valor={k.ALTO}
          etiqueta="Riesgo alto"
          tono="peligro"
          pie={variacion(k.ALTO, data.anterior?.ALTO)}
        />
        <Kpi
          icono={<ChartColumn />}
          valor={k.MEDIO}
          etiqueta="Riesgo medio"
          tono="aviso"
          pie={variacion(k.MEDIO, data.anterior?.MEDIO)}
        />
        <Kpi
          icono={<ShieldCheck />}
          valor={k.BAJO}
          etiqueta="Riesgo bajo"
          tono="exito"
          pie={`de ${plural(k.estudiantes, 'estudiante', 'estudiantes')}`}
        />
        <Kpi
          icono={<BellRing />}
          valor={k.sin_atender}
          etiqueta="Alertas sin atender"
          tono="info"
          pie={
            k.mas_antigua
              ? `La más antigua: ${hace(k.mas_antigua).toLowerCase()}`
              : `${plural(k.abiertas, 'alerta abierta', 'alertas abiertas')}`
          }
        />
      </div>

      <div className="grid items-start gap-6 lg:grid-cols-2">
        <Distribucion porAula={data.por_aula ?? []} />
        <Factores factores={data.factores ?? []} />
      </div>

      <Card className="flex flex-col gap-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <CardTitulo>Estudiantes que requieren atención primero</CardTitulo>
          <Button asChild variante="secundario" tamano="chico">
            <Link to={`${base}/alertas`}>Ver todas las alertas</Link>
          </Button>
        </div>
        <TablaDatos
          filas={data.atencion ?? []}
          claveFila={(a) => a.matricula_id}
          descripcion="Estudiantes que requieren atención"
          vacio="Ningún estudiante está en riesgo medio o alto."
          columnas={[
            {
              clave: 'nombre',
              titulo: 'Estudiante',
              principal: true,
              celda: (a) => <span className="font-bold">{a.nombre}</span>,
            },
            { clave: 'aula', titulo: 'Aula', celda: (a) => a.aula },
            {
              clave: 'nivel',
              titulo: 'Riesgo',
              celda: (a) => (
                <Insignia tono={NIVEL[a.nivel].tono} punto>
                  {NIVEL[a.nivel].texto} · {a.probabilidad} %
                </Insignia>
              ),
            },
            {
              clave: 'factor',
              titulo: 'Factor principal',
              celda: (a) => <span className="block max-w-sm text-texto-suave">{a.factor_principal ?? '—'}</span>,
            },
            {
              clave: 'accion',
              titulo: 'Acción',
              celda: (a) =>
                a.alerta_id ? (
                  <Button asChild variante="secundario" tamano="chico">
                    <Link to={`${base}/alertas?id=${a.alerta_id}`} aria-label={`Abrir la alerta de ${a.nombre}`}>
                      Abrir
                    </Link>
                  </Button>
                ) : (
                  <span className="text-sm text-texto-suave">Alerta cerrada</span>
                ),
            },
          ]}
        />
      </Card>

      <p className="text-sm text-texto-suave">
        El riesgo se calcula con reglas (asistencia, promedio con tareas pendientes y notas en descenso) al registrar
        asistencia o notas, una vez al día y con «Recalcular». Los umbrales se configuran en Estructura académica →
        Colegio.
        {rol === 'docente' && ' La coordinación solo muestra las aulas de tu ámbito.'}
      </p>
    </div>
  )
}

function Kpi({ pie, ...props }: Parameters<typeof TarjetaKpi>[0] & { pie?: string }) {
  return (
    <div className="flex flex-col gap-1">
      <TarjetaKpi {...props} />
      {pie && <p className="px-2 text-sm text-texto-suave">{pie}</p>}
    </div>
  )
}

/** Barras apiladas por aula: rojo = alto, ámbar = medio (los demás, bajo) */
function Distribucion({ porAula }: { porAula: NonNullable<Panel['por_aula']> }) {
  const max = Math.max(1, ...porAula.map((a) => a.ALTO + a.MEDIO + a.BAJO))
  return (
    <Card className="flex flex-col gap-4" aria-labelledby="titulo-distribucion">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <CardTitulo id="titulo-distribucion">Distribución por aula</CardTitulo>
        <ul className="flex flex-wrap gap-3 text-sm" aria-label="Leyenda">
          {(['ALTO', 'MEDIO', 'BAJO'] as const).map((n) => (
            <li key={n} className="flex items-center gap-1.5">
              <span className={`size-3 rounded-full ${COLOR[n]}`} aria-hidden />
              {NIVEL[n].texto}
            </li>
          ))}
        </ul>
      </div>
      {porAula.length === 0 ? (
        <p className="text-texto-suave">Aún no hay aulas con estudiantes evaluados.</p>
      ) : (
        <ul className="flex flex-col gap-3">
          {porAula.map((a) => {
            const total = a.ALTO + a.MEDIO + a.BAJO
            const texto = `${a.nombre}: ${a.ALTO} en riesgo alto, ${a.MEDIO} en riesgo medio y ${a.BAJO} en riesgo bajo`
            return (
              <li key={a.id} className="grid grid-cols-[4.5rem_minmax(0,1fr)_2rem] items-center gap-3" title={texto}>
                <span className="font-bold">{a.nombre}</span>
                <span className="flex h-6 gap-0.5" role="img" aria-label={texto}>
                  {(['ALTO', 'MEDIO', 'BAJO'] as const).map(
                    (n) =>
                      a[n] > 0 && (
                        <span
                          key={n}
                          className={`grid place-items-center rounded text-xs font-bold ${COLOR[n]} ${n === 'BAJO' ? 'text-texto-suave' : 'text-white'}`}
                          style={{ width: `${(100 * a[n]) / max}%` }}
                        >
                          {n !== 'BAJO' && a[n]}
                        </span>
                      ),
                  )}
                </span>
                <span className="text-right text-texto-suave">{total}</span>
              </li>
            )
          })}
        </ul>
      )}
    </Card>
  )
}

const COLOR = { ALTO: 'bg-peligro', MEDIO: 'bg-aviso', BAJO: 'bg-exito/25' } as const

function Factores({ factores }: { factores: NonNullable<Panel['factores']> }) {
  return (
    <Card className="flex flex-col gap-4" aria-labelledby="titulo-factores">
      <div>
        <CardTitulo id="titulo-factores">Factores más frecuentes</CardTitulo>
        <p className="text-texto-suave">Entre los estudiantes en riesgo medio o alto.</p>
      </div>
      {factores.length === 0 ? (
        <p className="text-texto-suave">Ningún estudiante está en riesgo medio o alto.</p>
      ) : (
        <ul className="flex flex-col gap-3">
          {factores.map((f) => (
            <li
              key={f.variable}
              className="grid grid-cols-[minmax(0,10rem)_minmax(0,1fr)_3rem] items-center gap-3"
              title={`${f.titulo}: ${plural(f.estudiantes, 'estudiante', 'estudiantes')}`}
            >
              <span className="leading-tight">{f.titulo}</span>
              <span className="h-3 rounded-full bg-texto/8" aria-hidden>
                <span className="block h-full rounded-full bg-primario" style={{ width: `${f.porcentaje}%` }} />
              </span>
              <span className="text-right font-bold">{f.porcentaje} %</span>
            </li>
          ))}
        </ul>
      )}
    </Card>
  )
}
