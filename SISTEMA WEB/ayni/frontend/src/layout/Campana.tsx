import { Bell, CheckCheck } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router'
import { IconoNotificacion } from '@/components/IconoNotificacion'
import { hace, useMarcarLeida, useResumenNotificaciones } from '@/lib/notificaciones'
import { cn } from '@/lib/utils'

/** RF-51: campana con contador y las últimas 5 notificaciones */
export function Campana({ base }: { base: string }) {
  const [abierta, setAbierta] = useState(false)
  const ref = useRef<HTMLDivElement>(null)
  const { data } = useResumenNotificaciones()
  const marcar = useMarcarLeida()
  const pendientes = data?.pendientes ?? 0

  useEffect(() => {
    if (!abierta) return
    const fuera = (e: MouseEvent) => !ref.current?.contains(e.target as Node) && setAbierta(false)
    const esc = (e: KeyboardEvent) => e.key === 'Escape' && setAbierta(false)
    document.addEventListener('mousedown', fuera)
    document.addEventListener('keydown', esc)
    return () => {
      document.removeEventListener('mousedown', fuera)
      document.removeEventListener('keydown', esc)
    }
  }, [abierta])

  return (
    <div ref={ref} className="sm:relative">
      <button
        type="button"
        onClick={() => setAbierta((a) => !a)}
        className="relative grid size-10 cursor-pointer place-items-center rounded-lg hover:bg-primario-suave"
        aria-expanded={abierta}
        aria-controls="panel-notificaciones"
        aria-label={pendientes ? `Notificaciones (${pendientes} sin leer)` : 'Notificaciones'}
      >
        <Bell className="size-6" aria-hidden />
        {pendientes > 0 && (
          <span
            className="absolute -top-0.5 -right-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-peligro px-1 text-xs font-extrabold text-white"
            aria-hidden
            data-testid="contador-notificaciones"
          >
            {pendientes > 99 ? '99+' : pendientes}
          </span>
        )}
      </button>
      {abierta && (
        <section
          id="panel-notificaciones"
          aria-label="Últimas notificaciones"
          className="fixed inset-x-4 top-16 z-40 animate-aparecer rounded-2xl border border-borde bg-superficie shadow-lg sm:absolute sm:inset-x-auto sm:top-auto sm:right-0 sm:mt-2 sm:w-96"
        >
          <header className="flex items-center justify-between gap-2 border-b border-borde px-4 py-3">
            <h2 className="font-extrabold">Notificaciones</h2>
            {pendientes > 0 && (
              <button
                type="button"
                onClick={() => marcar.mutate('todas')}
                disabled={marcar.isPending}
                className="flex cursor-pointer items-center gap-1 rounded-lg px-2 py-1 text-sm font-bold text-accion hover:bg-primario-suave"
              >
                <CheckCheck className="size-4" aria-hidden /> Marcar todas como leídas
              </button>
            )}
          </header>
          {!data ? (
            <p className="px-4 py-6 text-center text-texto-suave">Cargando…</p>
          ) : data.recientes.length === 0 ? (
            <p className="px-4 py-6 text-center text-texto-suave">Aún no tienes notificaciones.</p>
          ) : (
            <ul className="max-h-[60vh] overflow-y-auto p-1.5">
              {data.recientes.map((n) => (
                <li key={n.id}>
                  <Link
                    to={n.enlace ?? `${base}/notificaciones`}
                    onClick={() => {
                      setAbierta(false)
                      if (!n.leida) marcar.mutate(n.id)
                    }}
                    className={cn('flex gap-3 rounded-xl p-2.5 hover:bg-fondo', !n.leida && 'bg-primario-suave/40')}
                  >
                    <IconoNotificacion tipo={n.tipo} chico />
                    <span className="min-w-0 flex-1">
                      <span className="flex items-center gap-2 font-bold">
                        {!n.leida && <span className="size-2 shrink-0 rounded-full bg-accion" aria-hidden />}
                        <span className="truncate">{n.titulo}</span>
                        {!n.leida && <span className="sr-only">(sin leer)</span>}
                      </span>
                      <span className="line-clamp-2 text-sm text-texto-suave">{n.mensaje}</span>
                      <span className="text-xs text-texto-suave">{hace(n.fecha)}</span>
                    </span>
                  </Link>
                </li>
              ))}
            </ul>
          )}
          <footer className="border-t border-borde p-1.5">
            <Link
              to={`${base}/notificaciones`}
              onClick={() => setAbierta(false)}
              className="block rounded-xl px-3 py-2.5 text-center font-bold text-accion hover:bg-primario-suave"
            >
              Ver todas
            </Link>
          </footer>
        </section>
      )}
    </div>
  )
}
