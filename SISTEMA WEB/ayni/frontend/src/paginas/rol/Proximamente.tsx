import { Hammer } from 'lucide-react'
import { Link, useLocation } from 'react-router'
import { Ilustracion } from '@/components/Ilustracion'
import { Button } from '@/components/ui/button'
import { IMG } from '@/lib/imagenes'
import { EXTRAS, ROLES, type Rol } from '@/layout/roles'

export default function Proximamente({ rol }: { rol: Rol }) {
  const { pathname } = useLocation()
  const config = ROLES[rol]
  const ruta = pathname.replace(`${config.base}/`, '')
  const item = [...config.menu, ...EXTRAS].find((i) => i.ruta === ruta)
  const mascota = rol === 'docente' ? IMG.tambo : rol === 'estudiante' ? IMG.piki : null

  return (
    <div className="mx-auto flex max-w-xl flex-col items-center gap-4 py-10 text-center">
      {mascota ? (
        <Ilustracion imagen={mascota} sizes="160px" alt="" className="h-auto w-40" />
      ) : (
        <span
          className="grid size-20 place-items-center rounded-full bg-primario-suave text-primario-oscuro"
          aria-hidden
        >
          <Hammer className="size-10" />
        </span>
      )}
      <h1 className="text-3xl font-black">{item?.texto ?? 'Pantalla en construcción'}</h1>
      <p className="text-lg text-texto-suave">
        Esta pantalla se construye en el{' '}
        <strong className="text-texto">{item?.modulo ? `Módulo ${item.modulo.slice(1)}` : 'próximo módulo'}</strong> del
        plan.
      </p>
      <Button asChild variante="secundario">
        <Link to={config.base}>Volver al inicio</Link>
      </Button>
    </div>
  )
}
