import { LoaderCircle } from 'lucide-react'
import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

export function EstadoVacio({
  titulo,
  descripcion,
  accion,
}: {
  titulo: string
  descripcion?: string
  accion?: ReactNode
}) {
  return (
    <div className="flex flex-col items-center gap-2 px-4 py-10 text-center">
      <p className="text-lg font-extrabold">{titulo}</p>
      {descripcion && <p className="max-w-md text-texto-suave">{descripcion}</p>}
      {accion && <div className="mt-2">{accion}</div>}
    </div>
  )
}

export function Cargando({ texto = 'Cargando…' }: { texto?: string }) {
  return (
    <p role="status" className="flex items-center justify-center gap-2 py-10 text-texto-suave">
      <LoaderCircle className="size-5 animate-spin" aria-hidden />
      {texto}
    </p>
  )
}

export function Esqueleto({ className }: { className?: string }) {
  return <div aria-hidden className={cn('animate-pulse rounded-lg bg-texto/8', className)} />
}
