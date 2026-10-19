import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { Tono } from '@/components/ui/insignia'
import { enviar, pedir } from './api'

/** El docente ve a sus estudiantes; el administrador, a todos */
export type RolIncidencias = 'docente' | 'admin'

export type Gravedad = 'LEVE' | 'MODERADA' | 'GRAVE'

export const GRAVEDADES: { valor: Gravedad; texto: string; tono: Tono }[] = [
  { valor: 'LEVE', texto: 'Leve', tono: 'exito' },
  { valor: 'MODERADA', texto: 'Moderada', tono: 'aviso' },
  { valor: 'GRAVE', texto: 'Grave', tono: 'peligro' },
]

export type Incidencia = {
  id: number
  fecha: string
  gravedad: Gravedad
  tipo: string
  descripcion: string
  estudiante: { matricula_id: number; nombre: string; codigo: string }
  aula: string
  registrado_por: string
}

export type ListaIncidencias = {
  datos: Incidencia[]
  pagina: number
  por_pagina: number
  total: number
  ultima_pagina: number
  resumen: Record<Gravedad, number>
  tipos: { id: number; nombre: string; descripcion: string | null }[]
  aulas: { id: number; nombre: string }[]
}

export type EstudianteIncidencia = { matricula_id: number; nombre: string; codigo: string; aula: string }

export type FiltrosIncidencias = {
  buscar: string
  tipo_id: string
  gravedad: string
  aula_id: string
  matricula_id: string
  pagina: number
}

const consulta = (f: Record<string, string | number>) => {
  const p = new URLSearchParams()
  for (const [k, v] of Object.entries(f)) if (v !== '' && v !== null) p.set(k, String(v))
  const t = p.toString()
  return t ? `?${t}` : ''
}

export const useIncidencias = (rol: RolIncidencias, f: FiltrosIncidencias) =>
  useQuery({
    queryKey: ['incidencias', rol, 'lista', f],
    queryFn: () => pedir<ListaIncidencias>(`/${rol}/incidencias${consulta(f)}`),
    placeholderData: keepPreviousData,
  })

export const useEstudiantesIncidencia = (rol: RolIncidencias, buscar: string) =>
  useQuery({
    queryKey: ['incidencias', rol, 'estudiantes', buscar],
    queryFn: () =>
      pedir<{ estudiantes: EstudianteIncidencia[] }>(`/${rol}/incidencias/estudiantes${consulta({ buscar })}`),
    placeholderData: keepPreviousData,
  })

export type NuevaIncidencia = {
  matricula_id: number
  tipo_incidencia_id: number
  gravedad: Gravedad
  fecha: string
  descripcion: string
}

export function useRegistrarIncidencia(rol: RolIncidencias) {
  const cliente = useQueryClient()
  return useMutation({
    mutationFn: (d: NuevaIncidencia) => enviar<{ mensaje: string; id: number }>(`/${rol}/incidencias`, d),
    onSuccess: () => cliente.invalidateQueries({ queryKey: ['incidencias', rol] }),
  })
}
