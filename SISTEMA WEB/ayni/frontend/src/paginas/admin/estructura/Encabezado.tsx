import type { ReactNode } from 'react'

/** Título de tarjeta con su botón a la derecha */
export function Encabezado({ titulo, detalle, children }: { titulo: string; detalle?: string; children?: ReactNode }) {
  return (
    <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 className="text-2xl font-black">{titulo}</h2>
        {detalle && <p className="text-texto-suave">{detalle}</p>}
      </div>
      {children && <div className="flex flex-wrap gap-2">{children}</div>}
    </div>
  )
}
