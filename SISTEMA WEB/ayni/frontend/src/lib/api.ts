import { EVENTO_EXPIRADA, token } from './sesion'

// Cliente de la API. La ruta es relativa a la base de la app, así funciona
// igual en local (/api) y en el hosting (/grupo02/api).
export const URL_API = `${import.meta.env.BASE_URL}api/v1`

export type ErroresCampo = Record<string, string[]>

export class ErrorApi extends Error {
  readonly estado: number
  readonly errores: ErroresCampo
  /** Cuerpo completo de la respuesta (intentos restantes, código, etc.) */
  readonly datos: Record<string, unknown>

  constructor(mensaje: string, estado: number, errores: ErroresCampo = {}, datos: Record<string, unknown> = {}) {
    super(mensaje)
    this.name = 'ErrorApi'
    this.estado = estado
    this.errores = errores
    this.datos = datos
  }
}

const SIN_CONEXION = 'No se pudo conectar con el servidor. Revisa tu conexión.'

/** Hace la petición con el token y convierte cualquier error en ErrorApi */
async function solicitar(ruta: string, opciones: RequestInit): Promise<Response> {
  const actual = token.leer()
  let respuesta: Response

  try {
    respuesta = await fetch(`${URL_API}${ruta}`, {
      ...opciones,
      headers: {
        Accept: 'application/json',
        // Con FormData (archivos) el navegador pone su propio Content-Type
        ...(typeof opciones.body === 'string' ? { 'Content-Type': 'application/json' } : {}),
        ...(actual ? { Authorization: `Bearer ${actual}` } : {}),
        ...opciones.headers,
      },
    })
  } catch {
    throw new ErrorApi(SIN_CONEXION, 0)
  }

  if (actual && respuesta.status !== 401) token.tocar()
  if (respuesta.ok) return respuesta

  const cuerpo = await respuesta.json().catch(() => null)
  // El token venció o se revocó: se avisa a la app para que pida ingresar de nuevo
  if (respuesta.status === 401 && actual) {
    token.borrar()
    // La respuesta puede llegar cuando la página ya no existe (por ejemplo, al terminar una prueba)
    if (typeof window !== 'undefined') window.dispatchEvent(new CustomEvent(EVENTO_EXPIRADA))
  }
  throw new ErrorApi(
    cuerpo?.mensaje ?? `Error ${respuesta.status} del servidor.`,
    respuesta.status,
    cuerpo?.errores ?? {},
    cuerpo ?? {},
  )
}

export async function pedir<T>(ruta: string, opciones: RequestInit = {}): Promise<T> {
  const respuesta = await solicitar(ruta, opciones)
  return (await respuesta.json().catch(() => null)) as T
}

/** Atajo para enviar JSON por POST */
export const enviar = <T>(ruta: string, datos: unknown = {}) =>
  pedir<T>(ruta, { method: 'POST', body: JSON.stringify(datos) })

/** Envía un formulario con archivos (multipart) */
export const subir = <T>(ruta: string, datos: FormData) => pedir<T>(ruta, { method: 'POST', body: datos })

/** Pide un archivo a la API y lo guarda en la PC con el nombre indicado */
export async function descargar(ruta: string, nombre: string, opciones: RequestInit = {}) {
  const archivo = await (await solicitar(ruta, opciones)).blob()
  guardarArchivo(archivo, nombre)
}

/** Ofrece al navegador un archivo generado (por ejemplo, un .csv) */
export function guardarArchivo(archivo: Blob, nombre: string) {
  const url = URL.createObjectURL(archivo)
  const enlace = document.createElement('a')
  enlace.href = url
  enlace.download = nombre
  document.body.append(enlace)
  enlace.click()
  enlace.remove()
  URL.revokeObjectURL(url)
}
