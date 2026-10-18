import { useSearchParams } from 'react-router'
import { useMisCursos } from '@/lib/asistencia'

/** Curso y bimestre elegidos, guardados en la dirección (?curso=…&bimestre=…) */
export function useCursoElegido() {
  const [parametros, setParametros] = useSearchParams()
  const cursos = useMisCursos()
  const lista = cursos.data?.cursos ?? []
  const curso = lista.find((c) => c.id === Number(parametros.get('curso'))) ?? lista[0] ?? null
  const bimestre = Number(parametros.get('bimestre')) || null

  const cambiar = (cambios: Record<string, string | null>) => {
    const p = new URLSearchParams(parametros)
    for (const [k, v] of Object.entries(cambios)) {
      if (v) p.set(k, v)
      else p.delete(k)
    }
    setParametros(p, { replace: true })
  }

  return { cursos, lista, curso, bimestre, cambiar, parametros }
}
