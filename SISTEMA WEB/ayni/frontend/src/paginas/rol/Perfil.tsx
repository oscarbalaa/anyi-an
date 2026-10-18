import { zodResolver } from '@hookform/resolvers/zod'
import { BarChart3, Clock, GraduationCap, Pencil, Settings, Users } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { useForm } from 'react-hook-form'
import { FormCambioContrasena } from '@/components/FormCambioContrasena'
import { Ilustracion } from '@/components/Ilustracion'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { Card, CardTitulo } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import type { ErrorApi } from '@/lib/api'
import { IMG } from '@/lib/imagenes'
import { useSesion } from '@/lib/sesion-contexto'
import { fechaHora, useGuardarPerfil, usePerfil, type Perfil } from '@/lib/usuarios'
import { esquemaContacto, filtros, type DatosContacto } from '@/lib/validaciones'
import type { Rol } from '@/layout/roles'

const ROL_TEXTO = { ADMIN: 'Administrador', DOCENTE: 'Docente', ESTUDIANTE: 'Estudiante' } as const

// Solo informativo: lo que puede hacer la administración en el sistema
const PERMISOS = [
  { icono: GraduationCap, titulo: 'Gestión académica', texto: 'Periodos, secciones, aulas y matrículas.' },
  { icono: Users, titulo: 'Usuarios y desbloqueos', texto: 'Cuentas, accesos y contraseñas temporales.' },
  { icono: BarChart3, titulo: 'Riesgo institucional', texto: 'Alertas y criterios de riesgo.' },
  { icono: Settings, titulo: 'Configuración de periodos', texto: 'Fechas de cierre y datos del colegio.' },
]

// CU-05: RF-14 y RF-15
export default function Perfil({ rol }: { rol: Rol }) {
  const { data: perfil, isPending, isError, refetch } = usePerfil()
  const { usuario } = useSesion()

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6">
      <header>
        <h1 className="text-3xl font-black sm:text-4xl">Mi perfil</h1>
        <p className="mt-1 text-lg text-texto-suave">
          {rol === 'admin' ? 'Datos personales, acceso y alcance institucional.' : 'Tus datos personales y de acceso.'}
        </p>
      </header>

      {isPending ? (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]">
          <Esqueleto className="h-96" />
          <Esqueleto className="h-96" />
        </div>
      ) : isError ? (
        <EstadoVacio
          titulo="No se pudo cargar tu perfil"
          descripcion="Revisa tu conexión e inténtalo de nuevo."
          accion={<Button onClick={() => refetch()}>Reintentar</Button>}
        />
      ) : (
        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]">
          <Identidad perfil={perfil} rol={rol} />
          <div className="flex flex-col gap-6">
            <DatosPersonales perfil={perfil} />
            {rol === 'admin' && <Permisos />}
            {usuario && (
              <Card>
                <CardTitulo>Cambiar contraseña</CardTitulo>
                <p className="mt-1 mb-4 text-sm text-texto-suave">
                  Al guardarla se cierran tus sesiones abiertas en otros equipos.
                </p>
                <FormCambioContrasena usuario={usuario} disposicion="fila" />
              </Card>
            )}
          </div>
        </div>
      )}
    </div>
  )
}

function Identidad({ perfil, rol }: { perfil: Perfil; rol: Rol }) {
  const mascota = rol === 'docente' ? IMG.tambo : rol === 'estudiante' ? IMG.piki : null
  const insignia = perfil.rol === 'ESTUDIANTE' && perfil.aula ? `Estudiante · ${perfil.aula}` : ROL_TEXTO[perfil.rol]
  const cargo = perfil.rol === 'DOCENTE' ? perfil.coordinacion : perfil.rol === 'ADMIN' ? perfil.cargo : null
  const extra = cargo !== insignia ? cargo : null

  return (
    <Card className="flex flex-col items-center gap-3 overflow-hidden pb-0 text-center sm:pb-0 lg:sticky lg:top-24">
      <span
        className="mt-2 grid size-28 place-items-center rounded-full bg-primario-suave text-4xl font-black text-primario-oscuro"
        aria-hidden
      >
        {perfil.iniciales}
      </span>
      <h2 className="text-2xl font-extrabold">{perfil.nombre_completo}</h2>
      {perfil.rol === 'DOCENTE' && perfil.especialidad && (
        <p className="-mt-2 text-texto-suave">Docente · {perfil.especialidad}</p>
      )}
      <div className="flex flex-wrap justify-center gap-2">
        <Insignia tono="primario">{insignia}</Insignia>
        {extra && <Insignia tono="info">{extra}</Insignia>}
        <Insignia tono={perfil.estado === 'ACTIVO' ? 'exito' : 'peligro'} punto>
          {perfil.estado === 'ACTIVO' ? 'Activo' : perfil.estado === 'BLOQUEADO' ? 'Bloqueado' : 'Inactivo'}
        </Insignia>
      </div>
      <p className="flex items-center gap-2 text-sm text-texto-suave">
        <Clock className="size-4" aria-hidden /> Último acceso: {fechaHora(perfil.ultimo_acceso)}
      </p>
      {mascota ? (
        <Ilustracion imagen={mascota} sizes="220px" alt="" className="mt-auto h-auto w-52" />
      ) : (
        <Ilustracion imagen={IMG.bordeAdmin} sizes="400px" alt="" className="mt-auto h-auto w-full" />
      )}
    </Card>
  )
}

function DatosPersonales({ perfil }: { perfil: Perfil }) {
  const [editando, setEditando] = useState(false)

  const filas: [string, ReactNode][] = [
    ['Documento', perfil.documento],
    ...(perfil.codigo ? ([['Código', perfil.codigo]] as [string, ReactNode][]) : []),
    ['Correo', perfil.correo ?? 'Sin correo registrado'],
    ['Celular', perfil.celular ? perfil.celular.replace(/(\d{3})(\d{3})(\d{3})/, '$1 $2 $3') : 'Sin celular'],
  ]
  if (perfil.rol === 'ADMIN') filas.push(['Cargo', perfil.cargo], ['Ámbito', perfil.ambito])
  if (perfil.rol === 'DOCENTE') {
    filas.push(['Especialidad', perfil.especialidad ?? '—'])
    filas.push(['Cursos a cargo', perfil.cursos?.length ? perfil.cursos.join('; ') : 'Sin cursos asignados'])
    if (perfil.tutoria) filas.push(['Tutoría', perfil.tutoria])
    if (perfil.coordinacion) filas.push(['Coordinación', perfil.coordinacion])
  }
  if (perfil.rol === 'ESTUDIANTE')
    filas.push(['Aula', perfil.aula ?? 'Sin matrícula'], ['Tutor(a)', perfil.tutor ?? '—'])

  return (
    <Card>
      <div className="flex items-center justify-between gap-3">
        <CardTitulo>Datos personales</CardTitulo>
        {!editando && (
          <Button variante="fantasma" tamano="chico" onClick={() => setEditando(true)}>
            <Pencil className="size-4" aria-hidden /> Editar contacto
          </Button>
        )}
      </div>
      {editando ? (
        <EditarContacto perfil={perfil} onListo={() => setEditando(false)} />
      ) : (
        <dl className="mt-4 divide-y divide-borde">
          {filas.map(([etiqueta, valor]) => (
            <div key={etiqueta} className="grid gap-1 py-2.5 sm:grid-cols-[10rem_1fr] sm:gap-4">
              <dt className="font-semibold text-texto-suave">{etiqueta}</dt>
              <dd className="[overflow-wrap:anywhere]">{valor}</dd>
            </div>
          ))}
        </dl>
      )}
    </Card>
  )
}

function EditarContacto({ perfil, onListo }: { perfil: Perfil; onListo: () => void }) {
  const guardar = useGuardarPerfil()
  const avisos = useAvisos()
  const sesion = useSesion()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<DatosContacto>({
    resolver: zodResolver(esquemaContacto),
    defaultValues: { correo: perfil.correo ?? '', celular: perfil.celular ?? '' },
  })

  const enviar = handleSubmit(async (d) => {
    try {
      const r = await guardar.mutateAsync(d)
      if (sesion.usuario) sesion.actualizar({ ...sesion.usuario, correo: r.perfil.correo })
      avisos.exito(r.mensaje)
      onListo()
    } catch (e) {
      const error = e as ErrorApi
      if (error.errores.correo) setError('correo', { message: error.errores.correo[0] })
      if (error.errores.celular) setError('celular', { message: error.errores.celular[0] })
      if (!error.errores.correo && !error.errores.celular) avisos.error(error.message)
    }
  })

  return (
    <form onSubmit={enviar} noValidate className="mt-4 flex flex-col gap-4">
      <Campo
        etiqueta="Correo"
        type="email"
        inputMode="email"
        maxLength={120}
        autoComplete="email"
        ayuda="Sirve para recuperar tu contraseña. Puedes dejarlo vacío."
        error={errors.correo?.message}
        {...register('correo')}
      />
      <Campo
        etiqueta="Celular"
        inputMode="numeric"
        maxLength={9}
        autoComplete="tel-national"
        placeholder="987654321"
        filtro={filtros.celular}
        error={errors.celular?.message}
        {...register('celular')}
      />
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variante="secundario" onClick={onListo}>
          Cancelar
        </Button>
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Guardando…' : 'Guardar cambios'}
        </Button>
      </div>
    </form>
  )
}

function Permisos() {
  return (
    <Card>
      <CardTitulo>Permisos principales</CardTitulo>
      <ul className="mt-4 grid gap-4 sm:grid-cols-2">
        {PERMISOS.map((p) => (
          <li key={p.titulo} className="flex items-start gap-3">
            <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-primario-suave text-primario-oscuro">
              <p.icono className="size-5" aria-hidden />
            </span>
            <div>
              <p className="font-bold">{p.titulo}</p>
              <p className="text-sm text-texto-suave">{p.texto}</p>
            </div>
          </li>
        ))}
      </ul>
    </Card>
  )
}
