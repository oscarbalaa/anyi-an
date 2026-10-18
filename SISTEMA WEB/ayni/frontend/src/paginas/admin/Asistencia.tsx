import { ChartColumn, ClipboardPen } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { Button } from '@/components/ui/button'
import { Card, CardTitulo } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import { Pestanas } from '@/components/ui/pestanas'
import { Selector } from '@/components/ui/selector'
import { TablaDatos } from '@/components/ui/tabla-datos'
import { fechaCorta, fechaLarga, porcentaje, useClase, useCursosAdmin, useResumenAsistencia } from '@/lib/asistencia'
import { RegistroClase } from '../asistencia/RegistroClase'
import { SelectorFecha } from '../asistencia/SelectorFecha'
import { useFechaElegida } from '../asistencia/useFechaElegida'

const PESTANAS = [
  { id: 'resumen', texto: 'Resumen', icono: ChartColumn },
  { id: 'corregir', texto: 'Corregir asistencia', icono: ClipboardPen },
]

// CU-14: indicadores por aula y corrección de registros pasados
export default function Asistencia() {
  const [parametros, setParametros] = useSearchParams()
  const activa = parametros.get('pestana') === 'corregir' ? 'corregir' : 'resumen'

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <header>
        <h1 className="text-3xl font-black sm:text-4xl">Asistencia</h1>
        <p className="mt-1 text-lg text-texto-suave">
          Asistencia por aula, clases sin registrar y corrección de registros pasados.
        </p>
      </header>
      <Pestanas
        etiqueta="Secciones de asistencia"
        pestanas={PESTANAS}
        activa={activa}
        onCambiar={(id) => setParametros({ pestana: id }, { replace: true })}
      >
        {activa === 'resumen' ? <Resumen /> : <Corregir />}
      </Pestanas>
    </div>
  )
}

function Resumen() {
  const [bimestre, setBimestre] = useState<number | null>(null)
  const [, setParametros] = useSearchParams()
  const { data, isPending, isError, refetch } = useResumenAsistencia(bimestre)

  if (isPending) return <Esqueleto className="h-96" />
  if (isError)
    return (
      <EstadoVacio
        titulo="No se pudo cargar el resumen"
        accion={<Button onClick={() => refetch()}>Reintentar</Button>}
      />
    )
  if (!data.bimestre) return <EstadoVacio titulo="No hay un periodo en curso con bimestres" />
  const meta = data.meta ?? 90

  const revisar = (curso: number, fecha: string) =>
    setParametros(
      { pestana: 'corregir', curso: String(curso), bimestre: String(data.bimestre!.id), fecha },
      { replace: true },
    )

  return (
    <div className="flex flex-col gap-6">
      <Selector
        etiqueta="Bimestre"
        className="w-52"
        value={String(data.bimestre.id)}
        onChange={(e) => setBimestre(Number(e.target.value))}
        opciones={data.bimestres.map((b) => ({ valor: String(b.id), texto: b.nombre }))}
      />
      <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <Card className="flex flex-col gap-4">
          <CardTitulo>Asistencia por aula</CardTitulo>
          <TablaDatos
            filas={data.aulas}
            claveFila={(a) => a.id}
            descripcion="Asistencia por aula"
            porPagina={12}
            columnas={[
              {
                clave: 'aula',
                titulo: 'Aula',
                principal: true,
                celda: (a) => <span className="font-bold">{a.nombre}</span>,
              },
              { clave: 'est', titulo: 'Estudiantes', celda: (a) => a.estudiantes },
              { clave: 'clases', titulo: 'Clases', celda: (a) => a.clases },
              { clave: 'faltas', titulo: 'Faltas', celda: (a) => a.faltas },
              { clave: 'tard', titulo: 'Tardanzas', celda: (a) => a.tardanzas },
              {
                clave: 'pct',
                titulo: 'Asistencia',
                celda: (a) => (
                  <span className="flex flex-wrap items-center gap-2">
                    <strong>{porcentaje(a.porcentaje)}</strong>
                    {a.porcentaje !== null && a.porcentaje < meta && (
                      <Insignia tono="aviso">Bajo la meta ({meta} %)</Insignia>
                    )}
                  </span>
                ),
              },
            ]}
          />
        </Card>

        <Card className="flex flex-col gap-4" aria-labelledby="sin-registro">
          <div>
            <CardTitulo id="sin-registro">Clases sin registrar</CardTitulo>
            <p className="text-texto-suave">Clases ya dictadas (hasta ayer) sin asistencia definitiva.</p>
          </div>
          {data.sin_registro.length === 0 ? (
            <EstadoVacio titulo="Todo al día" descripcion="Los docentes registraron todas sus clases." />
          ) : (
            <ul className="flex flex-col gap-3">
              {data.sin_registro.map((d) => (
                <li key={d.docente_id} className="rounded-xl border border-borde p-4">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="font-bold">{d.docente}</p>
                    <Insignia tono={d.total >= 3 ? 'peligro' : 'aviso'}>
                      {d.total} {d.total === 1 ? 'clase' : 'clases'} · desde {fechaCorta(d.desde)}
                    </Insignia>
                  </div>
                  <ul className="mt-2 flex flex-col gap-1">
                    {d.cursos.map((c) => (
                      <li key={c.id} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span>
                          {c.nombre}: {c.fechas.map(fechaCorta).join(', ')}
                        </span>
                        <Button
                          variante="fantasma"
                          tamano="chico"
                          onClick={() => revisar(c.id, c.fechas[c.fechas.length - 1])}
                          aria-label={`Revisar ${c.nombre}`}
                        >
                          Revisar
                        </Button>
                      </li>
                    ))}
                  </ul>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>
    </div>
  )
}

function Corregir() {
  const [parametros, setParametros] = useSearchParams()
  const { data, isPending } = useCursosAdmin()
  const aulas = data?.aulas ?? []
  const cursoPedido = Number(parametros.get('curso'))
  const aula =
    aulas.find((a) => a.cursos.some((c) => c.id === cursoPedido)) ??
    aulas.find((a) => a.id === Number(parametros.get('aula'))) ??
    aulas[0]
  const curso = aula?.cursos.find((c) => c.id === cursoPedido) ?? aula?.cursos[0] ?? null
  const elegido = useFechaElegida('admin', curso?.id ?? null)
  const clase = useClase('admin', curso?.id ?? null, elegido.fecha)

  const cambiar = (cambios: Record<string, string>) =>
    setParametros({ pestana: 'corregir', ...cambios }, { replace: true })

  if (isPending) return <Esqueleto className="h-96" />
  if (!aula || !curso) return <EstadoVacio titulo="No hay cursos en el periodo en curso" />

  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Selector
          etiqueta="Aula"
          value={String(aula.id)}
          onChange={(e) => cambiar({ aula: e.target.value })}
          opciones={aulas.map((a) => ({ valor: String(a.id), texto: a.nombre }))}
        />
        <Selector
          etiqueta="Curso"
          value={String(curso.id)}
          onChange={(e) => cambiar({ curso: e.target.value })}
          opciones={aula.cursos.map((c) => ({ valor: String(c.id), texto: `${c.asignatura} · ${c.docente}` }))}
        />
        <SelectorFecha {...elegido} />
      </div>
      {elegido.fecha && <p className="text-lg text-texto-suave">{`${curso.nombre} · ${fechaLarga(elegido.fecha)}`}</p>}
      {!elegido.cargando && !elegido.fecha ? (
        <EstadoVacio titulo="No hay clases de este curso en el bimestre elegido" />
      ) : clase.isPending ? (
        <Esqueleto className="h-96" />
      ) : clase.isError ? (
        <EstadoVacio titulo="No se pudo cargar la clase" descripcion={clase.error.message} />
      ) : (
        <RegistroClase
          key={`${clase.data.curso.id}-${clase.data.fecha}-${clase.data.sesion.actualizado_en}`}
          clase={clase.data}
          rol="admin"
        />
      )}
    </div>
  )
}
