import { createContext, useContext } from 'react'
import type { Usuario } from './sesion'

export type Sesion = {
  /** true mientras se confirma con el servidor el token guardado */
  verificando: boolean
  usuario: Usuario | null
  iniciar: (token: string, usuario: Usuario) => void
  actualizar: (usuario: Usuario) => void
  salir: () => Promise<void>
}

export const ContextoSesion = createContext<Sesion | null>(null)

export function useSesion() {
  const sesion = useContext(ContextoSesion)
  if (!sesion) throw new Error('useSesion debe usarse dentro de ProveedorSesion')
  return sesion
}
