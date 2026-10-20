import { ChevronLeft, ChevronRight, Search } from 'lucide-react'
import { useId, useMemo, useState, type ReactNode } from 'react'
import { cn } from '@/lib/utils'
import { EstadoVacio } from './estados'

export type Columna<T> = {
  clave: string
  titulo: string
  celda: (fila: T) => ReactNode
  /** En celular la tarjeta usa esta columna como título */
  principal?: boolean
}

type Props<T> = {
  filas: T[]
  columnas: Columna<T>[]
  claveFila: (fila: T) => string | number
  /** Texto en el que se busca; sin esto no aparece el buscador */
  textoBusqueda?: (fila: T) => string
  porPagina?: number
  descripcion: string
  vacio?: string
  /** Paginación hecha por la API: las filas recibidas ya son las de la página */
  paginacion?: { pagina: number; total: number; porPagina: number; onCambiar: (pagina: number) => void }
  /** Resalta una fila (por ejemplo, la que se ve en el panel de detalle) */
  filaActiva?: string | number | null
}

const normalizar = (t: string) =>
  t
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLowerCase()

/** Tabla con búsqueda y paginación. En celular cada fila se muestra como tarjeta. */
export function TablaDatos<T>({
  filas,
  columnas,
  claveFila,
  textoBusqueda,
  porPagina = 10,
  descripcion,
  vacio = 'No hay registros.',
  paginacion,
  filaActiva,
}: Props<T>) {
  const [busqueda, setBusqueda] = useState('')
  const [pagina, setPagina] = useState(1)
  const idBuscador = useId()

  const filtradas = useMemo(() => {
    const termino = normalizar(busqueda.trim())
    if (!termino || !textoBusqueda) return filas
    return filas.filter((f) => normalizar(textoBusqueda(f)).includes(termino))
  }, [filas, busqueda, textoBusqueda])

  const total = paginacion?.total ?? filtradas.length
  const tamano = paginacion?.porPagina ?? porPagina
  const paginas = Math.max(1, Math.ceil(total / tamano))
  const actual = paginacion ? paginacion.pagina : Math.min(pagina, paginas)
  const visibles = paginacion ? filas : filtradas.slice((actual - 1) * tamano, actual * tamano)
  const irA = paginacion?.onCambiar ?? setPagina
  const principal = columnas.find((c) => c.principal) ?? columnas[0]

  return (
    <div className="flex flex-col gap-4">
      {textoBusqueda && (
        <div className="relative max-w-sm">
          <label htmlFor={idBuscador} className="sr-only">
            Buscar en {descripcion}
          </label>
          <Search
            className="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-texto-suave"
            aria-hidden
          />
          <input
            id={idBuscador}
            type="search"
            value={busqueda}
            maxLength={100}
            onChange={(e) => {
              setBusqueda(e.target.value)
              setPagina(1)
            }}
            placeholder="Buscar…"
            className="h-11 w-full rounded-xl border border-borde bg-superficie pr-3 pl-10 outline-none focus:border-accion focus:ring-3 focus:ring-primario/20"
          />
        </div>
      )}

      {visibles.length === 0 ? (
        <EstadoVacio
          titulo={busqueda ? 'Sin resultados' : vacio}
          descripcion={busqueda ? `No hay coincidencias para "${busqueda}".` : undefined}
        />
      ) : (
        <>
          <div
            className="hidden overflow-x-auto rounded-2xl border border-borde md:block"
            tabIndex={0}
            role="region"
            aria-label={descripcion}
          >
            <table className="w-full text-left">
              <caption className="sr-only">{descripcion}</caption>
              <thead className="bg-primario-suave/60 text-sm">
                <tr>
                  {columnas.map((c) => (
                    <th key={c.clave} scope="col" className="px-4 py-3 font-bold">
                      {c.titulo}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-borde bg-superficie">
                {visibles.map((f) => (
                  <tr
                    key={claveFila(f)}
                    className={claveFila(f) === filaActiva ? 'bg-primario-suave/50' : 'hover:bg-fondo'}
                    aria-current={claveFila(f) === filaActiva ? 'true' : undefined}
                  >
                    {columnas.map((c) => (
                      <td key={c.clave} className="px-4 py-3">
                        {c.celda(f)}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <ul className="flex flex-col gap-3 md:hidden" aria-label={descripcion}>
            {visibles.map((f) => (
              <li
                key={claveFila(f)}
                className={cn(
                  'rounded-2xl border bg-superficie p-4',
                  claveFila(f) === filaActiva ? 'border-accion' : 'border-borde',
                )}
              >
                <div className="mb-2 font-extrabold">{principal.celda(f)}</div>
                <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-sm">
                  {columnas
                    .filter((c) => c !== principal)
                    .map((c) => (
                      <div key={c.clave} className="contents">
                        <dt className="text-texto-suave">{c.titulo}</dt>
                        <dd>{c.celda(f)}</dd>
                      </div>
                    ))}
                </dl>
              </li>
            ))}
          </ul>
        </>
      )}

      {paginas > 1 && (
        <nav className="flex items-center justify-between gap-2 text-sm" aria-label="Paginación">
          <p className="text-texto-suave">
            {(actual - 1) * tamano + 1}–{Math.min(actual * tamano, total)} de {total}
          </p>
          <div className="flex items-center gap-1">
            <button
              type="button"
              onClick={() => irA(actual - 1)}
              disabled={actual === 1}
              className="grid size-10 cursor-pointer place-items-center rounded-lg border border-borde bg-superficie disabled:cursor-default disabled:opacity-40"
              aria-label="Página anterior"
            >
              <ChevronLeft className="size-5" aria-hidden />
            </button>
            <span className="px-2 font-semibold">
              Página {actual} de {paginas}
            </span>
            <button
              type="button"
              onClick={() => irA(actual + 1)}
              disabled={actual === paginas}
              className="grid size-10 cursor-pointer place-items-center rounded-lg border border-borde bg-superficie disabled:cursor-default disabled:opacity-40"
              aria-label="Página siguiente"
            >
              <ChevronRight className="size-5" aria-hidden />
            </button>
          </div>
        </nav>
      )}
    </div>
  )
}
