import { useEffect, useRef, useState } from 'react'

/** Marca el elemento como visible cuando entra en pantalla (clase .revelar) */
export function useRevelar<T extends HTMLElement>() {
  const ref = useRef<T>(null)
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    const el = ref.current
    const sinMovimiento = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches
    if (!el || !('IntersectionObserver' in window) || sinMovimiento) {
      setVisible(true)
      return
    }
    const observador = new IntersectionObserver(
      ([entrada]) => {
        if (entrada.isIntersecting) {
          setVisible(true)
          observador.disconnect()
        }
      },
      { threshold: 0.15 },
    )
    observador.observe(el)
    return () => observador.disconnect()
  }, [])

  return { ref, 'data-visible': visible }
}
