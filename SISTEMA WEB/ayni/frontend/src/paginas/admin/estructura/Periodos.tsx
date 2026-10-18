import { zodResolver } from '@hookform/resolvers/zod'
import { CalendarCheck, Info, Lock, Pencil, Play, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { Card } from '@/components/ui/card'
import { Ventana } from '@/components/ui/dialogo'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia, type Tono } from '@/components/ui/insignia'
import { TablaDatos } from '@/components/ui/tabla-datos'
import { fecha, useCambio, usePeriodos, type Bimestre, type Periodo, type SituacionBimestre } from '@/lib/estructura'
import { marcarErrores } from '@/lib/formularios'
import { esquemaBimestre, esquemaPeriodo, type DatosBimestre, type DatosPeriodo } from '@/lib/validaciones'
import { Encabezado } from './Encabezado'
import { useConfirmar } from './useConfirmar'

const ESTADO_PERIODO: Record<Periodo['estado'], [Tono, string]> = {
  PLANIFICADO: ['neutro', 'Planificado'],
  EN_CURSO: ['exito', 'En curso'],
  CERRADO: ['neutro', 'Cerrado'],
}

const SITUACION: Record<SituacionBimestre, [Tono, string]> = {
  CERRADO: ['neutro', 'Cerrado'],
  EN_CURSO: ['exito', 'En curso'],
  POR_CERRAR: ['aviso', 'Notas abiertas'],
  PENDIENTE: ['info', 'Pendiente'],
  SIN_DEFINIR: ['aviso', 'Sin definir'],
}

const ROMANOS = ['I', 'II', 'III', 'IV']

// RF-17, RF-18 (mockup admin 05)
export function PestanaPeriodos() {
  const { data: periodos, isPending, isError } = usePeriodos()
  const [elegido, setElegido] = useState<number | null>(null)
  const [formPeriodo, setFormPeriodo] = useState<Periodo | 'nuevo' | null>(null)
  const [formBimestre, setFormBimestre] = useState<{ periodo: Periodo; bimestre: Bimestre } | null>(null)
  const { confirmar, dialogo } = useConfirmar()

  if (isPending) return <Esqueleto className="h-80" />
  if (isError) return <EstadoVacio titulo="No se pudieron cargar los periodos" />

  const actual = periodos.find((p) => p.id === elegido) ?? periodos.find((p) => p.estado === 'EN_CURSO') ?? periodos[0]

  return (
    <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
      <Card>
        <Encabezado titulo="Periodos" detalle="Cada periodo es un año escolar.">
          <Button onClick={() => setFormPeriodo('nuevo')}>
            <Plus className="size-5" aria-hidden /> Nuevo periodo
          </Button>
        </Encabezado>
        <TablaDatos
          filas={periodos}
          claveFila={(p) => p.id}
          descripcion="Periodos académicos"
          vacio="Aún no hay periodos. Crea el primero."
          filaActiva={actual?.id}
          columnas={[
            {
              clave: 'nombre',
              titulo: 'Periodo',
              principal: true,
              celda: (p) => <span className="text-lg font-black">{p.nombre}</span>,
            },
            { clave: 'inicio', titulo: 'Inicio', celda: (p) => fecha(p.fecha_inicio) },
            { clave: 'fin', titulo: 'Fin', celda: (p) => fecha(p.fecha_fin) },
            {
              clave: 'estado',
              titulo: 'Estado',
              celda: (p) => (
                <Insignia tono={ESTADO_PERIODO[p.estado][0]} punto>
                  {ESTADO_PERIODO[p.estado][1]}
                </Insignia>
              ),
            },
            {
              clave: 'accion',
              titulo: 'Acción',
              celda: (p) => (
                <Button
                  variante="secundario"
                  tamano="chico"
                  onClick={() => setElegido(p.id)}
                  aria-label={`Ver el periodo ${p.nombre}`}
                >
                  Ver
                </Button>
              ),
            },
          ]}
        />
      </Card>

      {actual && (
        <Card aria-label={`Periodo ${actual.nombre}`}>
          <Encabezado
            titulo={`Bimestres de ${actual.nombre}`}
            detalle={`${fecha(actual.fecha_inicio)} – ${fecha(actual.fecha_fin)}`}
          >
            {actual.estado !== 'CERRADO' && (
              <Button variante="secundario" tamano="chico" onClick={() => setFormPeriodo(actual)}>
                <Pencil className="size-4" aria-hidden /> Editar fechas
              </Button>
            )}
          </Encabezado>

          <ul className="flex flex-col divide-y divide-borde rounded-2xl border border-borde">
            {actual.bimestres.map((b) => (
              <li key={b.numero} className="grid grid-cols-[2.5rem_minmax(0,1fr)_auto] items-start gap-x-3 p-4">
                <span className="border-r border-borde font-serif text-2xl text-texto-suave" aria-hidden>
                  {ROMANOS[b.numero - 1]}
                </span>
                <div className="flex min-w-0 flex-col gap-0.5">
                  <p className="font-extrabold">{b.nombre}</p>
                  {b.id ? (
                    <>
                      <p className="text-texto-suave">
                        {fecha(b.fecha_inicio, true)} – {fecha(b.fecha_fin, true)}
                      </p>
                      <p className="text-sm">
                        Cierre de notas: <strong>{fecha(b.fecha_cierre_notas)}</strong>
                        {b.cerrado_automatico && <span className="text-texto-suave"> (cerrado automáticamente)</span>}
                      </p>
                    </>
                  ) : (
                    <p className="text-texto-suave">Sin fechas</p>
                  )}
                  {actual.estado !== 'CERRADO' && b.estado !== 'CERRADO' && (
                    <div className="-ml-3 flex flex-wrap gap-1">
                      <Button
                        variante="fantasma"
                        tamano="chico"
                        onClick={() => setFormBimestre({ periodo: actual, bimestre: b })}
                      >
                        {b.id ? 'Editar' : 'Definir'}
                      </Button>
                      {b.id && b.situacion !== 'PENDIENTE' && (
                        <Button
                          variante="fantasma"
                          tamano="chico"
                          onClick={() =>
                            confirmar({
                              titulo: `¿Cerrar el ${b.nombre}?`,
                              texto: `Se cierra antes de su fecha de cierre (${fecha(b.fecha_cierre_notas)}). Lo que no tenga nota se calcula como 0, los promedios quedan cerrados y ya no se modifican notas, entregas ni asistencia de este bimestre.`,
                              textoConfirmar: 'Sí, cerrar',
                              peligro: true,
                              metodo: 'POST',
                              ruta: `/bimestres/${b.id}/cerrar`,
                            })
                          }
                        >
                          <Lock className="size-4" aria-hidden /> Cerrar
                        </Button>
                      )}
                    </div>
                  )}
                </div>
                <Insignia tono={SITUACION[b.situacion][0]} punto>
                  {SITUACION[b.situacion][1]}
                </Insignia>
              </li>
            ))}
          </ul>

          <p className="mt-4 flex gap-2 text-sm text-texto-suave">
            <Info className="size-5 shrink-0" aria-hidden />
            Al llegar la fecha de cierre de notas, el bimestre se cierra solo. Los docentes la ven en su pantalla y
            reciben un aviso cuando faltan pocos días.
          </p>

          <div className="mt-4 flex flex-wrap gap-2">
            {actual.estado === 'PLANIFICADO' && (
              <>
                <Button
                  onClick={() =>
                    confirmar({
                      titulo: `¿Iniciar el periodo ${actual.nombre}?`,
                      texto: 'Pasará a estar en curso. Solo puede haber un periodo en curso a la vez.',
                      textoConfirmar: 'Sí, iniciar',
                      metodo: 'PATCH',
                      ruta: `/periodos/${actual.id}/estado`,
                      datos: { estado: 'EN_CURSO' },
                    })
                  }
                >
                  <Play className="size-5" aria-hidden /> Iniciar periodo
                </Button>
                {actual.aulas === 0 && (
                  <Button
                    variante="secundario"
                    onClick={() =>
                      confirmar({
                        titulo: `¿Eliminar el periodo ${actual.nombre}?`,
                        texto: 'Se eliminan también sus bimestres. No se puede deshacer.',
                        textoConfirmar: 'Sí, eliminar',
                        peligro: true,
                        metodo: 'DELETE',
                        ruta: `/periodos/${actual.id}`,
                        despues: () => setElegido(null),
                      })
                    }
                  >
                    <Trash2 className="size-5" aria-hidden /> Eliminar
                  </Button>
                )}
              </>
            )}
            {actual.estado === 'EN_CURSO' && (
              <Button
                variante="secundario"
                onClick={() =>
                  confirmar({
                    titulo: `¿Cerrar el periodo ${actual.nombre}?`,
                    texto:
                      'Se cierran también sus bimestres abiertos. Un periodo cerrado no se vuelve a abrir ni se modifica.',
                    textoConfirmar: 'Sí, cerrar el periodo',
                    peligro: true,
                    metodo: 'PATCH',
                    ruta: `/periodos/${actual.id}/estado`,
                    datos: { estado: 'CERRADO' },
                  })
                }
              >
                <CalendarCheck className="size-5" aria-hidden /> Cerrar periodo
              </Button>
            )}
          </div>
        </Card>
      )}

      <Ventana
        abierto={formPeriodo !== null}
        titulo={formPeriodo === 'nuevo' ? 'Nuevo periodo' : `Editar el periodo ${formPeriodo?.nombre ?? ''}`}
        onCerrar={() => setFormPeriodo(null)}
        conCerrar
      >
        {formPeriodo !== null && (
          <FormPeriodo
            periodo={formPeriodo === 'nuevo' ? null : formPeriodo}
            onListo={(id) => {
              setFormPeriodo(null)
              if (id) setElegido(id)
            }}
          />
        )}
      </Ventana>

      <Ventana
        abierto={formBimestre !== null}
        titulo={formBimestre ? `${formBimestre.bimestre.nombre} de ${formBimestre.periodo.nombre}` : ''}
        onCerrar={() => setFormBimestre(null)}
        conCerrar
      >
        {formBimestre && <FormBimestre {...formBimestre} onListo={() => setFormBimestre(null)} />}
      </Ventana>

      {dialogo}
    </div>
  )
}

// Al crear se propone el año siguiente
const anioSiguiente = () => String(new Date().getFullYear() + 1)

function FormPeriodo({ periodo, onListo }: { periodo: Periodo | null; onListo: (id?: number) => void }) {
  const [anio] = useState(anioSiguiente)
  const cambio = useCambio()
  const avisos = useAvisos()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<DatosPeriodo>({
    resolver: zodResolver(esquemaPeriodo),
    defaultValues: periodo
      ? { nombre: periodo.nombre, fecha_inicio: periodo.fecha_inicio, fecha_fin: periodo.fecha_fin }
      : { nombre: anio, fecha_inicio: '', fecha_fin: '' },
  })

  const guardar = handleSubmit(async (d) => {
    try {
      const r = await cambio.mutateAsync(
        periodo
          ? { metodo: 'PUT', ruta: `/periodos/${periodo.id}`, datos: d }
          : { metodo: 'POST', ruta: '/periodos', datos: d },
      )
      avisos.exito(r.mensaje)
      onListo(r.id ?? periodo?.id)
    } catch (e) {
      const mensaje = marcarErrores(e, setError, ['nombre', 'fecha_inicio', 'fecha_fin'])
      if (mensaje) avisos.error(mensaje)
    }
  })

  return (
    <form onSubmit={guardar} noValidate className="flex flex-col gap-4">
      <Campo
        etiqueta="Año"
        inputMode="numeric"
        maxLength={4}
        filtro={(v) => v.replace(/\D/g, '')}
        error={errors.nombre?.message}
        {...register('nombre')}
      />
      <div className="grid gap-4 sm:grid-cols-2">
        <Campo etiqueta="Inicio" type="date" error={errors.fecha_inicio?.message} {...register('fecha_inicio')} />
        <Campo
          etiqueta="Fin"
          type="date"
          ayuda="Incluye el cierre de notas del IV bimestre."
          error={errors.fecha_fin?.message}
          {...register('fecha_fin')}
        />
      </div>
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variante="secundario" onClick={() => onListo()}>
          Cancelar
        </Button>
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Guardando…' : periodo ? 'Guardar cambios' : 'Crear periodo'}
        </Button>
      </div>
    </form>
  )
}

function FormBimestre({ periodo, bimestre, onListo }: { periodo: Periodo; bimestre: Bimestre; onListo: () => void }) {
  const cambio = useCambio()
  const avisos = useAvisos()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<DatosBimestre>({
    resolver: zodResolver(esquemaBimestre),
    defaultValues: {
      fecha_inicio: bimestre.fecha_inicio ?? '',
      fecha_fin: bimestre.fecha_fin ?? '',
      fecha_cierre_notas: bimestre.fecha_cierre_notas ?? '',
    },
  })

  const guardar = handleSubmit(async (d) => {
    const datos = { ...d, fecha_cierre_notas: d.fecha_cierre_notas || null }
    try {
      const r = await cambio.mutateAsync(
        bimestre.id
          ? { metodo: 'PUT', ruta: `/bimestres/${bimestre.id}`, datos }
          : { metodo: 'POST', ruta: `/periodos/${periodo.id}/bimestres`, datos: { ...datos, numero: bimestre.numero } },
      )
      avisos.exito(r.mensaje)
      onListo()
    } catch (e) {
      const mensaje = marcarErrores(e, setError, ['fecha_inicio', 'fecha_fin', 'fecha_cierre_notas'])
      if (mensaje) avisos.error(mensaje)
    }
  })

  return (
    <form onSubmit={guardar} noValidate className="flex flex-col gap-4">
      <p className="text-texto-suave">
        Dentro del periodo ({fecha(periodo.fecha_inicio)} – {fecha(periodo.fecha_fin)}) y sin cruzarse con los otros
        bimestres.
      </p>
      <div className="grid gap-4 sm:grid-cols-2">
        <Campo
          etiqueta="Inicio de clases"
          type="date"
          error={errors.fecha_inicio?.message}
          {...register('fecha_inicio')}
        />
        <Campo etiqueta="Fin de clases" type="date" error={errors.fecha_fin?.message} {...register('fecha_fin')} />
      </div>
      <Campo
        etiqueta="Cierre de notas"
        type="date"
        ayuda="Si lo dejas vacío: fin de clases + los días configurados en Colegio. Al llegar esta fecha, el bimestre se cierra solo."
        error={errors.fecha_cierre_notas?.message}
        {...register('fecha_cierre_notas')}
      />
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variante="secundario" onClick={onListo}>
          Cancelar
        </Button>
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Guardando…' : 'Guardar bimestre'}
        </Button>
      </div>
    </form>
  )
}
