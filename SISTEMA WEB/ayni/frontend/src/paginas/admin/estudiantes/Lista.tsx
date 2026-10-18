import { Plus } from 'lucide-react'
import { useCallback, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Ventana } from '@/components/ui/dialogo'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import { Selector } from '@/components/ui/selector'
import { TablaDatos } from '@/components/ui/tabla-datos'
import { useOpciones } from '@/lib/estructura'
import { useEstudiantes, type RespuestaCambio } from '@/lib/personas'
import { useCoincide } from '@/lib/useCoincide'
import { ResultadoClave } from '../usuarios/DetalleCuenta'
import { Buscador } from './Buscador'
import { EstadoEstudianteInsignia } from './Estados'
import { FichaEstudiante } from './FichaEstudiante'
import { FormEstudiante } from './FormEstudiante'

type Panel = number | 'nuevo' | null
type Clave = {
  contrasena: string
  correo_enviado: boolean
  usuario: { nombre: string; usuario: string; correo: string | null }
}

// CU-10 (mockup admin 09)
export function PestanaLista() {
  const [buscar, setBuscar] = useState('')
  const [aula, setAula] = useState('')
  const [estado, setEstado] = useState('ACTIVO')
  const [pagina, setPagina] = useState(1)
  const [panel, setPanel] = useState<Panel>(null)
  const [clave, setClave] = useState<Clave | null>(null)
  const anchoGrande = useCoincide('(min-width: 1280px)')
  const { data: opciones } = useOpciones()

  const alBuscar = useCallback((t: string) => {
    setBuscar(t)
    setPagina(1)
  }, [])
  const { data, isPending, isError, refetch } = useEstudiantes({ buscar, aula, estado, pagina })
  const visible: Panel = panel ?? (anchoGrande ? 'nuevo' : null)

  const creado = (r: RespuestaCambio) => {
    const e = r.estudiante as { id: number; nombre: string; codigo: string; correo: string | null }
    setClave({
      contrasena: r.contrasena ?? '',
      correo_enviado: Boolean(r.correo_enviado),
      usuario: { nombre: e.nombre, usuario: e.codigo, correo: e.correo },
    })
    setPanel(e.id)
  }

  const contenidoPanel =
    visible === 'nuevo' ? (
      <Card aria-label="Registrar estudiante">
        <h2 className="mb-4 text-2xl font-black">Registrar estudiante</h2>
        <FormEstudiante estudiante={null} onListo={() => setPanel(null)} onCreado={creado} />
      </Card>
    ) : visible !== null ? (
      <FichaEstudiante key={visible} id={visible} />
    ) : null

  const filtro = (cambiar: (v: string) => void) => (e: { target: { value: string } }) => {
    cambiar(e.target.value)
    setPagina(1)
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-texto-suave">{data ? `${data.registrados} estudiantes registrados en total.` : ' '}</p>
        <Button onClick={() => setPanel('nuevo')}>
          <Plus className="size-5" aria-hidden /> Registrar estudiante
        </Button>
      </div>
      <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_26rem]">
        <Card className="flex flex-col gap-4">
          <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
            <div className="sm:col-span-2 2xl:col-span-1">
              <Buscador placeholder="Nombre, código o documento" onBuscar={alBuscar} />
            </div>
            <Selector
              etiqueta="Aula"
              value={aula}
              onChange={filtro(setAula)}
              opciones={[
                { valor: '', texto: 'Todas' },
                { valor: 'sin', texto: 'Sin matrícula' },
                ...(opciones?.aulas ?? []).map((a) => ({ valor: String(a.id), texto: a.nombre })),
              ]}
            />
            <Selector
              etiqueta="Estado"
              value={estado}
              onChange={filtro(setEstado)}
              opciones={[
                { valor: 'ACTIVO', texto: 'Activos' },
                { valor: 'RETIRADO', texto: 'Retirados' },
                { valor: 'TRASLADADO', texto: 'Trasladados' },
                { valor: 'EGRESADO', texto: 'Egresados' },
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
              claveFila={(e) => e.id}
              descripcion="Estudiantes"
              vacio={buscar ? 'Ningún estudiante coincide con la búsqueda.' : 'No hay estudiantes con esos filtros.'}
              filaActiva={typeof visible === 'number' ? visible : null}
              paginacion={{ pagina: data.pagina, total: data.total, porPagina: data.por_pagina, onCambiar: setPagina }}
              columnas={[
                {
                  clave: 'estudiante',
                  titulo: 'Estudiante',
                  principal: true,
                  celda: (e) => (
                    <span className="block leading-tight">
                      <span className="block font-bold">
                        {e.nombre}
                        {e.origen === 'EXCEL' && (
                          <span className="ml-2 align-middle">
                            <Insignia tono="info">Excel</Insignia>
                          </span>
                        )}
                      </span>
                      <span className="text-sm text-texto-suave">
                        {e.codigo} · {e.documento}
                      </span>
                    </span>
                  ),
                },
                {
                  clave: 'aula',
                  titulo: 'Aula',
                  celda: (e) =>
                    e.aula ? (
                      <span className="whitespace-nowrap">{e.aula}</span>
                    ) : (
                      <span className="text-texto-suave">Sin matrícula</span>
                    ),
                },
                {
                  clave: 'estado',
                  titulo: 'Estado',
                  celda: (e) => <EstadoEstudianteInsignia estado={e.estado} cuenta={e.cuenta} />,
                },
                {
                  clave: 'accion',
                  titulo: 'Acción',
                  celda: (e) => (
                    <Button
                      variante="secundario"
                      tamano="chico"
                      onClick={() => setPanel(e.id)}
                      aria-label={`Ver ficha de ${e.nombre}`}
                      className="whitespace-nowrap"
                    >
                      Ver ficha
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
            titulo={visible === 'nuevo' ? 'Registrar estudiante' : 'Estudiante'}
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
