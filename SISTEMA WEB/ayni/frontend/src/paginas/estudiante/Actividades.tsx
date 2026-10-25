import {
  ArrowLeft,
  CalendarDays,
  CheckCircle2,
  Clock,
  Download,
  FileText,
  Info,
  Percent,
  Send,
  Star,
  Upload,
} from 'lucide-react'
import { useRef, useState, type ReactNode } from 'react'
import { Link, useSearchParams } from 'react-router'
import { Ilustracion } from '@/components/Ilustracion'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Card, CardTitulo } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import { Selector } from '@/components/ui/selector'
import { TablaDatos } from '@/components/ui/tabla-datos'
import { TarjetaKpi } from '@/components/ui/tarjeta-kpi'
import { descargar, type ErrorApi } from '@/lib/api'
import { fechaCorta } from '@/lib/asistencia'
import {
  ESTADO_ENTREGA,
  notaSobre,
  tamano,
  useActividad,
  useCambioEvaluaciones,
  useMisActividades,
  type Actividad,
  type DetalleActividad,
} from '@/lib/evaluaciones'
import { IMG } from '@/lib/imagenes'
import { cn } from '@/lib/utils'

const FILTROS = [
  { id: 'pendientes', texto: 'Pendientes' },
  { id: 'entregadas', texto: 'Entregadas' },
  { id: 'todas', texto: 'Todas' },
] as const

const pendiente = (a: Actividad) => a.estado === 'PENDIENTE' || a.estado === 'EN_PROGRESO'

// CU-17 (mockups estudiante 08 y 09)
export default function Actividades() {
  const [parametros] = useSearchParams()
  const id = Number(parametros.get('id')) || null
  return id ? <Detalle id={id} /> : <Lista />
}

function EstadoActividad({ a }: { a: Actividad }) {
  const e = ESTADO_ENTREGA[a.estado]
  return (
    <Insignia tono={e.tono} punto>
      {e.texto}
      {a.nota !== null && ` · ${notaSobre(a.nota, a.puntaje_maximo)}`}
    </Insignia>
  )
}

function Lista() {
  const [bimestre, setBimestre] = useState<number | null>(null)
  const [filtro, setFiltro] = useState<(typeof FILTROS)[number]['id']>('pendientes')
  const [curso, setCurso] = useState('')
  const { data, isPending, isError, refetch } = useMisActividades(bimestre)

  if (isPending) return <Esqueleto className="mx-auto h-[32rem] max-w-6xl" />
  if (isError)
    return (
      <EstadoVacio
        titulo="No se pudo cargar tus actividades"
        accion={<Button onClick={() => refetch()}>Reintentar</Button>}
      />
    )
  if (!data.bimestre) return <EstadoVacio titulo="Aún no tienes matrícula en el periodo en curso" />

  const cursos = [...new Set(data.actividades.map((a) => a.curso))].sort()
  const visibles = data.actividades
    .filter((a) => !curso || a.curso === curso)
    .filter((a) =>
      filtro === 'pendientes'
        ? pendiente(a)
        : filtro === 'entregadas'
          ? a.estado === 'ENTREGADO' || a.estado === 'TARDIO'
          : true,
    )
  const conteo = {
    pendientes: data.resumen?.pendientes ?? 0,
    entregadas: data.resumen?.completadas ?? 0,
    todas: data.actividades.length,
  }

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-3xl font-black sm:text-4xl">Actividades</h1>
          <p className="mt-1 text-lg text-texto-suave">Organiza tus entregas del {data.bimestre.nombre}</p>
        </div>
        <Selector
          etiqueta="Bimestre"
          className="w-48"
          value={String(data.bimestre.id)}
          onChange={(e) => setBimestre(Number(e.target.value))}
          opciones={data.bimestres.map((b) => ({ valor: String(b.id), texto: b.nombre }))}
        />
      </header>

      <div className="grid gap-4 sm:grid-cols-3">
        <TarjetaKpi icono={<FileText />} valor={conteo.pendientes} etiqueta="Pendientes" tono="peligro" />
        <TarjetaKpi
          icono={<Clock />}
          valor={data.resumen?.por_vencer ?? 0}
          etiqueta="Por vencer (3 días)"
          tono="aviso"
        />
        <TarjetaKpi icono={<CheckCircle2 />} valor={conteo.entregadas} etiqueta="Completadas" tono="exito" />
      </div>

      {data.primera && (
        <div className="relative flex items-center gap-4 overflow-hidden rounded-2xl bg-primario-suave px-5 py-4 sm:px-6">
          <span className="grid size-12 shrink-0 place-items-center rounded-full bg-accion text-white" aria-hidden>
            <Star className="size-6" />
          </span>
          <div className="min-w-0 flex-1">
            <p className="text-xl font-black">Primero esta semana</p>
            <p className="text-lg">
              <Link to={`?id=${data.primera.id}`} className="font-semibold hover:underline">
                {data.primera.titulo}
              </Link>{' '}
              — <span className="font-bold text-accion">vence el {fechaCorta(data.primera.fecha_limite)}</span>
            </p>
          </div>
          <Ilustracion imagen={IMG.piki} sizes="120px" alt="" className="hidden h-24 w-auto sm:block" />
        </div>
      )}

      <Card className="flex flex-col gap-4">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div role="tablist" aria-label="Filtrar actividades" className="flex gap-1 border-b border-borde">
            {FILTROS.map((f) => (
              <button
                key={f.id}
                type="button"
                role="tab"
                aria-selected={filtro === f.id}
                onClick={() => setFiltro(f.id)}
                className={cn(
                  '-mb-px flex cursor-pointer items-center gap-2 border-b-3 px-3 py-2 font-bold',
                  filtro === f.id ? 'border-accion text-texto' : 'border-transparent text-texto-suave hover:text-texto',
                )}
              >
                {f.texto}
                <span className="rounded-full bg-texto/8 px-2 text-sm">{conteo[f.id]}</span>
              </button>
            ))}
          </div>
          <Selector
            etiqueta="Curso"
            className="w-56"
            value={curso}
            onChange={(e) => setCurso(e.target.value)}
            opciones={[{ valor: '', texto: 'Todos los cursos' }, ...cursos.map((c) => ({ valor: c, texto: c }))]}
          />
        </div>
        <TablaDatos
          key={`${filtro}-${curso}`}
          filas={visibles}
          claveFila={(a) => a.id}
          descripcion="Mis actividades"
          vacio={filtro === 'pendientes' ? '¡No tienes actividades pendientes!' : 'No hay actividades con ese filtro.'}
          columnas={[
            {
              clave: 'titulo',
              titulo: 'Actividad',
              principal: true,
              celda: (a) => <span className="font-bold">{a.titulo}</span>,
            },
            { clave: 'curso', titulo: 'Curso', celda: (a) => a.curso },
            {
              clave: 'limite',
              titulo: 'Fecha de entrega',
              celda: (a) => (
                <span className={cn(pendiente(a) && 'font-bold text-accion')}>{fechaCorta(a.fecha_limite)}</span>
              ),
            },
            { clave: 'estado', titulo: 'Estado', celda: (a) => <EstadoActividad a={a} /> },
            {
              clave: 'accion',
              titulo: 'Acciones',
              celda: (a) => (
                <Button asChild variante={pendiente(a) ? 'principal' : 'secundario'} tamano="chico">
                  <Link to={`?id=${a.id}`} aria-label={`Ver ${a.titulo}`}>
                    {a.estado === 'EN_PROGRESO' ? 'Continuar' : pendiente(a) ? 'Ver actividad' : 'Ver detalle'}
                  </Link>
                </Button>
              ),
            },
          ]}
        />
      </Card>
    </div>
  )
}

function Detalle({ id }: { id: number }) {
  const { data, isPending, isError, error } = useActividad(id)
  if (isPending) return <Esqueleto className="mx-auto h-[32rem] max-w-6xl" />
  if (isError)
    return (
      <EstadoVacio
        titulo="No se encontró la actividad"
        descripcion={error.message}
        accion={
          <Button asChild>
            <Link to="/estudiante/actividades">Volver a Actividades</Link>
          </Button>
        }
      />
    )
  return <VistaDetalle a={data} />
}

function VistaDetalle({ a }: { a: DetalleActividad }) {
  const avisos = useAvisos()
  const cambio = useCambioEvaluaciones()
  const entrada = useRef<HTMLInputElement>(null)
  const [archivo, setArchivo] = useState<File | null>(null)
  const [error, setError] = useState<string | null>(null)
  const yaTiene = Boolean(a.entrega?.archivo)

  const elegir = (f: File | undefined) => {
    setError(null)
    if (!f) return
    if (!f.name.toLowerCase().endsWith('.pdf')) {
      setError('El archivo debe ser un PDF.')
      return
    }
    setArchivo(f)
  }

  const enviar = async (accion: 'BORRADOR' | 'ENTREGAR') => {
    if (!archivo && !yaTiene) {
      setError('Adjunta tu archivo PDF.')
      return
    }
    const datos = new FormData()
    if (archivo) datos.append('archivo', archivo)
    datos.append('accion', accion)
    try {
      const r = await cambio.mutateAsync({ metodo: 'SUBIR', ruta: `/estudiante/actividades/${a.id}/entrega`, datos })
      avisos.exito(r.mensaje)
      setArchivo(null)
      if (entrada.current) entrada.current.value = ''
    } catch (e) {
      const err = e as ErrorApi
      setError(err.errores?.archivo?.[0] ?? err.message)
    }
  }

  const bajar = (archivoId: number, nombre: string) =>
    descargar(`/archivos/${archivoId}`, nombre).catch((e) => avisos.error((e as ErrorApi).message))

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6">
      <div>
        <Link
          to="/estudiante/actividades"
          className="inline-flex items-center gap-2 font-bold text-accion hover:underline"
        >
          <ArrowLeft className="size-4" aria-hidden /> Volver a Actividades
        </Link>
        <h1 className="mt-2 text-3xl font-black sm:text-4xl">{a.titulo}</h1>
        <p className="mt-1 text-lg text-texto-suave">
          {a.curso} · Docente: {a.docente} · {a.bimestre.nombre}
        </p>
      </div>

      <Card className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Dato icono={<CalendarDays />} etiqueta="Asignada" valor={fechaCorta(a.fecha_asignacion)} />
        <Dato
          icono={<Clock />}
          etiqueta="Vence"
          valor={a.requiere_entrega ? `${fechaCorta(a.fecha_limite)} · 11:59 p. m.` : fechaCorta(a.fecha_limite)}
        />
        <Dato icono={<Percent />} etiqueta="Peso" valor={`${a.peso_porcentaje} % del bimestre`} />
        <Dato icono={<Info />} etiqueta="Estado" valor={<EstadoActividad a={a} />} />
      </Card>

      <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
        <div className="flex flex-col gap-6">
          <Card>
            <CardTitulo>Indicaciones</CardTitulo>
            {a.indicaciones ? (
              <ol className="mt-4 flex flex-col gap-3">
                {a.indicaciones
                  .split('\n')
                  .filter(Boolean)
                  .map((linea, i) => (
                    <li key={i} className="flex gap-3">
                      <span
                        className="grid size-7 shrink-0 place-items-center rounded-full bg-primario-suave text-sm font-black"
                        aria-hidden
                      >
                        {i + 1}
                      </span>
                      <span>{linea}</span>
                    </li>
                  ))}
              </ol>
            ) : (
              <p className="mt-3 text-texto-suave">Tu docente no dejó indicaciones escritas.</p>
            )}
            {a.requiere_entrega && (
              <p className="mt-4 flex items-center gap-2 rounded-xl bg-aviso/10 px-4 py-3">
                <Info className="size-5 shrink-0 text-aviso" aria-hidden />
                <span>
                  <strong>Formato:</strong> PDF · Tamaño máximo: {a.limite_archivo}
                </span>
              </p>
            )}
          </Card>
          <Card>
            <CardTitulo>Material de apoyo</CardTitulo>
            {a.material.length === 0 ? (
              <p className="mt-3 text-texto-suave">Esta actividad no tiene material de apoyo.</p>
            ) : (
              <ul className="mt-4 flex flex-col gap-2">
                {a.material.map((m) => (
                  <li
                    key={m.id}
                    className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-borde px-4 py-3"
                  >
                    <span className="min-w-0">
                      <span className="block truncate font-bold">{m.nombre}</span>
                      <span className="text-sm text-texto-suave">{tamano(m.tamano)}</span>
                    </span>
                    <Button
                      variante="secundario"
                      tamano="chico"
                      onClick={() => bajar(m.id, m.nombre)}
                      aria-label={`Descargar ${m.nombre}`}
                    >
                      <Download className="size-4" aria-hidden /> Descargar
                    </Button>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>

        <div className="flex flex-col gap-6">
          <Card className="flex flex-col gap-4" aria-labelledby="mi-entrega">
            <CardTitulo id="mi-entrega">Mi entrega</CardTitulo>
            {a.entrega?.archivo && (
              <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-borde px-4 py-3">
                <span className="min-w-0">
                  <span className="block truncate font-bold">{a.entrega.archivo.nombre}</span>
                  <span className="text-sm text-texto-suave">
                    {a.entrega.estado === 'ENTREGADA'
                      ? `Entregado el ${fechaCorta(a.entrega.entregada_en!.slice(0, 10))}`
                      : 'Borrador guardado'}
                  </span>
                </span>
                <Button
                  variante="fantasma"
                  tamano="chico"
                  onClick={() => bajar(a.entrega!.archivo!.id, a.entrega!.archivo!.nombre)}
                  aria-label="Descargar mi entrega"
                >
                  <Download className="size-4" aria-hidden />
                </Button>
              </div>
            )}
            {a.puede_entregar ? (
              <>
                <div
                  className="flex flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-borde px-4 py-6 text-center"
                  onDragOver={(e) => e.preventDefault()}
                  onDrop={(e) => {
                    e.preventDefault()
                    elegir(e.dataTransfer.files[0])
                  }}
                >
                  <Upload className="size-8 text-accion" aria-hidden />
                  <p className="font-bold">{archivo ? archivo.name : 'Arrastra tu archivo aquí'}</p>
                  <p className="text-sm text-texto-suave">
                    {archivo ? tamano(archivo.size) : 'o selecciónalo desde tu dispositivo'}
                  </p>
                  <input
                    ref={entrada}
                    id="archivo-entrega"
                    type="file"
                    accept="application/pdf,.pdf"
                    className="sr-only"
                    onChange={(e) => elegir(e.target.files?.[0])}
                  />
                  <Button variante="secundario" tamano="chico" onClick={() => entrada.current?.click()}>
                    {archivo || yaTiene ? 'Cambiar archivo' : 'Seleccionar archivo'}
                  </Button>
                  <label htmlFor="archivo-entrega" className="sr-only">
                    Archivo PDF de la entrega
                  </label>
                </div>
                {error && (
                  <p role="alert" className="text-sm font-semibold text-peligro">
                    {error}
                  </p>
                )}
                <p className="text-sm text-texto-suave">{a.aviso}</p>
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                  <Button onClick={() => enviar('ENTREGAR')} disabled={cambio.isPending} className="flex-1">
                    <Send className="size-5" aria-hidden /> {cambio.isPending ? 'Enviando…' : 'Entregar actividad'}
                  </Button>
                  <Button variante="fantasma" onClick={() => enviar('BORRADOR')} disabled={cambio.isPending}>
                    Guardar borrador
                  </Button>
                </div>
              </>
            ) : (
              <p className="rounded-xl bg-texto/6 px-4 py-3" role="note">
                {a.aviso}
              </p>
            )}
          </Card>
          <Card>
            <CardTitulo>Historial</CardTitulo>
            <ol className="mt-4 flex flex-col gap-3 border-l-2 border-borde pl-4">
              {a.historial.map((h, i) => (
                <li key={i} className="relative">
                  <span className="absolute top-1.5 -left-[1.4rem] size-3 rounded-full bg-primario" aria-hidden />
                  <span className="font-bold">{fechaCorta(h.fecha.slice(0, 10))}</span> · {h.texto}
                </li>
              ))}
            </ol>
          </Card>
        </div>
      </div>
    </div>
  )
}

function Dato({ icono, etiqueta, valor }: { icono: ReactNode; etiqueta: string; valor: ReactNode }) {
  return (
    <div className="flex items-center gap-3">
      <span
        className="grid size-11 shrink-0 place-items-center rounded-full bg-primario-suave text-primario-oscuro [&_svg]:size-5"
        aria-hidden
      >
        {icono}
      </span>
      <div className="min-w-0">
        <p className="text-sm text-texto-suave">{etiqueta}</p>
        <div className="font-bold">{valor}</div>
      </div>
    </div>
  )
}
