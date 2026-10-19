import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { Tono } from '@/components/ui/insignia'
import { enviar, pedir } from './api'

/** El docente ve las alertas de sus aulas; el administrador, todas */
export type RolAlertas = 'docente' | 'admin'
export type NivelRiesgo = 'ALTO' | 'MEDIO' | 'BAJO'
export type EstadoAlerta = 'NUEVA' | 'EN_SEGUIMIENTO' | 'CERRADA' | 'DESCARTADA'

export const NIVEL: Record<NivelRiesgo, { texto: string; tono: Tono }> = {
  ALTO: { texto: 'Alto', tono: 'peligro' },
  MEDIO: { texto: 'Medio', tono: 'aviso' },
  BAJO: { texto: 'Bajo', tono: 'exito' },
}

export const ESTADO: Record<EstadoAlerta, { texto: string; tono: Tono }> = {
  NUEVA: { texto: 'Nueva', tono: 'aviso' },
  EN_SEGUIMIENTO: { texto: 'En seguimiento', tono: 'info' },
  CERRADA: { texto: 'Cerrada', tono: 'exito' },
  DESCARTADA: { texto: 'Descartada', tono: 'neutro' },
}

export type FilaAlerta = {
  id: number
  estudiante: { matricula_id: number; nombre: string; codigo: string }
  aula: string
  bimestre: string
  nivel: NivelRiesgo
  probabilidad: number
  estado: EstadoAlerta
  factor_principal: string | null
  responsable: string
  a_mi_cargo: boolean
  intervenciones: number
  creada: string
}

export type ListaAlertas = {
  datos: FilaAlerta[]
  pagina: number
  por_pagina: number
  total: number
  ultima_pagina: number
  resumen: { abiertas: number; nuevas: number; en_seguimiento: number; sin_intervencion: number; cerradas: number }
  aulas: { id: number; nombre: string }[]
}

export type Factor = { variable: string; titulo: string; valor: string; contribucion: number; descripcion: string }

export type DetalleAlerta = {
  alerta: {
    id: number
    estado: EstadoAlerta
    creada: string
    cerrada: string | null
    justificacion: string | null
    bimestre: string
  }
  estudiante: { matricula_id: number; nombre: string; codigo: string; aula: string; nivel_educativo: string }
  prediccion: {
    nivel: NivelRiesgo
    probabilidad: number
    fecha: string
    modelo: string
    resumen: string | null
    factores: Factor[]
    recomendaciones: { descripcion: string; prioridad: number }[]
  }
  anterior: { bimestre: string; nivel: NivelRiesgo; probabilidad: number } | null
  responsable: { docente_id: number; nombre: string }
  permisos: { gestionar: boolean; reasignar: boolean }
  transiciones: EstadoAlerta[]
  candidatos: { docente_id: number; nombre: string; rol: string }[]
  intervenciones: {
    id: number
    tipo: string
    descripcion: string | null
    fecha: string
    resultado: string | null
    registrado_por: string
  }[]
  historial: { accion: string; detalle: string | null; usuario: string; fecha: string }[]
  tipos_intervencion: string[]
}

export type FiltrosAlertas = {
  buscar: string
  nivel: string
  estado: string
  encargado: string
  aula_id: string
  pagina: number
}

export type AlertaAdministrativa = {
  id: number
  tipo: string
  categoria: string
  severidad: 'CRITICO' | 'ATENCION' | 'PENDIENTE'
  titulo: string
  detalle: string | null
  estado: 'ABIERTA' | 'RESUELTA'
  creada: string
  resuelta: string | null
  enlace: string
}

export type ListaAdministrativas = {
  datos: AlertaAdministrativa[]
  conteo: { abiertas: number; resueltas: number }
  tipos: { valor: string; texto: string }[]
}

export type Panel = {
  bimestre: { id: number; nombre: string; estado: string } | null
  bimestres: { id: number; nombre: string }[]
  ambito: string | null
  es_coordinador: boolean
  modelo?: string | null
  actualizado?: string | null
  kpis?: {
    ALTO: number
    MEDIO: number
    BAJO: number
    evaluados: number
    estudiantes: number
    sin_atender: number
    mas_antigua: string | null
    abiertas: number
  }
  anterior?: { bimestre: string; ALTO: number; MEDIO: number } | null
  por_aula?: { id: number; nombre: string; nivel: string; ALTO: number; MEDIO: number; BAJO: number }[]
  factores?: { variable: string; titulo: string; estudiantes: number; porcentaje: number }[]
  atencion?: {
    matricula_id: number
    nombre: string
    aula: string
    nivel: NivelRiesgo
    probabilidad: number
    factor_principal: string | null
    alerta_id: number | null
  }[]
  aulas?: { id: number; nombre: string; nivel: string }[]
}

const consulta = (f: Record<string, string | number>) => {
  const p = new URLSearchParams()
  for (const [k, v] of Object.entries(f)) if (v !== '' && v !== null && v !== undefined) p.set(k, String(v))
  const t = p.toString()
  return t ? `?${t}` : ''
}

export const useAlertas = (rol: RolAlertas, f: FiltrosAlertas) =>
  useQuery({
    queryKey: ['alertas', rol, 'lista', f],
    queryFn: () => pedir<ListaAlertas>(`/${rol}/alertas${consulta(f)}`),
    placeholderData: keepPreviousData,
  })

export const useAlerta = (rol: RolAlertas, id: number) =>
  useQuery({
    queryKey: ['alertas', rol, 'detalle', id],
    queryFn: () => pedir<DetalleAlerta>(`/${rol}/alertas/${id}`),
  })

export type AccionAlerta =
  | { tipo: 'estado'; datos: { estado: EstadoAlerta; justificacion?: string | null } }
  | { tipo: 'intervenciones'; datos: { tipo: string; descripcion: string; fecha: string; resultado: string | null } }
  | { tipo: 'responsable'; datos: { docente_id: number; justificacion: string } }

export function useAccionAlerta(rol: RolAlertas, id: number) {
  const cliente = useQueryClient()
  return useMutation({
    mutationFn: (a: AccionAlerta) =>
      enviar<DetalleAlerta & { mensaje: string }>(`/${rol}/alertas/${id}/${a.tipo}`, a.datos),
    onSuccess: (r) => {
      cliente.setQueryData(['alertas', rol, 'detalle', id], r)
      cliente.invalidateQueries({ queryKey: ['alertas', rol, 'lista'] })
      cliente.invalidateQueries({ queryKey: ['riesgo'] })
    },
  })
}

export const useAlertasAdministrativas = (f: { tipo: string; estado: string }) =>
  useQuery({
    queryKey: ['alertas', 'admin', 'administrativas', f],
    queryFn: () => pedir<ListaAdministrativas>(`/admin/alertas-administrativas${consulta(f)}`),
    placeholderData: keepPreviousData,
  })

const rutaPanel = (rol: RolAlertas) => (rol === 'admin' ? '/admin/riesgo' : '/docente/coordinacion')

export const usePanel = (rol: RolAlertas, f: { nivel: string; aula_id: string; bimestre_id: string }) =>
  useQuery({
    queryKey: ['riesgo', rol, f],
    queryFn: () => pedir<Panel>(`${rutaPanel(rol)}${consulta(f)}`),
    placeholderData: keepPreviousData,
  })

export function useRecalcular(rol: RolAlertas) {
  const cliente = useQueryClient()
  return useMutation({
    mutationFn: () => enviar<{ mensaje: string }>(`${rutaPanel(rol)}/recalcular`),
    onSuccess: () => {
      cliente.invalidateQueries({ queryKey: ['riesgo'] })
      cliente.invalidateQueries({ queryKey: ['alertas'] })
    },
  })
}

const FECHA_HORA = new Intl.DateTimeFormat('es-PE', {
  day: 'numeric',
  month: 'long',
  hour: 'numeric',
  minute: '2-digit',
  timeZone: 'America/Lima',
})

/** "29 de septiembre, 6:00 a. m." */
export const fechaHora = (iso: string) => FECHA_HORA.format(new Date(iso))
