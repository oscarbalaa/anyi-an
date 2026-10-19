import { Suspense, type ReactNode } from 'react'
import type { RouteObject } from 'react-router'
import { Cargando } from '@/components/ui/estados'
import { EXTRAS, ROLES, type Rol } from '@/layout/roles'
import {
  Actividades,
  Alertas,
  Asistencia,
  AsistenciaAdmin,
  Calificaciones,
  CambiarContrasena,
  Docentes,
  EstadoSistema,
  Estructura,
  Estudiantes,
  Evaluaciones,
  Ficha360,
  Incidencias,
  Inicio,
  Ingreso,
  LayoutRol,
  MiAsistencia,
  MiHorario,
  MisEstudiantes,
  MisNotas,
  Notas,
  Notificaciones,
  PanelRiesgo,
  Perfil,
  Recuperar,
  Reportes,
  Restablecer,
  SelectorIngreso,
  Usuarios,
  VistaPrevia,
} from '@/paginas/diferidas'
import PaginaError from '@/paginas/errores/PaginaError'
import Landing from '@/paginas/publicas/Landing'

const cargar = (elemento: ReactNode) => <Suspense fallback={<Cargando />}>{elemento}</Suspense>

/** La pantalla de cada ruta del menú; lo que no corresponde al rol es 404 */
const pantalla = (rol: Rol, ruta: string): ReactNode => {
  if (ruta === 'perfil') return <Perfil rol={rol} />
  if (ruta === 'diseno') return <VistaPrevia rol={rol} />
  if (ruta === 'ficha') return rol === 'estudiante' ? <PaginaError codigo={404} /> : <Ficha360 rol={rol} />
  if (rol === 'docente' && ruta === 'estudiantes') return <MisEstudiantes />
  if (rol === 'admin' && ruta === 'reportes') return <Reportes />
  if (ruta === 'notificaciones') return <Notificaciones rol={rol} />
  if (rol !== 'estudiante' && ruta === 'incidencias') return <Incidencias rol={rol} />
  if (rol !== 'estudiante' && ruta === 'alertas') return <Alertas rol={rol} />
  if ((rol === 'admin' && ruta === 'riesgo') || (rol === 'docente' && ruta === 'coordinacion'))
    return <PanelRiesgo rol={rol} />
  if (rol === 'admin' && ruta === 'usuarios') return <Usuarios />
  if (rol === 'admin' && ruta === 'estructura') return <Estructura />
  if (rol === 'admin' && ruta === 'docentes') return <Docentes />
  if (rol === 'admin' && ruta === 'estudiantes') return <Estudiantes />
  if (rol === 'admin' && ruta === 'asistencia') return <AsistenciaAdmin />
  if (rol === 'docente' && ruta === 'asistencia') return <Asistencia />
  if (rol === 'estudiante' && ruta === 'asistencia') return <MiAsistencia />
  if (rol === 'docente' && ruta === 'evaluaciones') return <Evaluaciones />
  if (rol === 'docente' && ruta === 'notas') return <Notas />
  if (rol === 'docente' && ruta === 'calificaciones') return <Calificaciones />
  if (rol === 'estudiante' && ruta === 'notas') return <MisNotas />
  if (rol === 'estudiante' && ruta === 'actividades') return <Actividades />
  if (rol === 'estudiante' && ruta === 'horario') return <MiHorario />
  return <PaginaError codigo={404} />
}

const rutasDeRol = (rol: Rol): RouteObject => {
  const pantallas = new Set([...ROLES[rol].menu, ...EXTRAS].map((i) => i.ruta).filter(Boolean))
  return {
    path: ROLES[rol].base,
    element: cargar(<LayoutRol rol={rol} />),
    children: [
      { index: true, element: cargar(<Inicio rol={rol} />) },
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
