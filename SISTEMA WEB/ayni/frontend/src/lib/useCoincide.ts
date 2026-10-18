import { useSyncExternalStore } from 'react'

/** true mientras la pantalla cumpla la media query (por ejemplo, "(min-width: 1280px)") */
export function useCoincide(consulta: string) {
  return useSyncExternalStore(
    (avisar) => {
      const lista = window.matchMedia?.(consulta)
      lista?.addEventListener('change', avisar)
      return () => lista?.removeEventListener('change', avisar)
    },
    () => window.matchMedia?.(consulta).matches ?? false,
    () => false,
  )
}
