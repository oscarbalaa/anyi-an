import { useCallback, useEffect, useState } from 'react'
import { enviar } from './api'

export type EstadoCaptcha = {
  id: string | null
  imagen: string | null
  cargando: boolean
  error: string | null
  renovar: () => void
}

/** Pide una imagen nueva; cada captcha sirve una sola vez */
export function useCaptcha(): EstadoCaptcha {
  const [datos, setDatos] = useState<{ id: string; imagen: string } | null>(null)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [vuelta, setVuelta] = useState(0)

  useEffect(() => {
    let vigente = true
    enviar<{ id: string; imagen: string }>('/captcha')
      .then((r) => {
        if (!vigente) return
        setDatos(r)
        setError(null)
      })
      .catch((e: Error) => vigente && setError(e.message))
      .finally(() => vigente && setCargando(false))
    return () => {
      vigente = false
    }
  }, [vuelta])

  const renovar = useCallback(() => {
    setCargando(true)
    setVuelta((v) => v + 1)
  }, [])

  return { id: datos?.id ?? null, imagen: datos?.imagen ?? null, cargando, error, renovar }
}
