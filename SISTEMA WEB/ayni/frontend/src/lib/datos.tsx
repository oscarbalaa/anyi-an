import { QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { clienteDatos } from './cliente-datos'

// Solo lo usan las pantallas que consultan la API; así la landing no descarga esta librería
export function ProveedorDatos({ children }: { children: ReactNode }) {
  return <QueryClientProvider client={clienteDatos}>{children}</QueryClientProvider>
}
