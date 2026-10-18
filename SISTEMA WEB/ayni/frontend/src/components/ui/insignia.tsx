import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

export type Tono = 'exito' | 'aviso' | 'peligro' | 'info' | 'neutro' | 'primario'

const TONOS: Record<Tono, string> = {
  exito: 'bg-exito/10 text-exito',
  aviso: 'bg-aviso/12 text-aviso',
  peligro: 'bg-peligro/10 text-peligro',
  info: 'bg-info/10 text-info',
  neutro: 'bg-texto/8 text-texto-suave',
  primario: 'bg-primario-suave text-primario-oscuro',
}

const PUNTOS: Record<Tono, string> = {
  exito: 'bg-exito',
  aviso: 'bg-aviso',
  peligro: 'bg-peligro',
  info: 'bg-info',
  neutro: 'bg-texto-suave',
  primario: 'bg-primario',
}

export function Insignia({
  tono = 'neutro',
  punto = false,
  children,
}: {
  tono?: Tono
  punto?: boolean
  children: ReactNode
}) {
  return (
    <span
      className={cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-sm font-semibold', TONOS[tono])}
    >
      {punto && <span className={cn('size-2 rounded-full', PUNTOS[tono])} aria-hidden />}
      {children}
    </span>
  )
}
