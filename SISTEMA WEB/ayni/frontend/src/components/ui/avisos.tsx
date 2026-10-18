import { CheckCircle2, Info, TriangleAlert, X, XCircle } from 'lucide-react'
import { useCallback, useRef, useState, type ReactNode } from 'react'
import { cn } from '@/lib/utils'

import { ContextoAvisos, type TipoAviso } from './avisos-contexto'

type Aviso = { id: number; tipo: TipoAviso; mensaje: string }

const ESTILO: Record<TipoAviso, { clase: string; icono: ReactNode }> = {
  exito: { clase: 'border-exito/30', icono: <CheckCircle2 className="size-5 text-exito" /> },
  error: { clase: 'border-peligro/30', icono: <XCircle className="size-5 text-peligro" /> },
  aviso: { clase: 'border-aviso/30', icono: <TriangleAlert className="size-5 text-aviso" /> },
  info: { clase: 'border-info/30', icono: <Info className="size-5 text-info" /> },
}

export function ProveedorAvisos({ children }: { children: ReactNode }) {
  const [avisos, setAvisos] = useState<Aviso[]>([])
  const siguiente = useRef(1)

  const cerrar = useCallback((id: number) => setAvisos((a) => a.filter((x) => x.id !== id)), [])
  const mostrar = useCallback(
    (tipo: TipoAviso, mensaje: string) => {
      const id = siguiente.current++
      setAvisos((a) => [...a.slice(-2), { id, tipo, mensaje }])
      window.setTimeout(() => cerrar(id), 5000)
    },
    [cerrar],
  )

  return (
    <ContextoAvisos.Provider value={mostrar}>
      {children}
      <div
        aria-live="polite"
        className="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6"
      >
        {avisos.map((a) => (
          <div
            key={a.id}
            role={a.tipo === 'error' ? 'alert' : 'status'}
            className={cn(
              'pointer-events-auto flex w-full max-w-sm animate-aparecer items-start gap-3 rounded-xl border bg-superficie p-4 text-texto shadow-lg',
              ESTILO[a.tipo].clase,
            )}
          >
            <span aria-hidden>{ESTILO[a.tipo].icono}</span>
            <p className="flex-1">{a.mensaje}</p>
            <button
              type="button"
              onClick={() => cerrar(a.id)}
              className="cursor-pointer text-texto-suave hover:text-texto"
              aria-label="Cerrar aviso"
            >
              <X className="size-4" aria-hidden />
            </button>
          </div>
        ))}
      </div>
    </ContextoAvisos.Provider>
  )
}
