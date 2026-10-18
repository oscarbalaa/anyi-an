import { ClipboardList, FileUp, Users } from 'lucide-react'
import { useSearchParams } from 'react-router'
import { Pestanas } from '@/components/ui/pestanas'
import { PestanaImportar } from './estudiantes/Importar'
import { PestanaLista } from './estudiantes/Lista'
import { PestanaMatriculas } from './estudiantes/Matriculas'

const PESTANAS = [
  { id: 'lista', texto: 'Lista', icono: Users },
  { id: 'importar', texto: 'Importar Excel', icono: FileUp },
  { id: 'matriculas', texto: 'Matrículas', icono: ClipboardList },
]

// CU-10, CU-11 y CU-12 (mockups admin 09, 10 y 11)
export default function Estudiantes() {
  const [parametros, setParametros] = useSearchParams()
  const pedida = parametros.get('pestana')
  const activa = PESTANAS.some((p) => p.id === pedida) ? (pedida as string) : 'lista'

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <header>
        <h1 className="text-3xl font-black sm:text-4xl">Estudiantes</h1>
        <p className="mt-1 text-lg text-texto-suave">Registro, importación desde Excel y matrículas.</p>
      </header>

      <Pestanas
        etiqueta="Secciones de estudiantes"
        pestanas={PESTANAS}
        activa={activa}
        onCambiar={(id) => setParametros({ pestana: id }, { replace: true })}
      >
        {activa === 'lista' && <PestanaLista />}
        {activa === 'importar' && <PestanaImportar />}
        {activa === 'matriculas' && <PestanaMatriculas />}
      </Pestanas>
    </div>
  )
}
