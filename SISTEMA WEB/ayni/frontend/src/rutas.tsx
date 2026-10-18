import { Suspense, type ReactNode } from 'react'
import type { RouteObject } from 'react-router'
import { Cargando } from '@/components/ui/estados'
import { EXTRAS, ROLES, type Rol } from '@/layout/roles'
import {
  CambiarContrasena,
  Docentes,
  EstadoSistema,
  Estructura,
  Estudiantes,
  Ingreso,
  LayoutRol,
  Perfil,
  Proximamente,
  Recuperar,
  Restablecer,
  SelectorIngreso,
  Usuarios,
  VistaPrevia,
} from '@/paginas/diferidas'
import PaginaError from '@/paginas/errores/PaginaError'
import Landing from '@/paginas/publicas/Landing'

const cargar = (elemento: ReactNode) => <Suspense fallback={<Cargando />}>{elemento}</Suspense>

/** Pantallas ya construidas; las demás muestran en qué módulo llegan */
const pantalla = (rol: Rol, ruta: string): ReactNode => {
  if (ruta === 'perfil') return <Perfil rol={rol} />
  if (rol === 'admin' && ruta === 'usuarios') return <Usuarios />
  if (rol === 'admin' && ruta === 'estructura') return <Estructura />
  if (rol === 'admin' && ruta === 'docentes') return <Docentes />
  if (rol === 'admin' && ruta === 'estudiantes') return <Estudiantes />
  return <Proximamente rol={rol} />
}

const rutasDeRol = (rol: Rol): RouteObject => {
  const pantallas = new Set([...ROLES[rol].menu, ...EXTRAS].map((i) => i.ruta).filter(Boolean))
  return {
    path: ROLES[rol].base,
    element: cargar(<LayoutRol rol={rol} />),
    children: [
      { index: true, element: cargar(<VistaPrevia rol={rol} />) },
      ...[...pantallas].map((ruta) => ({ path: ruta, element: cargar(pantalla(rol, ruta)) })),
      { path: '*', element: <PaginaError codigo={404} /> },
    ],
  }
}

export const rutas: RouteObject[] = [
  { path: '/', element: <Landing /> },
  { path: '/estado', element: cargar(<EstadoSistema />) },
  { path: '/ingresar', element: cargar(<SelectorIngreso />) },
  { path: '/ingresar/estudiante', element: cargar(<Ingreso rol="estudiante" />) },
  { path: '/ingresar/docente', element: cargar(<Ingreso rol="docente" />) },
  { path: '/ingresar/administracion', element: cargar(<Ingreso rol="admin" />) },
  { path: '/recuperar/estudiante', element: cargar(<Recuperar rol="estudiante" />) },
  { path: '/recuperar/docente', element: cargar(<Recuperar rol="docente" />) },
  { path: '/recuperar/administracion', element: cargar(<Recuperar rol="admin" />) },
  { path: '/restablecer', element: cargar(<Restablecer />) },
  { path: '/cambiar-contrasena', element: cargar(<CambiarContrasena />) },
  rutasDeRol('estudiante'),
  rutasDeRol('docente'),
  rutasDeRol('admin'),
  { path: '/sin-permiso', element: <PaginaError codigo={403} /> },
  { path: '*', element: <PaginaError codigo={404} /> },
]
