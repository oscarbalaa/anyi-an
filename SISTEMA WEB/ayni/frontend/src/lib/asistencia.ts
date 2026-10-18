import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { Tono } from '@/components/ui/insignia'
import { pedir } from './api'

export type EstadoAsistencia = 'P' | 'F' | 'T' | 'J'
export type EstadoClase = 'SIN_REGISTRO' | 'BORRADOR' | 'DEFINITIVO'
/** El docente ve sus cursos; el administrador, todos */
export type RolAsistencia = 'docente' | 'admin'

export const ESTADOS: { valor: EstadoAsistencia; letra: string; texto: string }[] = [
  { valor: 'P', letra: 'P', texto: 'Presente' },
  { valor: 'F', letra: 'F', texto: 'Falta' },
  { valor: 'T', letra: 'T', texto: 'Tardanza' },
  { valor: 'J', letra: 'J', texto: 'Falta justificada' },
]

export const NOMBRE_ESTADO: Record<EstadoAsistencia, string> = {
  P: 'Presente',
  F: 'Falta',
  T: 'Tardanza',
  J: 'Falta justificada',
}

export const TONO_ESTADO: Record<EstadoAsistencia, Tono> = { P: 'exito', F: 'peligro', T: 'aviso', J: 'info' }

export type Bimestre = {
  id: number
  nombre: string
  estado: 'ABIERTO' | 'CERRADO'
  fecha_inicio: string
  fecha_fin: string
}

export type CursoAsistencia = {
  id: number
  nombre: string
  asignatura: string
  aula: string
  aula_id: number
  docente: string
}

export type MiCurso = CursoAsistencia & { dias: number[]; pendientes: number }

export type FechaClase = { fecha: string; estado: EstadoClase; hoy: boolean }

export type FilaClase = {
  matricula_id: number
  nombre: string
  codigo: string
  estado: EstadoAsistencia
  observacion: string | null
  faltas_semana: number
  corregido_por: string | null
}

export type Clase = {
  curso: CursoAsistencia
  fecha: string
  bimestre: Bimestre
  sesion: {
    estado: EstadoClase
    tema: string | null
    registrado_por: string | null
    actualizado_en: string | null
    editable: boolean
    aviso: string
  }
  estudiantes: FilaClase[]
  resumen: Record<EstadoAsistencia, number>
  aviso_faltas: number
}

export type Conteo = {
  clases: number
  presentes: number
  faltas: number
  tardanzas: number
  justificadas: number
  porcentaje: number | null
}

export type MiAsistencia = {
  periodo: string | null
  aula: string | null
  bimestre: Bimestre | null
  bimestres: Bimestre[]
  meta?: number
  resumen: Conteo | null
  por_curso: (Conteo & { curso_id: number; asignatura: string })[]
  por_mes: (Conteo & { mes: string })[]
  registros: {
    fecha: string
    curso: string
    hora: string | null
    estado: EstadoAsistencia
    observacion: string | null
  }[]
}

export type ResumenAdmin = {
  bimestre: Bimestre | null
  bimestres: Bimestre[]
  meta?: number
  aulas: {
    id: number
    nombre: string
    estudiantes: number
    clases: number
    faltas: number
    tardanzas: number
    justificadas: number
    porcentaje: number | null
  }[]
  sin_registro: {
    docente_id: number
    docente: string
    total: number
    desde: string
    cursos: { id: number; nombre: string; fechas: string[] }[]
  }[]
}

const base = (rol: RolAsistencia) => `/${rol}/asistencia`
const conBimestre = (id: number | null) => (id ? `?bimestre_id=${id}` : '')

export const useMisCursos = () =>
  useQuery({
    queryKey: ['asistencia', 'mis-cursos'],
    queryFn: () =>
      pedir<{ cursos: MiCurso[]; bimestre: Pick<Bimestre, 'id' | 'nombre' | 'estado'> | null }>(
        '/docente/asistencia/cursos',
      ),
  })

export const useCursosAdmin = () =>
  useQuery({
    queryKey: ['asistencia', 'cursos-admin'],
    queryFn: () =>
      pedir<{ periodo: string | null; aulas: { id: number; nombre: string; cursos: CursoAsistencia[] }[] }>(
        '/admin/asistencia/cursos',
      ),
  })

export const useFechasClase = (rol: RolAsistencia, curso: number | null, bimestre: number | null) =>
  useQuery({
    queryKey: ['asistencia', 'fechas', rol, curso, bimestre],
    queryFn: () =>
      pedir<{ curso: CursoAsistencia; bimestre: Bimestre; bimestres: Bimestre[]; fechas: FechaClase[] }>(
        `${base(rol)}/cursos/${curso}/fechas${conBimestre(bimestre)}`,
      ),
    enabled: curso !== null,
  })

export const useClase = (rol: RolAsistencia, curso: number | null, fecha: string | null) =>
  useQuery({
    queryKey: ['asistencia', 'clase', rol, curso, fecha],
    queryFn: () => pedir<Clase>(`${base(rol)}/cursos/${curso}/${fecha}`),
    enabled: curso !== null && Boolean(fecha),
  })

export const useMiAsistencia = (bimestre: number | null) =>
  useQuery({
    queryKey: ['asistencia', 'mia', bimestre],
    queryFn: () => pedir<MiAsistencia>(`/estudiante/asistencia${conBimestre(bimestre)}`),
  })

export const useResumenAsistencia = (bimestre: number | null) =>
  useQuery({
    queryKey: ['asistencia', 'resumen', bimestre],
    queryFn: () => pedir<ResumenAdmin>(`/admin/asistencia/resumen${conBimestre(bimestre)}`),
  })

export type DatosGuardar = {
  estado: 'BORRADOR' | 'DEFINITIVO'
  tema: string | null
  registros: { matricula_id: number; estado: EstadoAsistencia; observacion: string | null }[]
}

export function useGuardarClase(rol: RolAsistencia, curso: number, fecha: string) {
  const cliente = useQueryClient()
  return useMutation({
    mutationFn: (datos: DatosGuardar) =>
      pedir<Clase & { mensaje: string }>(`${base(rol)}/cursos/${curso}/${fecha}`, {
        method: 'PUT',
        body: JSON.stringify(datos),
      }),
    onSuccess: (clase) => {
      cliente.setQueryData(['asistencia', 'clase', rol, curso, fecha], clase)
      return cliente.invalidateQueries({ queryKey: ['asistencia'], predicate: (q) => q.queryKey[1] !== 'clase' })
    },
  })
}

export function useEliminarClase(curso: number, fecha: string) {
  const cliente = useQueryClient()
  return useMutation({
    mutationFn: (motivo: string) =>
      pedir<{ mensaje: string }>(`/admin/asistencia/cursos/${curso}/${fecha}`, {
        method: 'DELETE',
        body: JSON.stringify({ motivo }),
      }),
    onSuccess: () => cliente.invalidateQueries({ queryKey: ['asistencia'] }),
  })
}

const fecha = (iso: string) => {
  const [a, m, d] = iso.split('-').map(Number)
  return new Date(a, m - 1, d)
}

/** "2026-10-01" → "Jueves 1 de octubre de 2026" */
export const fechaLarga = (iso: string) => {
  const t = fecha(iso).toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  return t.charAt(0).toUpperCase() + t.slice(1).replace(',', '')
}

/** "2026-10-01" → "jue 1 oct" */
export const fechaCorta = (iso: string) =>
  fecha(iso).toLocaleDateString('es-PE', { weekday: 'short', day: 'numeric', month: 'short' }).replace(/[.,]/g, '')

/** "2026-10" → "Octubre" */
export const nombreMes = (mes: string) => {
  const t = fecha(`${mes}-01`).toLocaleDateString('es-PE', { month: 'long' })
  return t.charAt(0).toUpperCase() + t.slice(1)
}

/** 96.5 → "96,5 %"; 100 → "100 %" */
export const porcentaje = (p: number | null | undefined) =>
  p === null || p === undefined ? '—' : `${p.toLocaleString('es', { maximumFractionDigits: 1 })} %`

/** Fecha de hoy en Lima (YYYY-MM-DD) */
export const hoyIso = () => new Date().toLocaleDateString('en-CA', { timeZone: 'America/Lima' })
