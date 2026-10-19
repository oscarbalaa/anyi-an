import { Bell, BellDot, CheckCheck, CircleCheck, ChevronRight, MailOpen } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { IconoNotificacion } from '@/components/IconoNotificacion'
import { Ilustracion } from '@/components/Ilustracion'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Pestanas } from '@/components/ui/pestanas'
import type { ErrorApi } from '@/lib/api'
import { IMG } from '@/lib/imagenes'
import {
  hace,
  textoAccion,
  useMarcarLeida,
  useNotificaciones,
  type FiltroNotificaciones,
  type Notificacion,
} from '@/lib/notificaciones'
import { cn } from '@/lib/utils'
import type { Rol } from '@/layout/roles'

const SUBTITULO: Record<Rol, string> = {
  estudiante: 'Avisos claros para organizar tu semana.',
  docente: 'Avisos de tus cursos, tus estudiantes y el cierre de notas.',
  admin: 'Avisos de los estudiantes y del sistema.',
}

const VACIO: Record<FiltroNotificaciones, string> = {
  todas: 'Aún no tienes notificaciones.',
  pendientes: 'No tienes notificaciones sin leer.',
  leidas: 'Aún no has leído ninguna notificación.',
}

// CU-23 (mockup 03 de cada rol)
export default function Notificaciones({ rol }: { rol: Rol }) {
  const [filtro, setFiltro] = useState<FiltroNotificaciones>('todas')
  const [pagina, setPagina] = useState(1)
  const { data, isPending, isError, refetch } = useNotificaciones(filtro, pagina)
  const marcar = useMarcarLeida()
  const avisos = useAvisos()
  const pendientes = data?.conteo.pendientes ?? 0
  const mascota = rol === 'estudiante' ? IMG.piki : rol === 'docente' ? IMG.tambo : null

  const leerTodas = () =>
    marcar.mutate('todas', {
      onSuccess: (r) => avisos.exito(r.mensaje),
      onError: (e) => avisos.error((e as ErrorApi).message),
    })

  const cambiar = (f: string) => {
    setFiltro(f as FiltroNotificaciones)
    setPagina(1)
  }

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6">
      <header>
        <h1 className="text-3xl font-black sm:text-4xl">Notificaciones</h1>
        <p className="mt-1 text-lg text-texto-suave">{SUBTITULO[rol]}</p>
      </header>

      {data && (
        <section
          className={cn(
            'relative flex flex-wrap items-center gap-4 overflow-hidden rounded-2xl border p-5 sm:pr-48',
            pendientes ? 'border-info/25 bg-info/6' : 'border-exito/25 bg-exito/6',
          )}
          aria-live="polite"
        >
          <span
            className={cn(
              'grid size-14 shrink-0 place-items-center rounded-full',
              pendientes ? 'bg-info/12 text-info' : 'bg-exito/12 text-exito',
            )}
            aria-hidden
          >
            {pendientes ? <BellDot className="size-7" /> : <CircleCheck className="size-7" />}
          </span>
          <div className="min-w-0 flex-1">
            <h2 className={cn('text-xl font-extrabold sm:text-2xl', pendientes ? 'text-texto' : 'text-exito')}>
              {pendientes
                ? `Tienes ${pendientes} ${pendientes === 1 ? 'aviso' : 'avisos'} sin leer`
                : 'Vas al día con tus avisos'}
            </h2>
            <p className="text-texto-suave">
              {pendientes
                ? 'Ábrelos para ir directo a la pantalla de cada uno.'
                : 'Aquí aparecerán tus nuevas notas, faltas, actividades por vencer y más.'}
            </p>
          </div>
          {pendientes > 0 && (
            <Button
              variante="secundario"
              onClick={leerTodas}
              disabled={marcar.isPending}
              className="sm:relative sm:z-10"
            >
              <CheckCheck className="size-5" aria-hidden /> Marcar todas como leídas
            </Button>
          )}
          {mascota && (
            <Ilustracion
              imagen={mascota}
              sizes="160px"
              className="pointer-events-none absolute -right-2 -bottom-6 hidden h-auto w-40 sm:block"
            />
          )}
        </section>
      )}

      <Pestanas
        etiqueta="Filtrar notificaciones"
        activa={filtro}
        onCambiar={cambiar}
        pestanas={[
          { id: 'todas', texto: `Todas (${data?.conteo.todas ?? 0})`, icono: Bell },
          { id: 'pendientes', texto: `Sin leer (${pendientes})`, icono: BellDot },
          { id: 'leidas', texto: `Leídas (${data?.conteo.leidas ?? 0})`, icono: MailOpen },
        ]}
      >
        {isPending ? (
          <Esqueleto className="h-96" />
        ) : isError ? (
          <EstadoVacio
            titulo="No se pudo cargar tus notificaciones"
            accion={<Button onClick={() => refetch()}>Reintentar</Button>}
          />
        ) : data.datos.length === 0 ? (
          <EstadoVacio titulo={VACIO[filtro]} />
        ) : (
          <div className="flex flex-col gap-4">
            <ul className="flex flex-col gap-3" aria-label="Notificaciones">
              {data.datos.map((n) => (
                <Fila key={n.id} n={n} />
              ))}
            </ul>
            {data.ultima_pagina > 1 && (
              <nav className="flex items-center justify-between gap-3" aria-label="Páginas">
                <Button
                  variante="secundario"
                  tamano="chico"
                  disabled={pagina <= 1}
                  onClick={() => setPagina(pagina - 1)}
                >
                  Anterior
                </Button>
                <span className="text-texto-suave">
                  Página {data.pagina} de {data.ultima_pagina}
                </span>
                <Button
                  variante="secundario"
                  tamano="chico"
                  disabled={pagina >= data.ultima_pagina}
                  onClick={() => setPagina(pagina + 1)}
                >
                  Siguiente
                </Button>
              </nav>
            )}
          </div>
        )}
      </Pestanas>
    </div>
  )
}

function Fila({ n }: { n: Notificacion }) {
  const marcar = useMarcarLeida()
  const leer = () => !n.leida && marcar.mutate(n.id)

  return (
    <li
      className={cn(
        'flex flex-wrap items-center gap-x-4 gap-y-3 rounded-2xl border p-4 sm:flex-nowrap',
        n.leida ? 'border-borde bg-superficie' : 'border-accion/30 bg-primario-suave/40',
      )}
    >
      <IconoNotificacion tipo={n.tipo} />
      <div className="min-w-0 flex-1 basis-56">
        <p className="flex items-center gap-2 font-extrabold">
          {!n.leida && <span className="size-2.5 shrink-0 rounded-full bg-accion" aria-hidden />}
          {n.titulo}
          {!n.leida && <span className="sr-only">(sin leer)</span>}
        </p>
        <p className="text-texto-suave">{n.mensaje}</p>
      </div>
      <time dateTime={n.fecha} className="shrink-0 text-sm text-texto-suave sm:w-24 sm:text-right">
        {hace(n.fecha)}
      </time>
      {n.enlace ? (
        <Button
          asChild
          variante={n.leida ? 'fantasma' : 'principal'}
          tamano="chico"
          className="shrink-0 max-sm:ml-auto sm:min-w-40"
        >
          <Link to={n.enlace} onClick={leer} aria-label={`${textoAccion(n)}: ${n.titulo}`}>
            {textoAccion(n)} <ChevronRight className="size-4" aria-hidden />
          </Link>
        </Button>
      ) : (
        !n.leida && (
          <Button
            variante="fantasma"
            tamano="chico"
            onClick={leer}
            disabled={marcar.isPending}
            className="shrink-0 max-sm:ml-auto sm:min-w-40"
            aria-label={`Marcar como leída: ${n.titulo}`}
          >
            <CheckCheck className="size-4" aria-hidden /> Marcar como leída
          </Button>
        )
      )}
    </li>
  )
}
