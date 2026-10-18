import type { LucideIcon } from 'lucide-react'
import { useId, useRef, type KeyboardEvent, type ReactNode } from 'react'
import { cn } from '@/lib/utils'

type Pestana = { id: string; texto: string; icono: LucideIcon }

type Props = {
  pestanas: Pestana[]
  activa: string
  onCambiar: (id: string) => void
  children: ReactNode
  etiqueta: string
}

/** Pestañas con teclado (flechas, Inicio, Fin) según el patrón ARIA "tabs" */
export function Pestanas({ pestanas, activa, onCambiar, children, etiqueta }: Props) {
  const base = useId()
  const botones = useRef<(HTMLButtonElement | null)[]>([])

  const mover = (e: KeyboardEvent, i: number) => {
    const destino = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: pestanas.length - 1 }[e.key]
    if (destino === undefined) return
    e.preventDefault()
    const j = (destino + pestanas.length) % pestanas.length
    onCambiar(pestanas[j].id)
    botones.current[j]?.focus()
  }

  return (
    <div className="flex flex-col gap-6">
      <div
        role="tablist"
        aria-label={etiqueta}
        className="flex gap-1 overflow-x-auto overflow-y-hidden border-b border-borde"
      >
        {pestanas.map((p, i) => {
          const elegida = p.id === activa
          return (
            <button
              key={p.id}
              ref={(el) => {
                botones.current[i] = el
              }}
              type="button"
              role="tab"
              id={`${base}-${p.id}`}
              aria-selected={elegida}
              aria-controls={`${base}-panel`}
              tabIndex={elegida ? 0 : -1}
              onClick={() => onCambiar(p.id)}
              onKeyDown={(e) => mover(e, i)}
              className={cn(
                '-mb-px flex shrink-0 cursor-pointer items-center gap-2 border-b-3 px-4 py-3 font-bold whitespace-nowrap transition-colors',
                elegida
                  ? 'border-accion text-texto'
                  : 'border-transparent text-texto-suave hover:border-borde hover:text-texto',
              )}
            >
              <p.icono className="size-5" aria-hidden />
              {p.texto}
            </button>
          )
        })}
      </div>
      <div role="tabpanel" id={`${base}-panel`} aria-labelledby={`${base}-${activa}`}>
        {children}
      </div>
    </div>
  )
}
