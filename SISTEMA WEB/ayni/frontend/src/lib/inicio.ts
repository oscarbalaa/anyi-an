import { keepPreviousData, useQuery } from '@tanstack/react-query'
import type { NivelRiesgo } from './alertas'
import { pedir } from './api'

type Saludo = {
  nombre: string
  detalle: string | null
  fecha: string
  periodo: string | null
  bimestre: {
    id: number
    nombre: string
    estado: string
    fecha_cierre_notas: string
    dias_para_cierre: number
  } | null
}

export type Avance = Record<
  'asistencia' | 'notas' | 'promedios',
  { hechas: number; total: number; porcentaje: number | null }
>

export type InicioAdmin = Saludo & {
  kpis: {
    estudiantes: number
    docentes: number
    coordinadores: number
    alertas_estudiantiles: number
    alertas_administrativas: number
  }
  atencion: {
    id: number
    tipo: string
    severidad: 'CRITICO' | 'ATENCION' | 'PENDIENTE'
    titulo: string
    detalle: string | null
    enlace: string
  }[]
  avance: Avance | null
}

export type ClaseHoy = {
  curso_id: number
  curso: string
  inicio: string
  fin: string
  ambiente: string | null
  registrada: boolean
}

export type InicioDocente = Saludo & {
  tareas: {
    tipo: 'asistencia' | 'calificar' | 'seguimiento'
    titulo: string
    detalle: string
    extra: string
    enlace: string
  }[]
  coordinacion: {
    ambito: string
    ALTO: number
    MEDIO: number
    BAJO: number
    destacado: { nombre: string; factor_principal: string | null; alerta_id: number | null; nivel: NivelRiesgo } | null
  } | null
  hoy: ClaseHoy[]
}

export type InicioEstudiante = Saludo & {
  aula: string | null
  tutor: string | null
  /** Quien coordina su aula (la más cercana: aula, grado, nivel o colegio) */
  coordinador?: string | null
  promedio: number | null
  promedio_bimestre?: string | null
  cursos: { id: number; asignatura: string; promedio: number | null }[]
  asistencia: number | null
  pendientes: number
  por_entregar: { id: number; titulo: string; curso: string; fecha_limite: string }[]
  hoy: { curso: string; inicio: string; fin: string; ambiente: string | null }[]
}

export type Ficha = {
  estudiante: {
    matricula_id: number
    nombre: string
    codigo: string
    aula: string
    nivel_educativo: string
    edad: number | null
    tutor: string | null
    estado: string
  }
  bimestre: { id: number; nombre: string } | null
  riesgo: { nivel: NivelRiesgo; probabilidad: number; fecha: string } | null
  kpis: { promedio: number | null; asistencia: number | null; pendientes: number; incidencias: number }
  alerta: {
    id: number
    estado: string
    creada: string
    responsable: string
    historial: { accion: string; detalle: string | null; fecha: string }[]
  } | null
  bimestres: { id: number; nombre: string }[]
  notas: { curso: string; promedios: Record<string, number | null> }[]
  evolucion: { bimestre: string; nivel: NivelRiesgo; probabilidad: number }[]
  pendientes: { id: number; titulo: string; curso: string; fecha_limite: string }[]
  incidencias: {
    id: number
    fecha: string
    tipo: string
    gravedad: string
    descripcion: string
    registrado_por: string
  }[]
  apoderados: {
    nombre: string
    parentesco: string | null
    celular: string | null
    correo: string | null
    principal: boolean
  }[]
  factores: { titulo: string; descripcion: string }[]
}

export type CursoDocente = { id: number; nombre: string; asignatura: string; aula: string; aula_id: number }

export type MisEstudiantes = {
  cursos: CursoDocente[]
  curso: CursoDocente | null
  /** El aula de la que es tutor, si tiene una */
  tutoria: { aula_id: number; aula: string } | null
  /** CURSO: sus notas y sus clases; TUTORIA: toda el aula, con todos los cursos */
  modo: 'CURSO' | 'TUTORIA'
  bimestre: { id: number; nombre: string } | null
  total: number
  estudiantes: {
    matricula_id: number
    nombre: string
    codigo: string
    promedio: number | null
    asistencia: number | null
    nivel: NivelRiesgo | null
    /** Por qué tiene ese nivel: las reglas que se activaron */
    motivos: { titulo: string; descripcion: string }[]
  }[]
}

export type OpcionesReportes = {
  aulas: { id: number; nombre: string }[]
  bimestres: { id: number; nombre: string }[]
  bimestre: number | null
}

export const useInicio = <T>(rol: 'admin' | 'docente' | 'estudiante') =>
  useQuery({ queryKey: ['inicio', rol], queryFn: () => pedir<T>(`/${rol}/inicio`) })

export const useFicha = (rol: 'admin' | 'docente', matricula: number) =>
  useQuery({ queryKey: ['ficha', rol, matricula], queryFn: () => pedir<Ficha>(`/${rol}/fichas/${matricula}`) })

/** curso: un curso del docente; 'tutoria': toda su aula de tutoría; null: lo primero que tenga */
export const useMisEstudiantes = (curso: number | 'tutoria' | null) =>
  useQuery({
    queryKey: ['mis-estudiantes', curso],
    queryFn: () =>
      pedir<MisEstudiantes>(
        `/docente/estudiantes${curso === 'tutoria' ? '?tutoria=1' : curso ? `?curso_id=${curso}` : ''}`,
      ),
    placeholderData: keepPreviousData,
  })

export const useOpcionesReportes = (rol: 'admin' | 'docente') =>
  useQuery({ queryKey: ['reportes', rol], queryFn: () => pedir<OpcionesReportes>(`/${rol}/reportes/opciones`) })

/** "Buenos días" hasta las 12, "Buenas tardes" hasta las 19, luego "Buenas noches" (hora de Lima) */
export function saludoDelDia(ahora = new Date()): string {
  const hora = Number(
    new Intl.DateTimeFormat('en-US', { hour: 'numeric', hour12: false, timeZone: 'America/Lima' }).format(ahora),
  )
  return hora < 12 ? 'Buenos días' : hora < 19 ? 'Buenas tardes' : 'Buenas noches'
}

/** 13.6 → "13,6"; null → "—" */
export const nota = (n: number | null | undefined) =>
  n === null || n === undefined ? '—' : n.toLocaleString('es', { maximumFractionDigits: 1 })
