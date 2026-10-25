import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { enviar, pedir } from './api'

export type RolApi = 'ADMIN' | 'DOCENTE' | 'ESTUDIANTE'
export type EstadoCuenta = 'ACTIVO' | 'INACTIVO' | 'BLOQUEADO'

export type FilaUsuario = {
  id: number
  usuario: string
  nombre: string
  rol: RolApi
  rol_nombre: string
  detalle: string | null
  estado: EstadoCuenta
  correo: string | null
  debe_cambiar_contrasena: boolean
  ultimo_acceso: string | null
}

export type DetalleUsuario = FilaUsuario & {
  documento: string | null
  celular: string | null
  nombres: string | null
  apellidos: string | null
  cargo: string | null
  intentos_fallidos: number
  bloqueado_hasta: string | null
  creado_en: string | null
  creado_por: string | null
  actualizado_en: string | null
  actualizado_por: string | null
  historial: { accion: string; fecha: string; por: string }[]
}

export type ListaUsuarios = {
  datos: FilaUsuario[]
  pagina: number
  por_pagina: number
  total: number
  ultima_pagina: number
  resumen: Partial<Record<EstadoCuenta, number>>
}

export type FiltrosUsuarios = { buscar: string; rol: string; estado: string; pagina: number }

export type ResultadoTemporal = {
  mensaje: string
  contrasena: string
  correo_enviado: boolean
  usuario: DetalleUsuario
}

const BASE = '/admin/usuarios'

export function useUsuarios(filtros: FiltrosUsuarios) {
  const parametros = new URLSearchParams()
  for (const [clave, valor] of Object.entries(filtros)) {
    if (valor !== '' && !(clave === 'pagina' && valor === 1)) parametros.set(clave, String(valor))
  }
  const consulta = parametros.toString()
  return useQuery({
    queryKey: ['usuarios', filtros],
    queryFn: () => pedir<ListaUsuarios>(`${BASE}${consulta ? `?${consulta}` : ''}`),
    // Al cambiar de página o filtro se mantiene la tabla anterior mientras carga
    placeholderData: keepPreviousData,
  })
}

export function useUsuario(id: number | null) {
  return useQuery({
    queryKey: ['usuario', id],
    queryFn: () => pedir<{ usuario: DetalleUsuario }>(`${BASE}/${id}`).then((r) => r.usuario),
    enabled: id !== null,
  })
}

/** Tras cualquier cambio se recargan la lista y el detalle */
function useRefrescar() {
  const cliente = useQueryClient()
  return (usuario?: DetalleUsuario) => {
    if (usuario) cliente.setQueryData(['usuario', usuario.id], usuario)
    return cliente.invalidateQueries({ queryKey: ['usuarios'] })
  }
}

export function useGuardarUsuario() {
  const refrescar = useRefrescar()
  return useMutation({
    mutationFn: ({ id, datos }: { id: number | null; datos: Record<string, string> }) =>
      id === null
        ? enviar<ResultadoTemporal>(BASE, datos)
        : pedir<{ mensaje: string; usuario: DetalleUsuario }>(`${BASE}/${id}`, {
            method: 'PUT',
            body: JSON.stringify(datos),
          }),
    onSuccess: (r) => refrescar(r.usuario),
  })
}

export function useCambiarEstado() {
  const refrescar = useRefrescar()
  return useMutation({
    mutationFn: ({ id, estado }: { id: number; estado: 'ACTIVO' | 'INACTIVO' }) =>
      pedir<{ mensaje: string; usuario: DetalleUsuario }>(`${BASE}/${id}/estado`, {
        method: 'PATCH',
        body: JSON.stringify({ estado }),
      }),
    onSuccess: (r) => refrescar(r.usuario),
  })
}

export function useContrasenaTemporal() {
  const refrescar = useRefrescar()
  return useMutation({
    mutationFn: (id: number) => enviar<ResultadoTemporal>(`${BASE}/${id}/contrasena-temporal`),
    onSuccess: (r) => refrescar(r.usuario),
  })
}

export type Perfil = {
  id: number
  usuario: string
  rol: RolApi
  nombre: string
  nombre_completo: string
  iniciales: string
  detalle: string | null
  correo: string | null
  celular: string | null
  documento: string | null
  estado: EstadoCuenta
  ultimo_acceso: string | null
  cargo?: string | null
  ambito?: string
  especialidad?: string | null
  cursos?: string[]
  coordinacion?: string | null
  tutoria?: string | null
  codigo?: string
  aula?: string | null
  tutor?: string | null
  coordinador?: string | null
}

export function usePerfil() {
  return useQuery({ queryKey: ['perfil'], queryFn: () => pedir<{ perfil: Perfil }>('/perfil').then((r) => r.perfil) })
}

export function useGuardarPerfil() {
  const cliente = useQueryClient()
  return useMutation({
    mutationFn: (datos: { correo: string; celular: string }) =>
      pedir<{ mensaje: string; perfil: Perfil }>('/perfil', { method: 'PUT', body: JSON.stringify(datos) }),
    onSuccess: (r) => cliente.setQueryData(['perfil'], r.perfil),
  })
}

const FORMATO_FECHA = new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium', timeStyle: 'short' })

/** "17 oct 2026, 7:30 a. m." o "Nunca" */
export const fechaHora = (iso: string | null) => (iso ? FORMATO_FECHA.format(new Date(iso)) : 'Nunca')
