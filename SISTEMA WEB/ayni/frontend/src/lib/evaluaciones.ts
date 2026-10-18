import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { Tono } from '@/components/ui/insignia'
import { enviar, pedir, subir } from './api'
import type { Bimestre, CursoAsistencia } from './asistencia'

export type EstadoEntrega = 'PENDIENTE' | 'ENTREGADO' | 'TARDIO' | 'NO_ENTREGADO'
/** Estado de una actividad para el estudiante */
export type EstadoActividad = EstadoEntrega | 'EN_PROGRESO' | 'EN_CLASE'
export type EstadoCalificacion = 'BORRADOR' | 'PUBLICADA' | 'CERRADA'

export const ESTADO_ENTREGA: Record<EstadoActividad, { texto: string; tono: Tono }> = {
  PENDIENTE: { texto: 'Pendiente', tono: 'peligro' },
  EN_PROGRESO: { texto: 'En progreso', tono: 'aviso' },
  ENTREGADO: { texto: 'Entregado', tono: 'exito' },
  TARDIO: { texto: 'Tardío', tono: 'aviso' },
  NO_ENTREGADO: { texto: 'No entregado', tono: 'neutro' },
  EN_CLASE: { texto: 'En clase', tono: 'info' },
}

export type Archivo = { id: number; nombre: string; tamano: number; tipo: string }

export type FilaEvaluacion = {
  id: number
  titulo: string
  tipo: string
  tipo_evaluacion_id: number
  peso: number
  fecha_asignacion: string
  fecha_limite: string
  indicaciones: string | null
  requiere_entrega: boolean
  momento: 'POR_ASIGNAR' | 'EN_CURSO' | 'VENCIDA'
  entregas: number
  calificadas: number
  estudiantes: number
  material: Archivo[]
}

export type ListaEvaluaciones = {
  curso: CursoAsistencia
  bimestre: Bimestre
  bimestres: Bimestre[]
  tipos: { id: number; nombre: string }[]
  resumen: { evaluaciones: number; entregas: number | null; por_calificar: number }
  evaluaciones: FilaEvaluacion[]
  editable: boolean
  limite_archivo: string
}

export type FilaNota = {
  matricula_id: number
  nombre: string
  codigo: string
  nota: number | null
  estado_entrega: EstadoEntrega
  estado_sugerido: EstadoEntrega
  observacion: string | null
  entrega: { entregada_en: string; es_tardia: boolean; archivo: Archivo | null } | null
}

export type Planilla = {
  evaluacion: {
    id: number
    titulo: string
    tipo: string
    peso: number
    fecha_asignacion: string
    fecha_limite: string
    requiere_entrega: boolean
  }
  curso: CursoAsistencia
  bimestre: Bimestre
  nota_aprobatoria: number
  publicada: boolean
  editable: boolean
  resumen: { promedio: number | null; desaprobados: number; sin_entregar: number }
  estudiantes: FilaNota[]
}

export type VistaCalificaciones = {
  curso: CursoAsistencia
  bimestre: Bimestre
  bimestres: Bimestre[]
  estado: EstadoCalificacion
  publicado_en: string | null
  nota_aprobatoria: number
  evaluaciones: { id: number; titulo: string; tipo: string; peso: number; fecha_limite: string }[]
  estudiantes: {
    matricula_id: number
    nombre: string
    notas: Record<string, number | null>
    promedio: number | null
    literal: string | null
  }[]
  resumen: { estudiantes: number; promedio: number | null; desaprobados: number }
  sin_nota: { id: number; titulo: string; fecha_limite: string; estudiantes: string[] }[]
  editable: boolean
}

export type Actividad = {
  id: number
  titulo: string
  tipo: string
  curso: string
  curso_id: number
  docente: string
  fecha_asignacion: string
  fecha_limite: string
  peso: number
  peso_porcentaje: number
  requiere_entrega: boolean
  estado: EstadoActividad
  nota: number | null
}

export type MisActividades = {
  bimestre: Bimestre | null
  bimestres: Bimestre[]
  resumen: { pendientes: number; por_vencer: number; completadas: number } | null
  primera?: Actividad | null
  actividades: Actividad[]
}

export type DetalleActividad = Actividad & {
  indicaciones: string | null
  bimestre: Bimestre
  material: Archivo[]
  entrega: {
    estado: 'BORRADOR' | 'ENTREGADA'
    entregada_en: string | null
    es_tardia: boolean
    archivo: Archivo | null
  } | null
  historial: { fecha: string; texto: string }[]
  puede_entregar: boolean
  aviso: string
  limite_archivo: string
}

export type MisNotas = {
  aula: string | null
  bimestre: Bimestre | null
  bimestres: Bimestre[]
  cursos: {
    id: number
    asignatura: string
    docente: string
    publicado: boolean
    promedio: number | null
    literal: string | null
  }[]
  promedio: number | null
  anterior: { nombre: string; promedio: number | null } | null
  ultimas: { id: number; titulo: string; curso: string; nota: number; fecha: string }[]
  escala?: { codigo: string; desde: number }[]
}

export type NotasCurso = {
  curso: CursoAsistencia
  bimestre: Bimestre
  bimestres: Bimestre[]
  publicado: boolean
  promedio: number | null
  literal: string | null
  evaluaciones: Actividad[]
  resumen: { calificadas: number; entregadas: number; por_entregar: number }
  evolucion: { bimestre: string; promedio: number | null }[]
  proxima: Actividad | null
}

export type ClaseHorario = {
  dia: number
  inicio: string
  fin: string
  ambiente: string | null
  curso_id: number
  curso: string
  docente: string
}

export type MiHorario = {
  aula: string | null
  bloques: { inicio: string; fin: string }[]
  clases: ClaseHorario[]
  proxima: (ClaseHorario & { fecha: string }) | null
}

const conBimestre = (id: number | null) => (id ? `?bimestre_id=${id}` : '')

export const useEvaluaciones = (curso: number | null, bimestre: number | null) =>
  useQuery({
    queryKey: ['evaluaciones', 'lista', curso, bimestre],
    queryFn: () => pedir<ListaEvaluaciones>(`/docente/cursos/${curso}/evaluaciones${conBimestre(bimestre)}`),
    enabled: curso !== null,
  })

export const usePlanilla = (evaluacion: number | null) =>
  useQuery({
    queryKey: ['evaluaciones', 'planilla', evaluacion],
    queryFn: () => pedir<Planilla>(`/docente/evaluaciones/${evaluacion}/notas`),
    enabled: evaluacion !== null,
  })

export const useCalificaciones = (curso: number | null, bimestre: number | null) =>
  useQuery({
    queryKey: ['evaluaciones', 'calificaciones', curso, bimestre],
    queryFn: () => pedir<VistaCalificaciones>(`/docente/cursos/${curso}/calificaciones${conBimestre(bimestre)}`),
    enabled: curso !== null,
  })

export const useMisActividades = (bimestre: number | null) =>
  useQuery({
    queryKey: ['evaluaciones', 'mis-actividades', bimestre],
    queryFn: () => pedir<MisActividades>(`/estudiante/actividades${conBimestre(bimestre)}`),
  })

export const useActividad = (id: number | null) =>
  useQuery({
    queryKey: ['evaluaciones', 'actividad', id],
    queryFn: () => pedir<DetalleActividad>(`/estudiante/actividades/${id}`),
    enabled: id !== null,
  })

export const useMisNotas = (bimestre: number | null) =>
  useQuery({
    queryKey: ['evaluaciones', 'mis-notas', bimestre],
    queryFn: () => pedir<MisNotas>(`/estudiante/notas${conBimestre(bimestre)}`),
  })

export const useNotasCurso = (curso: number | null, bimestre: number | null) =>
  useQuery({
    queryKey: ['evaluaciones', 'notas-curso', curso, bimestre],
    queryFn: () => pedir<NotasCurso>(`/estudiante/notas/cursos/${curso}${conBimestre(bimestre)}`),
    enabled: curso !== null,
  })

export const useMiHorario = () =>
  useQuery({ queryKey: ['evaluaciones', 'horario'], queryFn: () => pedir<MiHorario>('/estudiante/horario') })

type Respuesta = { mensaje: string; id?: number }

/** Cambios del docente y del estudiante: recargan todo lo de evaluaciones */
export function useCambioEvaluaciones() {
  const cliente = useQueryClient()
  return useMutation({
    mutationFn: ({
      metodo,
      ruta,
      datos,
    }: {
      metodo: 'POST' | 'PUT' | 'DELETE' | 'SUBIR'
      ruta: string
      datos?: unknown
    }) =>
      metodo === 'SUBIR'
        ? subir<Respuesta & Record<string, unknown>>(ruta, datos as FormData)
        : metodo === 'POST'
          ? enviar<Respuesta & Record<string, unknown>>(ruta, datos ?? {})
          : pedir<Respuesta & Record<string, unknown>>(ruta, {
              method: metodo,
              body: datos === undefined ? undefined : JSON.stringify(datos),
            }),
    onSuccess: () => cliente.invalidateQueries({ queryKey: ['evaluaciones'] }),
  })
}

/** 15.5 → "15,5"; null → "—" */
export const nota = (n: number | null | undefined, vacio = '—') =>
  n === null || n === undefined ? vacio : n.toLocaleString('es', { maximumFractionDigits: 2 })

/** 1258291 → "1,2 MB" */
export const tamano = (bytes: number) =>
  bytes >= 1024 * 1024
    ? `${(bytes / 1024 / 1024).toLocaleString('es', { maximumFractionDigits: 1 })} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} KB`
