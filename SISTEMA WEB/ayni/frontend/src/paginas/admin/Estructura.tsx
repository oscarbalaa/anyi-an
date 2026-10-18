import { BookOpen, CalendarClock, CalendarDays, LayoutGrid, School } from 'lucide-react'
import { useSearchParams } from 'react-router'
import { Pestanas } from '@/components/ui/pestanas'
import { PestanaAsignaturas } from './estructura/Asignaturas'
import { PestanaAulas } from './estructura/Aulas'
import { PestanaCarga } from './estructura/Carga'
import { PestanaColegio } from './estructura/Colegio'
import { PestanaPeriodos } from './estructura/Periodos'

const PESTANAS = [
  { id: 'periodos', texto: 'Periodos', icono: CalendarDays },
  { id: 'aulas', texto: 'Grados y aulas', icono: LayoutGrid },
  { id: 'asignaturas', texto: 'Asignaturas', icono: BookOpen },
  { id: 'carga', texto: 'Carga y horarios', icono: CalendarClock },
  { id: 'colegio', texto: 'Colegio', icono: School },
]

// CU-06, CU-07 y CU-08 (mockups admin 05, 06, 07 y 12)
export default function Estructura() {
  const [parametros, setParametros] = useSearchParams()
  const pedida = parametros.get('pestana')
  const activa = PESTANAS.some((p) => p.id === pedida) ? (pedida as string) : 'periodos'

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <header>
        <h1 className="text-3xl font-black sm:text-4xl">Estructura académica</h1>
        <p className="mt-1 text-lg text-texto-suave">Periodos, aulas, asignaturas y carga docente.</p>
      </header>

      <Pestanas
        etiqueta="Secciones de la estructura académica"
        pestanas={PESTANAS}
        activa={activa}
        onCambiar={(id) => setParametros({ pestana: id }, { replace: true })}
      >
        {activa === 'periodos' && <PestanaPeriodos />}
        {activa === 'aulas' && <PestanaAulas />}
        {activa === 'asignaturas' && <PestanaAsignaturas />}
        {activa === 'carga' && (
          <PestanaCarga
            periodoInicial={Number(parametros.get('periodo')) || null}
            aulaInicial={Number(parametros.get('aula')) || null}
          />
        )}
        {activa === 'colegio' && <PestanaColegio />}
      </Pestanas>
    </div>
  )
}
