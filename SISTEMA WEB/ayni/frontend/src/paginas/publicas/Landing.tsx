import { BellRing, CalendarCheck, ChartColumn, GraduationCap, Lock, MapPin, School, Sprout, Users } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link } from 'react-router'
import logoAyni from '@/assets/logo-ayni.svg'
import { Ilustracion } from '@/components/Ilustracion'
import { Button } from '@/components/ui/button'
import { IMG } from '@/lib/imagenes'
import { useRevelar } from '@/lib/useRevelar'
import { cn } from '@/lib/utils'

const ANIO = new Date().getFullYear()

export default function Landing() {
  return (
    <div data-tema="estudiante" className="min-h-screen bg-superficie text-texto">
      <Encabezado />
      <main>
        <Portada />
        <QueEsAyni />
        <Descubre />
        <PikiYTambo />
        <Contacto />
      </main>
      <footer className="bg-tinta px-4 py-8 text-center text-sm text-white/85">
        <p>AYNI · Plataforma de gestión académica y seguimiento del estudiante</p>
        <p className="mt-1">Grupo 02 · Aplicaciones en la Nube · Universidad Nacional del Santa · {ANIO}</p>
      </footer>
    </div>
  )
}

function Encabezado() {
  return (
    <header className="sticky top-0 z-20 border-b border-borde bg-superficie/95 backdrop-blur">
      <div className="mx-auto flex h-18 max-w-6xl items-center gap-4 px-4 sm:px-6">
        <Link to="/" aria-label="Ayni, inicio">
          <img src={logoAyni} alt="Ayni" width={110} height={53} className="h-11 w-auto" />
        </Link>
        <nav aria-label="Secciones" className="ml-auto hidden gap-8 font-semibold md:flex">
          <a href="#descubre" className="hover:text-accion">
            Descubre Ayni
          </a>
          <a href="#piki-y-tambo" className="hover:text-accion">
            Piki y Tambo
          </a>
          <a href="#contacto" className="hover:text-accion">
            Contacto
          </a>
        </nav>
        <Link
          to="/ingresar/administracion"
          className="ml-auto flex items-center gap-2 font-semibold hover:text-accion md:ml-8"
        >
          <Lock className="size-5" aria-hidden />
          <span className="hidden sm:inline">Administración</span>
          <span className="sr-only sm:hidden">Acceso administrativo</span>
        </Link>
      </div>
    </header>
  )
}

function Portada() {
  const palabras = ['Señales', 'claras']
  return (
    <section className="relative overflow-hidden bg-gradient-to-b from-[#fdf3e4] to-superficie pt-10 sm:pt-14">
      <div className="relative z-10 mx-auto flex max-w-4xl flex-col items-center px-4 text-center">
        <h1 className="text-5xl leading-[1.05] font-black tracking-tight sm:text-7xl">
          <span className="block text-primario">
            {palabras.map((p, i) => (
              <span key={p} className="palabra-animada" style={{ animationDelay: `${i * 0.15}s` }}>
                {p}
                {i < palabras.length - 1 && ' '}
              </span>
            ))}
          </span>
          <span className="block text-acento">
            <span className="palabra-animada" style={{ animationDelay: '0.35s' }}>
              para acompañar mejor
            </span>
          </span>
        </h1>
        <p className="mt-5 max-w-xl text-lg font-semibold text-texto-suave sm:text-xl">
          Información clara para acompañar cada trayectoria educativa.
        </p>
        <Button asChild tamano="grande" className="mt-7 w-full max-w-xs rounded-full">
          <Link to="/ingresar">Ingresar</Link>
        </Button>
      </div>
      <Ilustracion
        imagen={IMG.colinas}
        sizes="100vw"
        loading="eager"
        fetchPriority="high"
        alt="Paisaje de colinas, chacras y casitas andinas"
        className="relative -mt-6 h-auto w-full sm:-mt-16"
      />
    </section>
  )
}

function Seccion({
  id,
  titulo,
  children,
  className,
}: {
  id: string
  titulo: string
  children: ReactNode
  className?: string
}) {
  const revelar = useRevelar<HTMLDivElement>()
  return (
    <section id={id} aria-labelledby={`${id}-titulo`} className={cn('scroll-mt-20 px-4 py-16 sm:py-20', className)}>
      <div {...revelar} className="revelar mx-auto max-w-6xl">
        <h2
          id={`${id}-titulo`}
          className="flex items-center justify-center gap-3 text-center text-3xl font-black text-acento sm:text-4xl"
        >
          <Sprout className="size-9 text-primario" aria-hidden />
          {titulo}
        </h2>
        {children}
      </div>
    </section>
  )
}

function QueEsAyni() {
  const ideas = [
    {
      icono: <CalendarCheck />,
      titulo: 'Todo en un solo lugar',
      texto: 'Asistencia, notas, actividades e incidencias de cada estudiante, sin hojas de cálculo sueltas.',
    },
    {
      icono: <BellRing />,
      titulo: 'Alertas a tiempo',
      texto: 'Cuando las faltas aumentan o las notas bajan, el tutor recibe una alerta con los motivos.',
    },
    {
      icono: <Users />,
      titulo: 'Acompañar juntos',
      texto: 'Docentes, coordinación y administración ven la misma información para decidir mejor.',
    },
  ]
  return (
    <Seccion id="que-es" titulo="¿Qué es Ayni?">
      <p className="mx-auto mt-6 max-w-3xl text-center text-lg text-texto-suave">
        <strong className="text-texto">Ayni</strong> es una palabra quechua que significa <em>reciprocidad</em>: hoy por
        ti, mañana por mí. Así entendemos la escuela: una comunidad que se acompaña. AYNI reúne la información académica
        del colegio para detectar a tiempo a quien necesita apoyo.
      </p>
      <ul className="mt-10 grid gap-5 md:grid-cols-3">
        {ideas.map((i) => (
          <li key={i.titulo} className="rounded-2xl border border-borde bg-fondo p-6">
            <span
              className="grid size-12 place-items-center rounded-full bg-primario-suave text-primario-oscuro [&_svg]:size-6"
              aria-hidden
            >
              {i.icono}
            </span>
            <h3 className="mt-4 text-xl font-extrabold">{i.titulo}</h3>
            <p className="mt-2 text-texto-suave">{i.texto}</p>
          </li>
        ))}
      </ul>
    </Seccion>
  )
}

function Descubre() {
  const perfiles = [
    {
      tema: 'estudiante',
      icono: <School />,
      titulo: 'Estudiantes',
      puntos: [
        'Consulta tus notas y tu asistencia',
        'Revisa tu horario y tus actividades',
        'Entrega tus trabajos en PDF',
      ],
    },
    {
      tema: 'docente',
      icono: <GraduationCap />,
      titulo: 'Docentes',
      puntos: [
        'Registra asistencia y notas en minutos',
        'Publica los promedios del bimestre',
        'Atiende las alertas de tus estudiantes',
      ],
    },
    {
      tema: 'admin',
      icono: <ChartColumn />,
      titulo: 'Coordinación y administración',
      puntos: [
        'Panel de riesgo de todo el colegio',
        'Matrículas, horarios y estructura académica',
        'Reportes y boletas en PDF y Excel',
      ],
    },
  ]
  return (
    <Seccion id="descubre" titulo="Descubre Ayni" className="bg-fondo">
      <ul className="mt-10 grid gap-5 md:grid-cols-3">
        {perfiles.map((p) => (
          <li key={p.titulo} data-tema={p.tema} className="rounded-2xl border border-borde bg-superficie p-6">
            <span
              className="grid size-12 place-items-center rounded-xl bg-primario-suave text-primario-oscuro [&_svg]:size-6"
              aria-hidden
            >
              {p.icono}
            </span>
            <h3 className="mt-4 text-xl font-extrabold text-primario-oscuro">{p.titulo}</h3>
            <ul className="mt-3 flex flex-col gap-2 text-texto-suave">
              {p.puntos.map((punto) => (
                <li key={punto} className="flex gap-2">
                  <span className="mt-2 size-2 shrink-0 rounded-full bg-primario" aria-hidden />
                  {punto}
                </li>
              ))}
            </ul>
          </li>
        ))}
      </ul>
    </Seccion>
  )
}

function PikiYTambo() {
  return (
    <Seccion id="piki-y-tambo" titulo="Piki y Tambo">
      <p className="mx-auto mt-6 max-w-2xl text-center text-lg text-texto-suave">
        Dos amigos de los Andes que te acompañan dentro de AYNI.
      </p>
      <div className="mt-10 grid gap-6 md:grid-cols-2">
        <article className="flex flex-col items-center gap-4 rounded-3xl bg-[#fdf0e6] p-6 text-center sm:flex-row sm:text-left">
          <Ilustracion
            imagen={IMG.piki}
            sizes="160px"
            alt="Piki, una vizcacha sonriente"
            className="flotar h-auto w-40 shrink-0"
          />
          <div>
            <h3 className="text-2xl font-black text-primario-oscuro">Piki</h3>
            <p className="mt-2 text-texto-suave">
              Una vizcacha curiosa que acompaña a los estudiantes: te recuerda tus actividades y celebra tus avances.
            </p>
          </div>
        </article>
        <article
          data-tema="docente"
          className="flex flex-col items-center gap-4 rounded-3xl bg-cielo/70 p-6 text-center sm:flex-row sm:text-left"
        >
          <Ilustracion
            imagen={IMG.tambo}
            sizes="160px"
            alt="Tambo, un oso de anteojos saludando"
            className="flotar h-auto w-40 shrink-0 [animation-delay:1s]"
          />
          <div>
            <h3 className="text-2xl font-black text-primario-oscuro">Tambo</h3>
            <p className="mt-2 text-texto-suave">
              Un oso de anteojos paciente que acompaña a los docentes: ordena la información para que el tiempo rinda en
              el aula.
            </p>
          </div>
        </article>
      </div>
    </Seccion>
  )
}

function Contacto() {
  return (
    <Seccion id="contacto" titulo="Contacto" className="bg-fondo">
      <div className="mx-auto mt-8 grid max-w-3xl gap-5 sm:grid-cols-2">
        <div className="rounded-2xl border border-borde bg-superficie p-6">
          <h3 className="flex items-center gap-2 text-lg font-extrabold">
            <School className="size-5 text-primario" aria-hidden /> Institución
          </h3>
          <p className="mt-2 font-semibold">IEP El Señor es mi Pastor</p>
          <p className="mt-1 flex items-start gap-2 text-texto-suave">
            <MapPin className="mt-0.5 size-4 shrink-0" aria-hidden /> Nuevo Chimbote, Áncash · UGEL Santa
          </p>
        </div>
        <div className="rounded-2xl border border-borde bg-superficie p-6">
          <h3 className="flex items-center gap-2 text-lg font-extrabold">
            <Users className="size-5 text-primario" aria-hidden /> Equipo
          </h3>
          <p className="mt-2 font-semibold">Grupo 02 · Aplicaciones en la Nube</p>
          <p className="mt-1 text-texto-suave">
            Escuela de Ingeniería de Sistemas e Informática, Universidad Nacional del Santa.
          </p>
        </div>
      </div>
      <div className="mt-10 text-center">
        <Button asChild tamano="grande" className="rounded-full">
          <Link to="/ingresar">Ingresar a Ayni</Link>
        </Button>
      </div>
    </Seccion>
  )
}
