import { zodResolver } from '@hookform/resolvers/zod'
import { Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { Card } from '@/components/ui/card'
import { Ventana } from '@/components/ui/dialogo'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import { Selector } from '@/components/ui/selector'
import { TablaDatos } from '@/components/ui/tabla-datos'
import { useAsignaturas, useCambio, useTipos, type Asignatura } from '@/lib/estructura'
import { marcarErrores } from '@/lib/formularios'
import { esquemaAsignatura, esquemaTipo, type DatosAsignatura } from '@/lib/validaciones'
import { Encabezado } from './Encabezado'
import { useConfirmar } from './useConfirmar'

const ESTADOS = [
  { valor: 'ACTIVO', texto: 'Activo' },
  { valor: 'INACTIVO', texto: 'Inactivo' },
]

const Estado = ({ estado }: { estado: 'ACTIVO' | 'INACTIVO' }) => (
  <Insignia tono={estado === 'ACTIVO' ? 'exito' : 'neutro'} punto>
    {estado === 'ACTIVO' ? 'Activo' : 'Inactivo'}
  </Insignia>
)

// RF-21 y RF-22 (mockup admin 07)
export function PestanaAsignaturas() {
  const { data, isPending, isError } = useAsignaturas()
  const [form, setForm] = useState<Asignatura | 'nueva' | null>(null)

  return (
    <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
      <Card>
        <Encabezado titulo="Asignaturas" detalle="Las inactivas ya no se asignan a nuevas aulas.">
          <Button onClick={() => setForm('nueva')}>
            <Plus className="size-5" aria-hidden /> Nueva asignatura
          </Button>
        </Encabezado>
        {isPending ? (
          <Esqueleto className="h-64" />
        ) : isError ? (
          <EstadoVacio titulo="No se pudieron cargar las asignaturas" />
        ) : (
          <TablaDatos
            filas={data}
            claveFila={(a) => a.id}
            descripcion="Asignaturas"
            textoBusqueda={(a) => `${a.codigo} ${a.nombre}`}
            vacio="Aún no hay asignaturas."
            columnas={[
              { clave: 'codigo', titulo: 'Código', celda: (a) => <span className="font-mono">{a.codigo}</span> },
              {
                clave: 'nombre',
                titulo: 'Asignatura',
                principal: true,
                celda: (a) => <span className="font-extrabold">{a.nombre}</span>,
              },
              { clave: 'horas', titulo: 'Horas / semana', celda: (a) => a.horas_semanales },
              { clave: 'aulas', titulo: 'Aulas', celda: (a) => a.cursos },
              { clave: 'estado', titulo: 'Estado', celda: (a) => <Estado estado={a.estado} /> },
              {
                clave: 'accion',
                titulo: 'Acción',
                celda: (a) => (
                  <Button
                    variante="secundario"
                    tamano="chico"
                    onClick={() => setForm(a)}
                    aria-label={`Editar ${a.nombre}`}
                  >
                    Editar
                  </Button>
                ),
              },
            ]}
          />
        )}
      </Card>

      <TiposEvaluacion />

      <Ventana
        abierto={form !== null}
        titulo={form === 'nueva' ? 'Nueva asignatura' : `Editar ${form?.nombre ?? ''}`}
        onCerrar={() => setForm(null)}
        conCerrar
      >
        {form !== null && <FormAsignatura asignatura={form === 'nueva' ? null : form} onListo={() => setForm(null)} />}
      </Ventana>
    </div>
  )
}

function FormAsignatura({ asignatura, onListo }: { asignatura: Asignatura | null; onListo: () => void }) {
  const cambio = useCambio()
  const avisos = useAvisos()
  const { confirmar, dialogo } = useConfirmar()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<DatosAsignatura>({
    resolver: zodResolver(esquemaAsignatura),
    defaultValues: {
      codigo: asignatura?.codigo ?? '',
      nombre: asignatura?.nombre ?? '',
      horas_semanales: String(asignatura?.horas_semanales ?? ''),
      estado: asignatura?.estado ?? 'ACTIVO',
    },
  })

  const guardar = handleSubmit(async (d) => {
    const datos = { ...d, horas_semanales: Number(d.horas_semanales) }
    try {
      const r = await cambio.mutateAsync(
        asignatura
          ? { metodo: 'PUT', ruta: `/asignaturas/${asignatura.id}`, datos }
          : { metodo: 'POST', ruta: '/asignaturas', datos },
      )
      avisos.exito(r.mensaje)
      onListo()
    } catch (e) {
      const mensaje = marcarErrores(e, setError, ['codigo', 'nombre', 'horas_semanales', 'estado'])
      if (mensaje) avisos.error(mensaje)
    }
  })

  return (
    <form onSubmit={guardar} noValidate className="flex flex-col gap-4">
      <div className="grid gap-4 sm:grid-cols-[1fr_2fr]">
        <Campo
          etiqueta="Código"
          maxLength={20}
          placeholder="MAT"
          autoCapitalize="characters"
          filtro={(v) => v.toUpperCase().replace(/[^A-Z0-9-]/g, '')}
          error={errors.codigo?.message}
          {...register('codigo')}
        />
        <Campo
          etiqueta="Nombre"
          maxLength={100}
          placeholder="Matemática"
          error={errors.nombre?.message}
          {...register('nombre')}
        />
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <Campo
          etiqueta="Horas por semana"
          inputMode="numeric"
          maxLength={2}
          filtro={(v) => v.replace(/\D/g, '')}
          error={errors.horas_semanales?.message}
          {...register('horas_semanales')}
        />
        <Selector etiqueta="Estado" opciones={ESTADOS} {...register('estado')} />
      </div>
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
        {asignatura && asignatura.cursos === 0 ? (
          <Button
            variante="fantasma"
            className="text-peligro"
            onClick={() =>
              confirmar({
                titulo: `¿Eliminar ${asignatura.nombre}?`,
                texto: 'No se dicta en ninguna aula. No se puede deshacer.',
                textoConfirmar: 'Sí, eliminar',
                peligro: true,
                metodo: 'DELETE',
                ruta: `/asignaturas/${asignatura.id}`,
                despues: onListo,
              })
            }
          >
            <Trash2 className="size-5" aria-hidden /> Eliminar
          </Button>
        ) : (
          <span />
        )}
        <div className="flex flex-col-reverse gap-2 sm:flex-row">
          <Button variante="secundario" onClick={onListo}>
            Cancelar
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'Guardando…' : asignatura ? 'Guardar cambios' : 'Crear asignatura'}
          </Button>
        </div>
      </div>
      {dialogo}
    </form>
  )
}

function TiposEvaluacion() {
  const { data } = useTipos()
  const cambio = useCambio()
  const avisos = useAvisos()
  const { confirmar, dialogo } = useConfirmar()
  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors },
  } = useForm<{ nombre: string }>({ resolver: zodResolver(esquemaTipo), defaultValues: { nombre: '' } })

  const agregar = handleSubmit(async (d) => {
    try {
      const r = await cambio.mutateAsync({
        metodo: 'POST',
        ruta: '/tipos-evaluacion',
        datos: { ...d, estado: 'ACTIVO' },
      })
      avisos.exito(r.mensaje)
      reset()
    } catch (e) {
      const mensaje = marcarErrores(e, setError, ['nombre'])
      if (mensaje) avisos.error(mensaje)
    }
  })

  const alternar = (t: { id: number; nombre: string; estado: 'ACTIVO' | 'INACTIVO' }) =>
    cambio.mutate(
      {
        metodo: 'PUT',
        ruta: `/tipos-evaluacion/${t.id}`,
        datos: { nombre: t.nombre, estado: t.estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO' },
      },
      { onSuccess: (r) => avisos.exito(r.mensaje), onError: (e) => avisos.error((e as Error).message) },
    )

  return (
    <Card>
      <Encabezado titulo="Tipos de evaluación" detalle="Los docentes eligen uno al crear cada evaluación." />
      <ul className="flex flex-col divide-y divide-borde">
        {data?.map((t) => (
          <li key={t.id} className="flex flex-wrap items-center gap-2 py-2">
            <span className="flex-1 font-semibold">{t.nombre}</span>
            <button
              type="button"
              onClick={() => alternar(t)}
              className="cursor-pointer"
              aria-label={`${t.estado === 'ACTIVO' ? 'Inactivar' : 'Activar'} ${t.nombre}`}
              title={t.estado === 'ACTIVO' ? 'Inactivar' : 'Activar'}
            >
              <Estado estado={t.estado} />
            </button>
            {t.evaluaciones === 0 && (
              <Button
                variante="fantasma"
                tamano="chico"
                aria-label={`Eliminar ${t.nombre}`}
                onClick={() =>
                  confirmar({
                    titulo: `¿Eliminar "${t.nombre}"?`,
                    texto: 'Ninguna evaluación lo usa todavía.',
                    textoConfirmar: 'Sí, eliminar',
                    peligro: true,
                    metodo: 'DELETE',
                    ruta: `/tipos-evaluacion/${t.id}`,
                  })
                }
              >
                <Trash2 className="size-4" aria-hidden />
              </Button>
            )}
          </li>
        ))}
      </ul>
      <form onSubmit={agregar} noValidate className="mt-4 flex items-start gap-2">
        <Campo
          etiqueta="Nuevo tipo"
          placeholder="Exposición"
          maxLength={40}
          className="flex-1"
          error={errors.nombre?.message}
          {...register('nombre')}
        />
        <Button type="submit" className="mt-7.5" aria-label="Agregar tipo de evaluación">
          <Plus className="size-5" aria-hidden />
        </Button>
      </form>
      {dialogo}
    </Card>
  )
}
