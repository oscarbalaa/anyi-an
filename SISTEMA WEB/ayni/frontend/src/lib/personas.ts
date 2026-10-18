import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { enviar, pedir } from './api'

export type Pagina<T> = { datos: T[]; pagina: number; por_pagina: number; total: number; ultima_pagina: number }

export type EstadoDocente = 'ACTIVO' | 'INACTIVO' | 'LICENCIA'
export type EstadoEstudiante = 'ACTIVO' | 'RETIRADO' | 'TRASLADADO' | 'EGRESADO'
export type EstadoCuenta = 'ACTIVO' | 'INACTIVO' | 'BLOQUEADO'

type DatosPersona = {
  tipo_documento: 'DNI' | 'CE' | 'PASAPORTE'
  numero_documento: string
  nombres: string
  apellidos: string
  fecha_nacimiento: string | null
  sexo: 'M' | 'F' | null
  celular: string | null
  correo: string | null
  direccion: string | null
}

export type FilaDocente = {
  id: number
  nombre: string
  documento: string
  especialidad: string | null
  estado: EstadoDocente
  cuenta: EstadoCuenta
  coordina: string | null
  cursos: number
}

export type Docente = Omit<FilaDocente, 'cursos'> &
  DatosPersona & {
    titulo: string | null
    fecha_inicio_contrato: string | null
    fecha_fin_contrato: string | null
    usuario: string
    ultimo_acceso: string | null
    tutoria: string | null
    cursos: string[]
  }

export type FilaEstudiante = {
  id: number
  nombre: string
  documento: string
  codigo: string
  estado: EstadoEstudiante
  cuenta: EstadoCuenta
  origen: 'MANUAL' | 'EXCEL'
  registrado_por: string
  aula: string | null
}

export type Apoderado = {
  id: number
  nombres: string
  celular: string | null
  correo: string | null
  parentesco: string
  es_principal: boolean
}

export type Estudiante = FilaEstudiante &
  DatosPersona & {
    fecha_ingreso: string
    usuario: string
    ultimo_acceso: string | null
    matriculas: {
      id: number
      periodo: string
      en_curso: boolean
      aula: string
      fecha: string
      estado: EstadoMatricula
      motivo: string | null
    }[]
    apoderados: Apoderado[]
  }

export type EstadoMatricula = 'ACTIVA' | 'RETIRADA' | 'ANULADA'

export type FilaMatricula = {
  id: number
  estudiante: { id: number; nombre: string; codigo: string }
  aula: string
  fecha: string
  estado: EstadoMatricula
  motivo: string | null
}

export type AulaConCupos = { id: number; nombre: string; nivel: string; capacidad: number; matriculados: number }

export type Credencial = { codigo: string; nombre: string; contrasena: string | null }

export type RevisionImportacion = {
  filas: number
  validas: number
  periodo: string | null
  errores: { fila: number; mensajes: string[] }[]
}

const A = '/admin'

const consulta = (filtros: Record<string, string | number | null | undefined>) => {
  const p = new URLSearchParams()
  for (const [k, v] of Object.entries(filtros))
    if (v !== '' && v !== null && v !== undefined && !(k === 'pagina' && v === 1)) p.set(k, String(v))
  const s = p.toString()
  return s ? `?${s}` : ''
}

export const useDocentes = (f: { buscar: string; estado: string; pagina: number }) =>
  useQuery({
    queryKey: ['personas', 'docentes', f],
    queryFn: () =>
      pedir<Pagina<FilaDocente> & { resumen: Partial<Record<EstadoDocente, number>> }>(`${A}/docentes${consulta(f)}`),
    placeholderData: keepPreviousData,
  })

export const useDocente = (id: number | null) =>
  useQuery({
    queryKey: ['personas', 'docente', id],
    queryFn: () => pedir<{ docente: Docente }>(`${A}/docentes/${id}`).then((r) => r.docente),
    enabled: id !== null,
  })

export const useEstudiantes = (f: { buscar: string; aula: string; estado: string; pagina: number }) =>
  useQuery({
    queryKey: ['personas', 'estudiantes', f],
    queryFn: () => pedir<Pagina<FilaEstudiante> & { registrados: number }>(`${A}/estudiantes${consulta(f)}`),
    placeholderData: keepPreviousData,
  })

export const useEstudiante = (id: number | null) =>
  useQuery({
    queryKey: ['personas', 'estudiante', id],
    queryFn: () => pedir<{ estudiante: Estudiante }>(`${A}/estudiantes/${id}`).then((r) => r.estudiante),
    enabled: id !== null,
  })

export const useMatriculas = (f: {
  periodo_id: number | null
  aula_id: string
  estado: string
  buscar: string
  pagina: number
}) =>
  useQuery({
    queryKey: ['personas', 'matriculas', f],
    queryFn: () => pedir<Pagina<FilaMatricula> & { aulas: AulaConCupos[] }>(`${A}/matriculas${consulta(f)}`),
    enabled: f.periodo_id !== null,
    placeholderData: keepPreviousData,
  })

export const useCandidatos = (periodoId: number | null, buscar: string) =>
  useQuery({
    queryKey: ['personas', 'candidatos', periodoId, buscar],
    queryFn: () =>
      pedir<{ estudiantes: { id: number; nombre: string; codigo: string }[] }>(
        `${A}/matriculas/candidatos${consulta({ periodo_id: periodoId, buscar })}`,
      ).then((r) => r.estudiantes),
    enabled: periodoId !== null,
    placeholderData: keepPreviousData,
  })

type Metodo = 'POST' | 'PUT' | 'DELETE'

/** Respuesta de los cambios: siempre trae el mensaje; al crear, también el registro y la contraseña */
export type RespuestaCambio = {
  mensaje: string
  id?: number
  contrasena?: string
  correo_enviado?: boolean
  [clave: string]: unknown
}

/** Cambios de personas: recargan sus listas y la de usuarios (las cuentas se crean con ellas) */
export function useCambioPersonas() {
  const cliente = useQueryClient()
  return useMutation({
    mutationFn: ({ metodo, ruta, datos }: { metodo: Metodo; ruta: string; datos?: unknown }) =>
      metodo === 'POST'
        ? enviar<RespuestaCambio>(`${A}${ruta}`, datos ?? {})
        : pedir<RespuestaCambio>(`${A}${ruta}`, {
            method: metodo,
            body: datos === undefined ? undefined : JSON.stringify(datos),
          }),
    onSuccess: () =>
      Promise.all([
        cliente.invalidateQueries({ queryKey: ['personas'] }),
        cliente.invalidateQueries({ queryKey: ['usuarios'] }),
        cliente.invalidateQueries({ queryKey: ['estructura'] }),
      ]),
  })
}

export const PARENTESCOS = ['Padre', 'Madre', 'Tutor legal', 'Abuelo(a)', 'Tío(a)', 'Hermano(a)', 'Otro']

/** "2026-06-10" → "10/06/2026" */
export const fechaCorta = (iso: string | null) => (iso ? iso.split('-').reverse().join('/') : '—')
