import { useSearchParams } from 'react-router'
import { useFechasClase, type RolAsistencia } from '@/lib/asistencia'

/** Bimestre y fecha de la clase, guardados en la dirección (?bimestre=…&fecha=…) */
export function useFechaElegida(rol: RolAsistencia, curso: number | null) {
  const [parametros, setParametros] = useSearchParams()
  const bimestrePedido = Number(parametros.get('bimestre')) || null
  const { data, isPending } = useFechasClase(rol, curso, bimestrePedido)
  const fechas = data?.fechas ?? []
  const pedida = parametros.get('fecha')

  // Por defecto: la clase de hoy o la más reciente que falta registrar
  const fecha =
    fechas.find((f) => f.fecha === pedida)?.fecha ??
    fechas.find((f) => f.hoy)?.fecha ??
    fechas.find((f) => f.estado !== 'DEFINITIVO')?.fecha ??
    fechas[0]?.fecha ??
    null

  const cambiar = (clave: string, valor: string) => {
    const p = new URLSearchParams(parametros)
    p.set(clave, valor)
    if (clave === 'bimestre') p.delete('fecha')
    setParametros(p, { replace: true })
  }

  return {
    cargando: curso !== null && isPending,
    bimestres: data?.bimestres ?? [],
    bimestre: data?.bimestre ?? null,
    fechas,
    fecha,
    elegirBimestre: (id: string) => cambiar('bimestre', id),
    elegirFecha: (f: string) => cambiar('fecha', f),
  }
}
