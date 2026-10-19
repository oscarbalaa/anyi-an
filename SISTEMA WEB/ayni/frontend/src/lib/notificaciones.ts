import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { enviar, pedir } from './api'

export type Notificacion = {
  id: number
  tipo: string
  titulo: string
  mensaje: string
  enlace: string | null
  leida: boolean
  fecha: string
}

export type FiltroNotificaciones = 'todas' | 'pendientes' | 'leidas'

export type ListaNotificaciones = {
  datos: Notificacion[]
  pagina: number
  por_pagina: number
  total: number
  ultima_pagina: number
  conteo: Record<FiltroNotificaciones, number>
}

export type ResumenNotificaciones = { pendientes: number; recientes: Notificacion[] }

/** La campana se revisa cada minuto mientras la pestaña está abierta (no hay cron ni websockets) */
export const useResumenNotificaciones = () =>
  useQuery({
    queryKey: ['notificaciones', 'resumen'],
    queryFn: () => pedir<ResumenNotificaciones>('/notificaciones/resumen'),
    refetchInterval: 60_000,
    staleTime: 30_000,
  })

export const useNotificaciones = (filtro: FiltroNotificaciones, pagina: number) =>
  useQuery({
    queryKey: ['notificaciones', 'lista', filtro, pagina],
    queryFn: () => pedir<ListaNotificaciones>(`/notificaciones?filtro=${filtro}&pagina=${pagina}`),
    placeholderData: keepPreviousData,
  })

export function useMarcarLeida() {
  const cliente = useQueryClient()
  return useMutation({
    mutationFn: (id: number | 'todas') =>
      enviar<{ mensaje: string }>(id === 'todas' ? '/notificaciones/leer-todas' : `/notificaciones/${id}/leer`),
    onSuccess: () => cliente.invalidateQueries({ queryKey: ['notificaciones'] }),
  })
}

/** Texto del botón según a dónde lleva */
export function textoAccion(n: Notificacion): string {
  const enlace = n.enlace ?? ''
  if (enlace.includes('/actividades')) return 'Ver actividad'
  if (enlace.includes('/estudiante/notas')) return 'Ver notas'
  if (enlace.includes('/asistencia')) return 'Ver asistencia'
  if (enlace.includes('/calificaciones')) return 'Ir a calificaciones'
  if (enlace.includes('/incidencias')) return 'Ver incidencias'
  return 'Abrir'
}

const MINUTO = 60_000
const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'set', 'oct', 'nov', 'dic']
const DIA_LIMA = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Lima' })

/** "Hace 5 min", "Hace 3 h", "Ayer", "12 oct" */
export function hace(iso: string, ahora = new Date()): string {
  const fecha = new Date(iso)
  const diferencia = ahora.getTime() - fecha.getTime()
  if (diferencia < MINUTO) return 'Ahora'
  if (diferencia < 60 * MINUTO) return `Hace ${Math.floor(diferencia / MINUTO)} min`
  const hoy = DIA_LIMA.format(ahora)
  if (DIA_LIMA.format(fecha) === hoy) return `Hace ${Math.floor(diferencia / (60 * MINUTO))} h`
  if (DIA_LIMA.format(fecha) === DIA_LIMA.format(new Date(ahora.getTime() - 1440 * MINUTO))) return 'Ayer'
  const [, mes, dia] = DIA_LIMA.format(fecha).split('-').map(Number)
  return `${dia} ${MESES[mes - 1]}`
}
