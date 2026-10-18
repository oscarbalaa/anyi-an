import type { Rol } from '@/layout/roles'

export type Usuario = {
  id: number
  usuario: string
  rol: 'ESTUDIANTE' | 'DOCENTE' | 'ADMIN'
  acceso: Rol
  nombre: string
  nombre_completo: string
  iniciales: string
  detalle: string | null
  correo: string | null
  debe_cambiar_contrasena: boolean
  ultimo_acceso: string | null
}

const CLAVE_TOKEN = 'ayni.token'
const CLAVE_ACTIVIDAD = 'ayni.actividad'
export const EVENTO_EXPIRADA = 'ayni:sesion-expirada'
/** Igual que en el servidor (config/ayni.php) */
export const INACTIVIDAD_MS = 60 * 60 * 1000

// localStorage puede fallar (modo privado, almacenamiento bloqueado)
function leer(clave: string): string | null {
  try {
    return localStorage.getItem(clave)
  } catch {
    return null
  }
}

function escribir(clave: string, valor: string | null) {
  try {
    if (valor === null) localStorage.removeItem(clave)
    else localStorage.setItem(clave, valor)
  } catch {
    /* sin almacenamiento: la sesión dura lo que la pestaña */
  }
}

export const token = {
  leer: () => leer(CLAVE_TOKEN),
  guardar: (valor: string) => {
    escribir(CLAVE_TOKEN, valor)
    token.tocar()
  },
  borrar: () => {
    escribir(CLAVE_TOKEN, null)
    escribir(CLAVE_ACTIVIDAD, null)
  },
  /** Marca la última petición con sesión; el servidor cuenta la inactividad igual */
  tocar: () => escribir(CLAVE_ACTIVIDAD, String(Date.now())),
  vencido: (ahora = Date.now()) => ahora - Number(leer(CLAVE_ACTIVIDAD) ?? 0) > INACTIVIDAD_MS,
}

/** Usuario recordado en la pantalla de ingreso ("Recordarme") */
export const recordado = {
  leer: (rol: Rol) => leer(`ayni.recordar.${rol}`) ?? '',
  guardar: (rol: Rol, usuario: string | null) => escribir(`ayni.recordar.${rol}`, usuario),
}
