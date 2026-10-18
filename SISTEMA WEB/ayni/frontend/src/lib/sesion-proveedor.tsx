import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { enviar, pedir } from './api'
import { EVENTO_EXPIRADA, token, type Usuario } from './sesion'
import { ContextoSesion, type Sesion } from './sesion-contexto'

const EXPIRADA = 'Tu sesión se cerró por inactividad. Vuelve a ingresar.'

export function ProveedorSesion({ children }: { children: ReactNode }) {
  const avisos = useAvisos()
  const [usuario, setUsuario] = useState<Usuario | null>(null)
  const [verificando, setVerificando] = useState(() => {
    if (token.leer() && token.vencido()) token.borrar()
    return token.leer() !== null
  })
  const conSesion = useRef(false)
  useEffect(() => {
    conSesion.current = usuario !== null
  }, [usuario])

  // Al abrir la app se confirma el token guardado
  useEffect(() => {
    if (!token.leer()) return
    pedir<{ usuario: Usuario }>('/auth/yo')
      .then((r) => setUsuario(r.usuario))
      .catch(() => setUsuario(null))
      .finally(() => setVerificando(false))
  }, [])

  // El servidor rechazó el token o pasó una hora sin actividad
  useEffect(() => {
    const expirar = () => {
      if (conSesion.current) avisos.aviso(EXPIRADA)
      setUsuario(null)
    }
    const revisar = () => {
      if (conSesion.current && token.vencido()) {
        token.borrar()
        expirar()
      }
    }
    window.addEventListener(EVENTO_EXPIRADA, expirar)
    const reloj = window.setInterval(revisar, 30_000)
    return () => {
      window.removeEventListener(EVENTO_EXPIRADA, expirar)
      window.clearInterval(reloj)
    }
  }, [avisos])

  const iniciar = useCallback((nuevo: string, datos: Usuario) => {
    token.guardar(nuevo)
    setUsuario(datos)
  }, [])

  const salir = useCallback(async () => {
    // Si el token ya venció, no se muestra el aviso de inactividad
    conSesion.current = false
    await enviar('/auth/salir').catch(() => null)
    token.borrar()
    setUsuario(null)
  }, [])

  const valor = useMemo<Sesion>(
    () => ({ verificando, usuario, iniciar, actualizar: setUsuario, salir }),
    [verificando, usuario, iniciar, salir],
  )

  return <ContextoSesion.Provider value={valor}>{children}</ContextoSesion.Provider>
}
