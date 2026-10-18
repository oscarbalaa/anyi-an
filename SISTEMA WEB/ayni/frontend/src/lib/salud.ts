import { useQuery } from '@tanstack/react-query'
import { pedir } from './api'

export type Salud = {
  estado: 'ok' | 'sin_base_de_datos'
  aplicacion: string
  version: string
  colegio: string
  hora_servidor: string
  zona_horaria: string
  base_de_datos: { conectada: boolean; motor?: string; version?: string }
  entorno?: string
  php?: string
  laravel?: string
}

export function useSalud() {
  return useQuery({
    queryKey: ['salud'],
    queryFn: () => pedir<Salud>('/health'),
  })
}
