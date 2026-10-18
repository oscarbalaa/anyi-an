import { Plus, Search } from 'lucide-react'
import { useEffect, useId, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Ventana } from '@/components/ui/dialogo'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import { Selector } from '@/components/ui/selector'
import { TablaDatos, type Columna } from '@/components/ui/tabla-datos'
import { useCoincide } from '@/lib/useCoincide'
import { useUsuarios, type FilaUsuario, type FiltrosUsuarios, type ResultadoTemporal } from '@/lib/usuarios'
import { DetalleCuenta, EstadoCuentaInsignia, ResultadoClave } from './usuarios/DetalleCuenta'
import { FormularioUsuario } from './usuarios/FormularioUsuario'

const ROLES = [
  { valor: '', texto: 'Todos' },
  { valor: 'ADMIN', texto: 'Administrador' },
  { valor: 'DOCENTE', texto: 'Docente' },
  { valor: 'ESTUDIANTE', texto: 'Estudiante' },
]

const ESTADOS = [
  { valor: '', texto: 'Todos' },
  { valor: 'ACTIVO', texto: 'Activo' },
  { valor: 'BLOQUEADO', texto: 'Bloqueado' },
  { valor: 'INACTIVO', texto: 'Inactivo' },
]

// CU-04: RF-11 a RF-13 y RF-16
export default function Usuarios() {
  const [filtros, setFiltros] = useState<Omit<FiltrosUsuarios, 'buscar'>>({ rol: '', estado: '', pagina: 1 })
  const [texto, setTexto] = useState('')
  const [buscar, setBuscar] = useState('')
  const [elegido, setElegido] = useState<number | null>(null)
  const [creando, setCreando] = useState(false)
  const [clave, setClave] = useState<ResultadoTemporal | null>(null)
  const anchoGrande = useCoincide('(min-width: 1280px)')
  const idBuscar = useId()

  // Se consulta cuando se deja de escribir (300 ms)
  useEffect(() => {
    const espera = window.setTimeout(() => setBuscar(texto.trim()), 300)
    return () => window.clearTimeout(espera)
  }, [texto])

  const { data, isPending, isError, isFetching, refetch } = useUsuarios({ ...filtros, buscar })
  const cambiar = (cambios: Partial<typeof filtros>) => setFiltros((f) => ({ ...f, pagina: 1, ...cambios }))

  // En pantalla grande siempre se ve una cuenta: si no se eligió, la primera (las bloqueadas van primero)
  const visible = elegido ?? (anchoGrande ? (data?.datos[0]?.id ?? null) : null)

  const columnas: Columna<FilaUsuario>[] = [
    {
      clave: 'usuario',
      titulo: 'Usuario',
      principal: true,
      celda: (f) => (
        <span className="block leading-tight">
          <span className="block font-bold">{f.nombre}</span>
          <span className="text-sm text-texto-suave">{f.usuario}</span>
        </span>
      ),
    },
    {
      clave: 'rol',
      titulo: 'Rol',
      celda: (f) => (
        <span className="block leading-tight">
          {f.rol_nombre}
          {f.detalle && <span className="block text-sm text-texto-suave">{f.detalle}</span>}
        </span>
      ),
    },
    {
      clave: 'estado',
      titulo: 'Estado',
      celda: (f) => (
        <span className="flex flex-wrap gap-1.5">
          <EstadoCuentaInsignia estado={f.estado} />
          {f.debe_cambiar_contrasena && f.estado === 'ACTIVO' && <Insignia tono="aviso">Clave temporal</Insignia>}
        </span>
      ),
    },
    {
      clave: 'accion',
      titulo: 'Acción',
      celda: (f) => (
        <Button
          variante="secundario"
          tamano="chico"
          onClick={() => setElegido(f.id)}
          aria-label={`Ver la cuenta de ${f.nombre}`}
        >
          Ver
        </Button>
      ),
    },
  ]

  const resumen = data?.resumen ?? {}
  const detalle = visible !== null && <DetalleCuenta id={visible} onClave={setClave} />

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-3xl font-black sm:text-4xl">Usuarios</h1>
          <p className="mt-1 text-lg text-texto-suave">Control de acceso al sistema.</p>
        </div>
        <Button onClick={() => setCreando(true)}>
          <Plus className="size-5" aria-hidden /> Nueva cuenta administrativa
        </Button>
      </header>

      <div className="flex flex-wrap gap-2" aria-label="Resumen de cuentas">
        <Insignia tono="exito" punto>
          {resumen.ACTIVO ?? 0} activas
        </Insignia>
        <Insignia tono="peligro" punto>
          {resumen.BLOQUEADO ?? 0} bloqueadas
        </Insignia>
        <Insignia tono="neutro" punto>
          {resumen.INACTIVO ?? 0} inactivas
        </Insignia>
      </div>

      <div className="grid gap-4 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
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
              onChange={(e) => {
                setTexto(e.target.value)
                setFiltros((f) => ({ ...f, pagina: 1 }))
              }}
              placeholder="Nombre o usuario"
              className="h-11 w-full rounded-xl border border-borde bg-superficie pr-3 pl-10 outline-none focus:border-accion focus:ring-3 focus:ring-primario/20"
            />
          </div>
        </div>
        <Selector
          etiqueta="Rol"
          opciones={ROLES}
          value={filtros.rol}
          onChange={(e) => cambiar({ rol: e.target.value })}
        />
        <Selector
          etiqueta="Estado"
          opciones={ESTADOS}
          value={filtros.estado}
          onChange={(e) => cambiar({ estado: e.target.value })}
        />
      </div>

      <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <Card className={isFetching && !isPending ? 'opacity-70 transition-opacity' : undefined} aria-busy={isFetching}>
          {isPending ? (
            <div className="flex flex-col gap-3">
              {[1, 2, 3, 4, 5].map((n) => (
                <Esqueleto key={n} className="h-14" />
              ))}
            </div>
          ) : isError ? (
            <EstadoVacio
              titulo="No se pudo cargar la lista"
              descripcion="Revisa tu conexión e inténtalo de nuevo."
              accion={<Button onClick={() => refetch()}>Reintentar</Button>}
            />
          ) : (
            <TablaDatos
              filas={data.datos}
              columnas={columnas}
              claveFila={(f) => f.id}
              descripcion="Usuarios del sistema"
              vacio={
                buscar || filtros.rol || filtros.estado
                  ? 'Ningún usuario coincide con los filtros.'
                  : 'No hay usuarios.'
              }
              filaActiva={visible}
              paginacion={{
                pagina: data.pagina,
                total: data.total,
                porPagina: data.por_pagina,
                onCambiar: (pagina) => setFiltros((f) => ({ ...f, pagina })),
              }}
            />
          )}
        </Card>

        {anchoGrande ? (
          <div className="sticky top-24">{detalle}</div>
        ) : (
          <Ventana
            abierto={elegido !== null}
            titulo="Cuenta"
            onCerrar={() => setElegido(null)}
            conCerrar
            className="w-[min(94vw,32rem)]"
          >
            {detalle}
          </Ventana>
        )}
      </div>

      <Ventana
        abierto={creando}
        titulo="Nueva cuenta administrativa"
        onCerrar={() => setCreando(false)}
        conCerrar
        className="w-[min(94vw,40rem)]"
      >
        {creando && (
          <FormularioUsuario
            usuario={null}
            onListo={(id) => {
              setCreando(false)
              if (id) setElegido(id)
            }}
          />
        )}
      </Ventana>
      <ResultadoClave resultado={clave} onCerrar={() => setClave(null)} />
    </div>
  )
}
