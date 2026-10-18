import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'
import type { Tono } from './insignia'

const TONOS: Record<Tono, string> = {
  exito: 'bg-exito/10 text-exito',
  aviso: 'bg-aviso/12 text-aviso',
  peligro: 'bg-peligro/10 text-peligro',
  info: 'bg-info/10 text-info',
  neutro: 'bg-texto/8 text-texto',
  primario: 'bg-primario-suave text-primario-oscuro',
}

type Props = { icono: ReactNode; valor: ReactNode; etiqueta: string; tono?: Tono }

export function TarjetaKpi({ icono, valor, etiqueta, tono = 'primario' }: Props) {
  return (
    <div className="flex items-center gap-4 rounded-2xl border border-borde bg-superficie p-4 sm:p-5">
      <span
        className={cn('grid size-14 shrink-0 place-items-center rounded-full [&_svg]:size-7', TONOS[tono])}
        aria-hidden
      >
        {icono}
      </span>
      <div className="min-w-0">
        <p className={cn('text-3xl leading-none font-extrabold', TONOS[tono].split(' ')[1])}>{valor}</p>
        <p className="mt-1 text-texto-suave">{etiqueta}</p>
      </div>
    </div>
  )
}
