import {
  Bell,
  CalendarClock,
  CalendarX,
  ChartColumn,
  Clock,
  FileWarning,
  TriangleAlert,
  UserPlus,
  type LucideIcon,
} from 'lucide-react'
import { cn } from '@/lib/utils'

const ICONOS: Record<string, { icono: LucideIcon; clase: string }> = {
  NOTA_PUBLICADA: { icono: ChartColumn, clase: 'bg-exito/10 text-exito' },
  FALTA_REGISTRADA: { icono: CalendarX, clase: 'bg-peligro/10 text-peligro' },
  ACTIVIDAD_POR_VENCER: { icono: Clock, clase: 'bg-aviso/12 text-aviso' },
  CIERRE_PROXIMO: { icono: CalendarClock, clase: 'bg-aviso/12 text-aviso' },
  INCIDENCIA_REGISTRADA: { icono: FileWarning, clase: 'bg-info/10 text-info' },
  INCIDENCIA_GRAVE: { icono: TriangleAlert, clase: 'bg-peligro/10 text-peligro' },
  ALERTA_ASIGNADA: { icono: UserPlus, clase: 'bg-info/10 text-info' },
}

export function IconoNotificacion({ tipo, chico = false }: { tipo: string; chico?: boolean }) {
  const { icono: Icono, clase } = ICONOS[tipo] ?? { icono: Bell, clase: 'bg-primario-suave text-primario-oscuro' }
  return (
    <span
      className={cn('grid shrink-0 place-items-center rounded-full', chico ? 'size-9' : 'size-12', clase)}
      aria-hidden
    >
      <Icono className={chico ? 'size-4' : 'size-6'} />
    </span>
  )
}
