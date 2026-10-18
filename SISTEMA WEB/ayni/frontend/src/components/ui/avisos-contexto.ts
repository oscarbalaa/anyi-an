import { createContext, useContext, useMemo } from 'react'

export type TipoAviso = 'exito' | 'error' | 'info' | 'aviso'

export const ContextoAvisos = createContext<((tipo: TipoAviso, mensaje: string) => void) | null>(null)

export function useAvisos() {
  const mostrar = useContext(ContextoAvisos)
  if (!mostrar) throw new Error('useAvisos debe usarse dentro de ProveedorAvisos')
  return useMemo(
    () => ({
      exito: (m: string) => mostrar('exito', m),
      error: (m: string) => mostrar('error', m),
      aviso: (m: string) => mostrar('aviso', m),
      info: (m: string) => mostrar('info', m),
    }),
    [mostrar],
  )
}
