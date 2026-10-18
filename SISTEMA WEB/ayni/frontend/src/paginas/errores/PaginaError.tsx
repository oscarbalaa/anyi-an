import { Link } from 'react-router'
import { Ilustracion } from '@/components/Ilustracion'
import { Button } from '@/components/ui/button'
import { IMG } from '@/lib/imagenes'

const TEXTOS = {
  404: { titulo: 'Esta página no existe', detalle: 'Revisa la dirección o vuelve al inicio.' },
  403: {
    titulo: 'No tienes permiso para ver esta página',
    detalle: 'Esta sección es de otro tipo de usuario. Si crees que es un error, comunícate con la administración.',
  },
}

export default function PaginaError({ codigo }: { codigo: 404 | 403 }) {
  const t = TEXTOS[codigo]
  return (
    <main
      data-tema="estudiante"
      className="flex min-h-screen flex-col items-center justify-center gap-4 bg-fondo px-4 text-center text-texto"
    >
      <Ilustracion
        imagen={codigo === 404 ? IMG.piki : IMG.tambo}
        sizes="176px"
        alt=""
        loading="eager"
        className="h-auto w-44"
      />
      <p className="text-6xl font-black text-primario">{codigo}</p>
      <h1 className="text-2xl font-black sm:text-3xl">{t.titulo}</h1>
      <p className="max-w-md text-texto-suave">{t.detalle}</p>
      <Button asChild>
        <Link to="/">Volver al inicio</Link>
      </Button>
    </main>
  )
}
