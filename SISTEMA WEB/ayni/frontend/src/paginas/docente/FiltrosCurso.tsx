import { Selector } from '@/components/ui/selector'
import type { Bimestre, MiCurso } from '@/lib/asistencia'

type Props = {
  cursos: MiCurso[]
  curso: MiCurso
  bimestres: Bimestre[]
  bimestre: Bimestre | null
  onCurso: (id: string) => void
  onBimestre: (id: string) => void
}

export function FiltrosCurso({ cursos, curso, bimestres, bimestre, onCurso, onBimestre }: Props) {
  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:max-w-2xl">
      <Selector
        etiqueta="Curso"
        value={String(curso.id)}
        onChange={(e) => onCurso(e.target.value)}
        opciones={cursos.map((c) => ({ valor: String(c.id), texto: c.nombre }))}
      />
      <Selector
        etiqueta="Bimestre"
        value={String(bimestre?.id ?? '')}
        onChange={(e) => onBimestre(e.target.value)}
        opciones={bimestres.map((b) => ({ valor: String(b.id), texto: b.nombre }))}
      />
    </div>
  )
}
