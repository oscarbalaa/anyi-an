import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import type { ErrorApi } from './api'

/**
 * Pasa a cada campo los errores 422 de la API.
 * Devuelve el mensaje general si ninguno corresponde a un campo del formulario (para mostrarlo en un aviso).
 */
export function marcarErrores<T extends FieldValues>(e: unknown, setError: UseFormSetError<T>, campos: string[]) {
  const error = e as ErrorApi
  const conCampo = campos.filter((c) => error.errores?.[c])
  conCampo.forEach((c) => setError(c as Path<T>, { message: error.errores[c][0] }))
  return conCampo.length > 0 ? null : error.message
}

/** "" → null y números en texto → número, para enviar a la API */
export const idONulo = (v: string) => (v === '' ? null : Number(v))

export type CampoPersona =
  | 'tipo_documento'
  | 'numero_documento'
  | 'nombres'
  | 'apellidos'
  | 'fecha_nacimiento'
  | 'sexo'
  | 'correo'
  | 'celular'
  | 'direccion'

/** Campos de persona en el formato que espera la API ("" → null) */
export function personaParaApi(d: Record<string, string>) {
  const nulo = (v: string) => (v === '' ? null : v)
  return {
    tipo_documento: d.tipo_documento,
    numero_documento: d.numero_documento,
    nombres: d.nombres,
    apellidos: d.apellidos,
    fecha_nacimiento: nulo(d.fecha_nacimiento),
    sexo: nulo(d.sexo),
    correo: nulo(d.correo),
    celular: nulo(d.celular),
    direccion: nulo(d.direccion),
  }
}

export const CAMPOS_PERSONA: CampoPersona[] = [
  'tipo_documento',
  'numero_documento',
  'nombres',
  'apellidos',
  'fecha_nacimiento',
  'sexo',
  'correo',
  'celular',
  'direccion',
]
