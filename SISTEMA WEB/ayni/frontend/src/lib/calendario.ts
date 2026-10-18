import { useQuery } from '@tanstack/react-query'
import { pedir } from './api'

export type CalendarioActual = {
  periodo: { id: number; nombre: string; fecha_inicio: string; fecha_fin: string } | null
  bimestre: {
    id: number
    numero: number
    nombre: string
    fecha_inicio: string
    fecha_fin: string
    fecha_cierre_notas: string
    estado: 'ABIERTO' | 'CERRADO'
    /** Días para la fecha de cierre de notas (null si ya cerró) */
    dias_para_cierre: number | null
  } | null
}

export function useCalendarioActual() {
  return useQuery({
    queryKey: ['calendario', 'actual'],
    queryFn: () => pedir<CalendarioActual>('/calendario/actual'),
    staleTime: 5 * 60_000,
  })
}
