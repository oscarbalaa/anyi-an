import {
  Bell,
  BookOpenCheck,
  CalendarCheck,
  CalendarDays,
  ChartColumn,
  ClipboardList,
  FileText,
  GraduationCap,
  House,
  Network,
  NotebookPen,
  ShieldCheck,
  Sheet,
  TriangleAlert,
  Users,
  type LucideIcon,
} from 'lucide-react'
import logoAdmin from '@/assets/logo-admin.svg'
import logoAyni from '@/assets/logo-ayni.svg'
import logoDocente from '@/assets/logo-docente.svg'

export type Rol = 'estudiante' | 'docente' | 'admin'

export type ItemMenu = { ruta: string; texto: string; icono: LucideIcon; modulo?: string }

type ConfigRol = { etiqueta: string; logo: string; base: string; ingreso: string; menu: ItemMenu[] }

// "modulo" indica en qué módulo del plan se construye la pantalla
export const ROLES: Record<Rol, ConfigRol> = {
  estudiante: {
    etiqueta: 'Estudiante',
    logo: logoAyni,
    base: '/estudiante',
    ingreso: '/ingresar/estudiante',
    menu: [
      { ruta: '', texto: 'Inicio', icono: House },
      { ruta: 'notas', texto: 'Mis notas', icono: FileText, modulo: 'M9' },
      { ruta: 'asistencia', texto: 'Mi asistencia', icono: CalendarCheck, modulo: 'M8' },
      { ruta: 'horario', texto: 'Mi horario', icono: CalendarDays, modulo: 'M9' },
      { ruta: 'actividades', texto: 'Actividades', icono: ClipboardList, modulo: 'M9' },
      { ruta: 'notificaciones', texto: 'Notificaciones', icono: Bell, modulo: 'M10' },
    ],
  },
  docente: {
    etiqueta: 'Docente',
    logo: logoDocente,
    base: '/docente',
    ingreso: '/ingresar/docente',
    menu: [
      { ruta: '', texto: 'Inicio', icono: House },
      { ruta: 'estudiantes', texto: 'Mis estudiantes', icono: Users, modulo: 'M12' },
      { ruta: 'asistencia', texto: 'Asistencia', icono: CalendarCheck, modulo: 'M8' },
      { ruta: 'evaluaciones', texto: 'Evaluaciones', icono: ClipboardList, modulo: 'M9' },
      { ruta: 'notas', texto: 'Notas', icono: NotebookPen, modulo: 'M9' },
      { ruta: 'calificaciones', texto: 'Calificaciones', icono: Sheet, modulo: 'M9' },
      { ruta: 'alertas', texto: 'Alertas', icono: TriangleAlert, modulo: 'M11' },
    ],
  },
  admin: {
    etiqueta: 'Administrativo',
    logo: logoAdmin,
    base: '/admin',
    ingreso: '/ingresar/administracion',
    menu: [
      { ruta: '', texto: 'Inicio', icono: House },
      { ruta: 'riesgo', texto: 'Panel de riesgo', icono: ChartColumn, modulo: 'M11' },
      { ruta: 'alertas', texto: 'Alertas', icono: Bell, modulo: 'M11' },
      { ruta: 'estudiantes', texto: 'Estudiantes', icono: Users, modulo: 'M7' },
      { ruta: 'docentes', texto: 'Docentes', icono: GraduationCap, modulo: 'M7' },
      { ruta: 'asistencia', texto: 'Asistencia', icono: CalendarCheck, modulo: 'M8' },
      { ruta: 'usuarios', texto: 'Usuarios', icono: ShieldCheck, modulo: 'M5' },
      { ruta: 'estructura', texto: 'Estructura académica', icono: Network, modulo: 'M6' },
      { ruta: 'incidencias', texto: 'Incidencias', icono: BookOpenCheck, modulo: 'M10' },
    ],
  },
}

/** Pantallas que no están en el menú lateral (se abren desde la barra superior) */
export const EXTRAS: ItemMenu[] = [
  { ruta: 'perfil', texto: 'Mi perfil', icono: Users, modulo: 'M5' },
  { ruta: 'notificaciones', texto: 'Notificaciones', icono: Bell, modulo: 'M10' },
]
