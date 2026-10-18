import { ArrowLeft } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link } from 'react-router'
import logoAdmin from '@/assets/logo-admin.svg'
import logoAyni from '@/assets/logo-ayni.svg'
import logoDocente from '@/assets/logo-docente.svg'
import { Ilustracion } from '@/components/Ilustracion'
import { IMG, type Imagen } from '@/lib/imagenes'
import type { Rol } from '@/layout/roles'

const PANEL: Record<
  Rol,
  { logo: string; lema: string; fondo: string; mascota?: { imagen: Imagen; alt: string }; borde: Imagen }
> = {
  estudiante: {
    logo: logoAyni,
    lema: 'Tu camino continúa aquí',
    fondo: 'bg-[#fbeee0]',
    mascota: { imagen: IMG.piki, alt: 'Piki, la vizcacha, te da la bienvenida' },
    borde: IMG.bordeEstudiante,
  },
  docente: {
    logo: logoDocente,
    lema: 'Acompañar también empieza aquí',
    fondo: 'bg-[#fdf7f0]',
    mascota: { imagen: IMG.tambo, alt: 'Tambo, el oso de anteojos, te saluda' },
    borde: IMG.bordeDocente,
  },
  admin: {
    logo: logoAdmin,
    lema: 'Información clara para decidir mejor',
    fondo: 'bg-marfil',
    borde: IMG.bordeAdmin,
  },
}

type Props = {
  rol: Rol
  titulo: string
  subtitulo?: ReactNode
  children: ReactNode
  /** Enlace inferior; por defecto vuelve al inicio */
  volver?: { a: string; texto: string } | null
}

/** Pantalla dividida de las páginas de acceso: panel ilustrado + formulario */
export function PantallaAcceso({
  rol,
  titulo,
  subtitulo,
  children,
  volver = { a: '/', texto: 'Volver al inicio' },
}: Props) {
  const c = PANEL[rol]
  return (
    <div data-tema={rol} className="grid min-h-screen bg-fondo text-texto lg:grid-cols-2">
      <aside className={`relative flex flex-col overflow-hidden px-6 pt-6 pb-24 lg:px-12 lg:pt-12 ${c.fondo}`}>
        <Link to="/" aria-label="Ir al inicio de Ayni" className="self-start">
          <img src={c.logo} alt="Ayni" width={180} height={86} className="h-12 w-auto lg:h-20" />
        </Link>
        <p className="mt-4 text-2xl font-black lg:mt-8 lg:text-center lg:text-4xl">{c.lema}</p>
        {c.mascota && (
          <Ilustracion
            imagen={c.mascota.imagen}
            sizes="(min-width: 1024px) 360px, 0px"
            alt={c.mascota.alt}
            loading="eager"
            className="flotar mx-auto mt-6 hidden h-auto w-80 lg:block"
          />
        )}
        <Ilustracion
          imagen={c.borde}
          sizes="(min-width: 1024px) 50vw, 100vw"
          alt=""
          className={
            rol === 'admin'
              ? 'absolute bottom-0 left-0 h-auto w-full lg:w-[95%]'
              : 'absolute inset-x-0 bottom-0 h-auto w-full'
          }
        />
      </aside>

      <main className="flex items-center justify-center px-4 py-10 sm:px-8">
        <div className="w-full max-w-md">
          <h1 className="text-center text-3xl font-black sm:text-4xl">{titulo}</h1>
          {subtitulo && <p className="mt-2 text-center text-lg text-texto-suave">{subtitulo}</p>}
          <div className="mt-8">{children}</div>
          {volver && (
            <Link
              to={volver.a}
              className="mx-auto mt-6 flex w-fit items-center gap-2 font-bold text-acento-texto hover:underline"
            >
              <ArrowLeft className="size-5" aria-hidden /> {volver.texto}
            </Link>
          )}
        </div>
      </main>
    </div>
  )
}

/** Mensaje de error o aviso general del formulario (no de un campo) */
export function AvisoFormulario({
  tono = 'error',
  children,
}: {
  tono?: 'error' | 'exito' | 'aviso'
  children: ReactNode
}) {
  const estilo = {
    error: 'border-peligro/40 bg-peligro/8',
    exito: 'border-exito/40 bg-exito/8',
    aviso: 'border-aviso/40 bg-aviso/10',
  }[tono]
  return (
    <div role={tono === 'error' ? 'alert' : 'status'} className={`rounded-xl border p-4 ${estilo}`}>
      {children}
    </div>
  )
}
