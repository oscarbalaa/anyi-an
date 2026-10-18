import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { enviar, pedir } from './api'

export type SituacionBimestre = 'CERRADO' | 'EN_CURSO' | 'POR_CERRAR' | 'PENDIENTE' | 'SIN_DEFINIR'

export type Bimestre = {
  id: number | null
  numero: number
  nombre: string
  situacion: SituacionBimestre
  fecha_inicio?: string
  fecha_fin?: string
  fecha_cierre_notas?: string
  estado?: 'ABIERTO' | 'CERRADO'
  cerrado_automatico?: boolean
}

export type Periodo = {
  id: number
  nombre: string
  fecha_inicio: string
  fecha_fin: string
  estado: 'PLANIFICADO' | 'EN_CURSO' | 'CERRADO'
  aulas: number
  bimestres: Bimestre[]
}

export type Aula = {
  id: number
  nombre: string
  grado_id: number
  grado: number
  nivel: 'PRIMARIA' | 'SECUNDARIA'
  seccion: string
  capacidad: number
  ambiente: string | null
  matriculados: number
  cursos: number
  tutor: { id: number; nombre: string } | null
  coordinador: { id: number; nombre: string; ambito: string; propio: boolean } | null
}

export type Coordinacion = {
  id: number
  docente: { id: number; nombre: string }
  ambito: 'GRADO' | 'NIVEL' | 'INSTITUCION'
  alcance: string
}

export type Asignatura = {
  id: number
  codigo: string
  nombre: string
  horas_semanales: number
  estado: 'ACTIVO' | 'INACTIVO'
  cursos: number
}

export type TipoEvaluacion = { id: number; nombre: string; estado: 'ACTIVO' | 'INACTIVO'; evaluaciones: number }

export type CursoAula = {
  id: number
  asignatura: { id: number; codigo: string; nombre: string; horas_semanales: number | null }
  docente: { id: number; nombre: string }
  horas_programadas: number
}

export type Bloque = {
  id: number
  curso_id: number
  dia_semana: number
  hora_inicio: string
  hora_fin: string
  ambiente: string | null
}

export type Carga = {
  aula: {
    id: number
    nombre: string
    nivel: string
    periodo: string
    periodo_cerrado: boolean
    ambiente: string | null
  }
  cursos: CursoAula[]
  horario: Bloque[]
}

export type Opciones = {
  periodo_id: number | null
  periodos: { id: number; nombre: string; estado: Periodo['estado'] }[]
  grados: { id: number; nivel: string; nombre: string }[]
  docentes: { id: number; nombre: string; especialidad: string | null }[]
  asignaturas: { id: number; codigo: string; nombre: string; horas_semanales: number }[]
  aulas: { id: number; nombre: string; nivel: string }[]
}

export type Institucion = {
  institucion: {
    nombre: string
    codigo_modular: string | null
    ugel: string | null
    direccion: string | null
    telefono: string | null
    correo: string | null
    nota_aprobatoria: string
    umbral_asistencia_alto: number
    umbral_asistencia_medio: number
    umbral_promedio_alto: string
    umbral_promedio_medio: string
    umbral_pendientes_alto: number
    dias_cierre_bimestre: number
  }
  escala: { codigo: 'AD' | 'A' | 'B' | 'C'; descripcion: string; nota_minima: string; nota_maxima: string }[]
}

const A = '/admin'

export const useOpciones = (periodoId?: number | null) =>
  useQuery({
    queryKey: ['estructura', 'opciones', periodoId ?? null],
    queryFn: () => pedir<Opciones>(`${A}/estructura/opciones${periodoId ? `?periodo_id=${periodoId}` : ''}`),
  })

export const usePeriodos = () =>
  useQuery({
    queryKey: ['estructura', 'periodos'],
    queryFn: () => pedir<{ periodos: Periodo[] }>(`${A}/periodos`).then((r) => r.periodos),
  })

export const useAulas = (periodoId: number | null, nivel: string) =>
  useQuery({
    queryKey: ['estructura', 'aulas', periodoId, nivel],
    queryFn: () =>
      pedir<{ aulas: Aula[]; coordinaciones: Coordinacion[] }>(
        `${A}/aulas?periodo_id=${periodoId}${nivel ? `&nivel=${nivel}` : ''}`,
      ),
    enabled: periodoId !== null,
  })

export const useAsignaturas = () =>
  useQuery({
    queryKey: ['estructura', 'asignaturas'],
    queryFn: () => pedir<{ asignaturas: Asignatura[] }>(`${A}/asignaturas`).then((r) => r.asignaturas),
  })

export const useTipos = () =>
  useQuery({
    queryKey: ['estructura', 'tipos'],
    queryFn: () => pedir<{ tipos: TipoEvaluacion[] }>(`${A}/tipos-evaluacion`).then((r) => r.tipos),
  })

export const useCarga = (aulaId: number | null) =>
  useQuery({
    queryKey: ['estructura', 'carga', aulaId],
    queryFn: () => pedir<Carga>(`${A}/aulas/${aulaId}/carga`),
    enabled: aulaId !== null,
  })

export const useInstitucion = () =>
  useQuery({ queryKey: ['estructura', 'institucion'], queryFn: () => pedir<Institucion>(`${A}/institucion`) })

type Metodo = 'POST' | 'PUT' | 'PATCH' | 'DELETE'

/** Cualquier cambio de la estructura recarga sus listas (y el pie del menú con el bimestre) */
export function useCambio() {
  const cliente = useQueryClient()
  return useMutation({
    mutationFn: ({ metodo, ruta, datos }: { metodo: Metodo; ruta: string; datos?: unknown }) =>
      metodo === 'POST'
        ? enviar<{ mensaje: string; id?: number }>(`${A}${ruta}`, datos ?? {})
        : pedir<{ mensaje: string; id?: number }>(`${A}${ruta}`, {
            method: metodo,
            body: datos === undefined ? undefined : JSON.stringify(datos),
          }),
    onSuccess: () =>
      Promise.all([
        cliente.invalidateQueries({ queryKey: ['estructura'] }),
        cliente.invalidateQueries({ queryKey: ['calendario'] }),
      ]),
  })
}

const FECHA = new Intl.DateTimeFormat('es-PE', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' })
const FECHA_CORTA = new Intl.DateTimeFormat('es-PE', { day: 'numeric', month: 'short', timeZone: 'UTC' })

/** "2026-03-02" → "2 mar 2026" (las fechas de la BD no tienen hora) */
export const fecha = (iso?: string | null, corta = false) =>
  iso ? (corta ? FECHA_CORTA : FECHA).format(new Date(`${iso}T00:00:00Z`)).replace('.', '') : '—'

export const DIAS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']
export const DIAS_LARGOS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']
