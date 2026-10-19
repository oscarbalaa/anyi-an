import { CalendarDays, ChevronDown, Clock, LogOut, Menu, UserRound, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link, Navigate, NavLink, Outlet, useLocation, useNavigate } from 'react-router'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Cargando } from '@/components/ui/estados'
import { useCalendarioActual } from '@/lib/calendario'
import { ProveedorDatos } from '@/lib/datos'
import type { Usuario } from '@/lib/sesion'
import { useSesion } from '@/lib/sesion-contexto'
import { cn } from '@/lib/utils'
import { Campana } from './Campana'
import { ROLES, type Rol } from './roles'

// RF-09: cada rol solo entra a su propia sección
export default function LayoutRol({ rol }: { rol: Rol }) {
  const { usuario, verificando } = useSesion()
  const { pathname } = useLocation()

  if (verificando) return <Cargando texto="Verificando tu sesión…" />
  if (!usuario) return <Navigate to={ROLES[rol].ingreso} state={{ desde: pathname }} replace />
  if (usuario.acceso !== rol) return <Navigate to="/sin-permiso" replace />
  if (usuario.debe_cambiar_contrasena) return <Navigate to="/cambiar-contrasena" replace />

  return (
    <ProveedorDatos>
      <Estructura rol={rol} usuario={usuario} />
    </ProveedorDatos>
  )
}

function Estructura({ rol, usuario }: { rol: Rol; usuario: Usuario }) {
  const config = ROLES[rol]
  const [menuAbierto, setMenuAbierto] = useState(false)
  useEffect(() => {
    if (!menuAbierto) return
    const cerrar = (e: KeyboardEvent) => e.key === 'Escape' && setMenuAbierto(false)
    window.addEventListener('keydown', cerrar)
    return () => window.removeEventListener('keydown', cerrar)
  }, [menuAbierto])

  return (
    <div data-tema={rol} className="min-h-screen bg-fondo text-texto">
      <a
        href="#contenido"
        className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-superficie focus:p-3"
      >
        Saltar al contenido
      </a>

      <header className="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-borde bg-superficie/95 px-4 backdrop-blur sm:px-6">
        <button
          type="button"
          className="grid size-10 cursor-pointer place-items-center rounded-lg hover:bg-primario-suave lg:hidden"
          onClick={() => setMenuAbierto(true)}
          aria-label="Abrir menú"
          aria-expanded={menuAbierto}
          aria-controls="menu-lateral"
        >
          <Menu className="size-6" aria-hidden />
        </button>
        <Link to={config.base} className="flex items-center gap-3">
          <img src={config.logo} alt="Ayni" width={88} height={42} className="h-9 w-auto" />
          <span className="hidden border-l border-borde pl-3 font-bold text-texto-suave sm:inline">
            {config.etiqueta}
          </span>
        </Link>
        <div className="ml-auto flex items-center gap-1 sm:gap-3">
          <Campana base={config.base} />
          <MenuUsuario rol={rol} usuario={usuario} />
        </div>
      </header>

      <div className="lg:flex">
        {menuAbierto && (
          <div className="fixed inset-0 z-30 bg-black/40 lg:hidden" onClick={() => setMenuAbierto(false)} aria-hidden />
        )}
        <aside
          id="menu-lateral"
          className={cn(
            'fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-borde bg-superficie transition-transform lg:sticky lg:top-16 lg:z-10 lg:h-[calc(100vh-4rem)] lg:translate-x-0',
            // invisible: en celular, cerrado, no recibe el foco del teclado
            menuAbierto ? 'visible translate-x-0' : 'invisible -translate-x-full lg:visible',
          )}
          aria-label="Menú principal"
        >
          <div className="flex h-16 items-center justify-between border-b border-borde px-4 lg:hidden">
            <img src={config.logo} alt="Ayni" width={88} height={42} className="h-9 w-auto" />
            <button
              type="button"
              onClick={() => setMenuAbierto(false)}
              className="grid size-10 cursor-pointer place-items-center rounded-lg hover:bg-primario-suave"
              aria-label="Cerrar menú"
            >
              <X className="size-6" aria-hidden />
            </button>
          </div>
          <nav className="flex-1 overflow-y-auto p-3">
            <ul className="flex flex-col gap-1">
              {config.menu.map((item) => (
                <li key={item.ruta}>
                  <NavLink
                    to={item.ruta ? `${config.base}/${item.ruta}` : config.base}
                    end={!item.ruta}
                    onClick={() => setMenuAbierto(false)}
                    className={({ isActive }) =>
                      cn(
                        'flex items-center gap-3 rounded-xl px-4 py-3 font-semibold transition-colors',
                        isActive
                          ? 'bg-primario-suave text-primario-oscuro shadow-[inset_4px_0_0_var(--primario)]'
                          : 'text-texto hover:bg-fondo',
                      )
                    }
                  >
                    <item.icono className="size-5 shrink-0" aria-hidden />
                    {item.texto}
                  </NavLink>
                </li>
              ))}
            </ul>
          </nav>
          <PeriodoActual />
        </aside>

        <main id="contenido" className="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
          {rol === 'docente' && <AvisoCierre />}
          <Outlet />
        </main>
      </div>
    </div>
  )
}

const FECHA_CIERRE = new Intl.DateTimeFormat('es-PE', { day: 'numeric', month: 'long', timeZone: 'UTC' })

/** RF-18: el docente ve la fecha de cierre de notas cuando faltan pocos días */
function AvisoCierre() {
  const { data } = useCalendarioActual()
  const b = data?.bimestre
  if (!b || b.dias_para_cierre === null || b.dias_para_cierre < 0 || b.dias_para_cierre > 7) return null
  const dia = FECHA_CIERRE.format(new Date(`${b.fecha_cierre_notas}T00:00:00Z`))
  const cuando =
    b.dias_para_cierre === 0
      ? 'es hoy'
      : b.dias_para_cierre === 1
        ? `es mañana (${dia})`
        : `es el ${dia} (faltan ${b.dias_para_cierre} días)`

  return (
    <p
      role="status"
      className="mx-auto mb-6 flex max-w-7xl items-start gap-3 rounded-xl border border-aviso/30 bg-aviso/10 p-4"
    >
      <Clock className="mt-0.5 size-5 shrink-0 text-aviso" aria-hidden />
      <span>
        <strong>
          El cierre de notas del {b.nombre} {cuando}.
        </strong>{' '}
        Después ya no podrás registrar notas de ese bimestre.
      </span>
    </p>
  )
}

function PeriodoActual() {
  const { data } = useCalendarioActual()
  return (
    <div className="flex items-center gap-3 border-t border-borde p-4 text-sm" data-testid="periodo-actual">
      <CalendarDays className="size-6 text-texto-suave" aria-hidden />
      <div>
        <p className="text-texto-suave">Periodo {data?.periodo?.nombre ?? '—'}</p>
        <p className="font-bold">{data?.bimestre?.nombre ?? 'Sin bimestre activo'}</p>
      </div>
    </div>
  )
}

function MenuUsuario({ rol, usuario }: { rol: Rol; usuario: Usuario }) {
  const [abierto, setAbierto] = useState(false)
  const ref = useRef<HTMLDivElement>(null)
  const avisos = useAvisos()
  const sesion = useSesion()
  const navegar = useNavigate()
  const base = ROLES[rol].base

  const salir = async () => {
    setAbierto(false)
    await sesion.salir()
    avisos.exito('Cerraste sesión. ¡Hasta pronto!')
    navegar(ROLES[rol].ingreso, { replace: true })
  }

  useEffect(() => {
    if (!abierto) return
    const fuera = (e: MouseEvent) => !ref.current?.contains(e.target as Node) && setAbierto(false)
    const esc = (e: KeyboardEvent) => e.key === 'Escape' && setAbierto(false)
    document.addEventListener('mousedown', fuera)
    document.addEventListener('keydown', esc)
    return () => {
      document.removeEventListener('mousedown', fuera)
      document.removeEventListener('keydown', esc)
    }
  }, [abierto])

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setAbierto((a) => !a)}
        className="flex cursor-pointer items-center gap-2 rounded-xl p-1 pr-2 hover:bg-primario-suave"
        aria-expanded={abierto}
        aria-haspopup="menu"
        aria-label="Menú de la cuenta"
      >
        <span
          className="grid size-10 place-items-center rounded-full bg-primario-suave font-extrabold text-primario-oscuro"
          aria-hidden
        >
          {usuario.iniciales}
        </span>
        <span className="hidden text-left leading-tight md:block">
          <span className="block font-bold" data-testid="nombre-usuario">
            {usuario.nombre}
          </span>
          <span className="block text-sm text-texto-suave">{usuario.detalle ?? ROLES[rol].etiqueta}</span>
        </span>
        <ChevronDown className="size-4 text-texto-suave" aria-hidden />
      </button>
      {abierto && (
        <div
          role="menu"
          className="absolute right-0 mt-2 w-56 animate-aparecer rounded-xl border border-borde bg-superficie p-1.5 shadow-lg"
        >
          <Link
            role="menuitem"
            to={`${base}/perfil`}
            className="flex items-center gap-2 rounded-lg px-3 py-2.5 hover:bg-fondo"
            onClick={() => setAbierto(false)}
          >
            <UserRound className="size-5" aria-hidden /> Mi perfil
          </Link>
          <button
            role="menuitem"
            type="button"
            className="flex w-full cursor-pointer items-center gap-2 rounded-lg px-3 py-2.5 text-left hover:bg-fondo"
            onClick={salir}
          >
            <LogOut className="size-5" aria-hidden /> Cerrar sesión
          </button>
        </div>
      )}
    </div>
  )
}
