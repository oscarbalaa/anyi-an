import { ArrowRight, Lock } from 'lucide-react'
import { Link } from 'react-router'
import logoAyni from '@/assets/logo-ayni.svg'
import { Ilustracion } from '@/components/Ilustracion'
import { Button } from '@/components/ui/button'
import { IMG, type Imagen } from '@/lib/imagenes'

export default function SelectorIngreso() {
  return (
    <div data-tema="estudiante" className="relative flex min-h-screen flex-col overflow-hidden bg-[#fdf9f3] text-texto">
      <header className="flex items-center justify-between px-4 pt-5 sm:px-8">
        <Link to="/" aria-label="Volver al inicio">
          <img src={logoAyni} alt="Ayni" width={100} height={48} className="h-10 w-auto" />
        </Link>
        <Link to="/ingresar/administracion" className="flex items-center gap-2 font-semibold hover:text-accion">
          <Lock className="size-5" aria-hidden /> Administración
        </Link>
      </header>

      <main className="relative z-10 mx-auto flex w-full max-w-5xl flex-1 flex-col px-4 pt-6 pb-40 sm:px-6 sm:pb-52">
        <h1 className="text-center text-4xl font-black sm:text-5xl">¿Cómo deseas ingresar?</h1>
        <div className="mt-10 grid gap-6 md:grid-cols-2">
          <Tarjeta
            tema="estudiante"
            fondo="bg-[#fbeee3]"
            imagen={IMG.piki}
            alt="Piki, la vizcacha"
            titulo="estudiante"
            texto="Consulta tus actividades, notas y horario."
            ruta="/ingresar/estudiante"
            boton="Ingresar como estudiante"
          />
          <Tarjeta
            tema="docente"
            fondo="bg-cielo/80"
            imagen={IMG.tambo}
            alt="Tambo, el oso de anteojos"
            titulo="docente"
            texto="Registra avances y acompaña a tus estudiantes."
            ruta="/ingresar/docente"
            boton="Ingresar como docente"
            invertida
          />
        </div>
      </main>

      <Ilustracion
        imagen={IMG.cesped}
        sizes="100vw"
        className="pointer-events-none absolute inset-x-0 bottom-0 h-auto w-full"
        alt=""
      />
    </div>
  )
}

type PropsTarjeta = {
  tema: 'estudiante' | 'docente'
  fondo: string
  imagen: Imagen
  alt: string
  titulo: string
  texto: string
  ruta: string
  boton: string
  invertida?: boolean
}

function Tarjeta({ tema, fondo, imagen, alt, titulo, texto, ruta, boton, invertida }: PropsTarjeta) {
  return (
    <section
      data-tema={tema}
      className={`flex items-center gap-4 rounded-3xl p-6 sm:p-8 ${fondo} ${invertida ? 'flex-row-reverse' : ''}`}
    >
      <Ilustracion
        imagen={imagen}
        sizes="(min-width: 768px) 180px, 112px"
        alt={alt}
        loading="eager"
        className="flotar h-auto w-28 shrink-0 sm:w-44"
      />
      <div className="flex flex-col items-start gap-3">
        <h2 className="text-3xl leading-tight font-black sm:text-4xl">
          Soy <span className="block text-primario-oscuro">{titulo}</span>
        </h2>
        <p className="text-lg text-texto-suave">{texto}</p>
        <Button asChild className="rounded-full">
          <Link to={ruta}>
            {boton} <ArrowRight className="size-5" aria-hidden />
          </Link>
        </Button>
      </div>
    </section>
  )
}
