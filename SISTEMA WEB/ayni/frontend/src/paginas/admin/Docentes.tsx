import { Plus, Search } from 'lucide-react'
import { useEffect, useId, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Ventana } from '@/components/ui/dialogo'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Selector } from '@/components/ui/selector'
import { TablaDatos } from '@/components/ui/tabla-datos'
import { useCoincide } from '@/lib/useCoincide'
import { useDocentes, type RespuestaCambio } from '@/lib/personas'
import { ResultadoClave } from './usuarios/DetalleCuenta'
import { EstadoDocenteInsignia } from './docentes/EstadoDocente'
import { FichaDocente } from './docentes/FichaDocente'
import { FormDocente } from './docentes/FormDocente'

type Panel = number | 'nuevo' | null
type Clave = {
  contrasena: string
  correo_enviado: boolean
  usuario: { nombre: string; usuario: string; correo: string | null }
}

// CU-09 (mockup admin 08)
export default function Docentes() {
  const [texto, setTexto] = useState('')
  const [buscar, setBuscar] = useState('')
  const [estado, setEstado] = useState('ACTIVO')
  const [pagina, setPagina] = useState(1)
  const [panel, setPanel] = useState<Panel>(null)
  const [clave, setClave] = useState<Clave | null>(null)
  const anchoGrande = useCoincide('(min-width: 1280px)')
  const idBuscar = useId()

  useEffect(() => {
    const espera = window.setTimeout(() => setBuscar(texto.trim()), 300)
    return () => window.clearTimeout(espera)
  }, [texto])

  const { data, isPending, isError, refetch } = useDocentes({ buscar, estado, pagina })
  const resumen = data?.resumen ?? {}
  const visible: Panel = panel ?? (anchoGrande ? 'nuevo' : null)

  const creado = (r: RespuestaCambio) => {
    const d = r.docente as { id: number; nombre: string; usuario: string; correo: string | null }
    setClave({ contrasena: r.contrasena ?? '', correo_enviado: Boolean(r.correo_enviado), usuario: d })
    setPanel(d.id)
  }

  const contenidoPanel =
    visible === 'nuevo' ? (
      <Card aria-label="Nuevo docente">
        <h2 className="mb-4 text-2xl font-black">Nuevo docente</h2>
        <FormDocente docente={null} onListo={() => setPanel(null)} onCreado={creado} />
      </Card>
    ) : visible !== null ? (
      <FichaDocente key={visible} id={visible} />
    ) : null

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-3xl font-black sm:text-4xl">Docentes</h1>
          <p className="mt-1 text-lg text-texto-suave">Personal docente de la institución.</p>
        </div>
        <Button onClick={() => setPanel('nuevo')}>
          <Plus className="size-5" aria-hidden /> Nuevo docente
        </Button>
      </header>

      <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_26rem]">
        <Card className="flex flex-col gap-4">
          <div className="grid gap-4 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] xl:grid-cols-1 2xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div className="flex flex-col gap-1.5">
              <label htmlFor={idBuscar} className="font-bold">
                Buscar
              </label>
              <div className="relative">
                <Search
                  className="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-texto-suave"
                  aria-hidden
                />
                <input
                  id={idBuscar}
                  type="search"
                  value={texto}
                  maxLength={100}
                  placeholder="Nombre o DNI"
                  onChange={(e) => {
                    setTexto(e.target.value)
                    setPagina(1)
                  }}
                  className="h-11 w-full rounded-xl border border-borde bg-superficie pr-3 pl-10 outline-none focus:border-accion focus:ring-3 focus:ring-primario/20"
                />
              </div>
            </div>
            <Selector
              etiqueta="Estado"
              value={estado}
              onChange={(e) => {
                setEstado(e.target.value)
                setPagina(1)
              }}
              opciones={[
                { valor: 'ACTIVO', texto: `Activos (${resumen.ACTIVO ?? 0})` },
                { valor: 'LICENCIA', texto: `De licencia (${resumen.LICENCIA ?? 0})` },
                { valor: 'INACTIVO', texto: `Inactivos (${resumen.INACTIVO ?? 0})` },
                { valor: '', texto: 'Todos' },
              ]}
            />
          </div>
          {isPending ? (
            <Esqueleto className="h-64" />
          ) : isError ? (
            <EstadoVacio
              titulo="No se pudo cargar la lista"
              accion={<Button onClick={() => refetch()}>Reintentar</Button>}
            />
          ) : (
            <TablaDatos
              filas={data.datos}
              claveFila={(d) => d.id}
              descripcion="Docentes"
              vacio={buscar ? 'Ningún docente coincide con la búsqueda.' : 'No hay docentes con ese estado.'}
              filaActiva={typeof visible === 'number' ? visible : null}
              paginacion={{ pagina: data.pagina, total: data.total, porPagina: data.por_pagina, onCambiar: setPagina }}
              columnas={[
                {
                  clave: 'docente',
                  titulo: 'Docente',
                  principal: true,
                  celda: (d) => (
                    <span className="block leading-tight">
                      <span className="block font-bold">{d.nombre}</span>
                      <span className="text-sm text-texto-suave">
                        {d.documento}
                        {d.especialidad && ` · ${d.especialidad}`}
                      </span>
                    </span>
                  ),
                },
                {
                  clave: 'coordina',
                  titulo: 'Coordina',
                  celda: (d) => d.coordina ?? <span className="text-texto-suave">—</span>,
                },
                {
                  clave: 'estado',
                  titulo: 'Estado',
                  celda: (d) => <EstadoDocenteInsignia estado={d.estado} cuenta={d.cuenta} />,
                },
                {
                  clave: 'accion',
                  titulo: 'Acción',
                  celda: (d) => (
                    <Button
                      variante="secundario"
                      tamano="chico"
                      onClick={() => setPanel(d.id)}
                      aria-label={`Abrir a ${d.nombre}`}
                    >
                      Abrir
                    </Button>
                  ),
                },
              ]}
            />
          )}
        </Card>

        {anchoGrande ? (
          <div className="sticky top-24">{contenidoPanel}</div>
        ) : (
          <Ventana
            abierto={visible !== null}
            titulo={visible === 'nuevo' ? 'Nuevo docente' : 'Docente'}
            onCerrar={() => setPanel(null)}
            conCerrar
            className="w-[min(94vw,36rem)]"
          >
            {contenidoPanel}
          </Ventana>
        )}
      </div>

      <ResultadoClave resultado={clave} onCerrar={() => setClave(null)} />
    </div>
  )
}
