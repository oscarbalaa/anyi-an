import { zodResolver } from '@hookform/resolvers/zod'
import { FilePlus2, History, Info, Search, X } from 'lucide-react'
import { useCallback, useEffect, useId, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { useSearchParams } from 'react-router'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { Card } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import { Selector } from '@/components/ui/selector'
import { TablaDatos } from '@/components/ui/tabla-datos'
import type { ErrorApi } from '@/lib/api'
import { fechaCorta, hoyIso } from '@/lib/asistencia'
import {
  GRAVEDADES,
  useEstudiantesIncidencia,
  useIncidencias,
  useRegistrarIncidencia,
  type EstudianteIncidencia,
  type Gravedad,
  type ListaIncidencias,
  type RolIncidencias,
} from '@/lib/incidencias'
import { esquemaIncidencia, type DatosIncidencia } from '@/lib/validaciones'
import { Buscador } from '../admin/estudiantes/Buscador'

const GRAVEDAD = Object.fromEntries(GRAVEDADES.map((g) => [g.valor, g])) as Record<
  Gravedad,
  (typeof GRAVEDADES)[number]
>

const SUBTITULO: Record<RolIncidencias, string> = {
  admin: 'Hechos relevantes registrados por docentes y coordinación.',
  docente: 'Hechos relevantes de los estudiantes de tus aulas.',
}

// CU-22 (mockup admin 18): el docente ve a sus estudiantes; el administrador, a todos
export default function Incidencias({ rol }: { rol: RolIncidencias }) {
  const [parametros, setParametros] = useSearchParams()
  const matricula = parametros.get('matricula') ?? ''
  const [buscar, setBuscar] = useState('')
  const [tipo, setTipo] = useState('')
  const [gravedad, setGravedad] = useState('')
  const [aula, setAula] = useState('')
  const [pagina, setPagina] = useState(1)
  const { data, isPending, isError, refetch } = useIncidencias(rol, {
    buscar,
    tipo_id: tipo,
    gravedad,
    aula_id: aula,
    matricula_id: matricula,
    pagina,
  })

  const alBuscar = useCallback((t: string) => {
    setBuscar(t)
    setPagina(1)
  }, [])
  const filtro = (cambiar: (v: string) => void) => (e: { target: { value: string } }) => {
    cambiar(e.target.value)
    setPagina(1)
  }
  const historial = (id: number | null) => {
    const p = new URLSearchParams(parametros)
    if (id) p.set('matricula', String(id))
    else p.delete('matricula')
    setParametros(p, { replace: true })
    setPagina(1)
  }
  // Con ?matricula= se ve el historial de un estudiante (lo abre la notificación al tutor)
  const primera = data?.datos[0]
  const delHistorial = matricula && primera?.estudiante.matricula_id === Number(matricula) ? primera : undefined

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-3xl font-black sm:text-4xl">Incidencias</h1>
          <p className="mt-1 text-lg text-texto-suave">{SUBTITULO[rol]}</p>
        </div>
        <Button asChild variante="secundario" className="xl:hidden">
          <a href="#registrar-incidencia">
            <FilePlus2 className="size-5" aria-hidden /> Registrar incidencia
          </a>
        </Button>
      </header>

      <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_26rem]">
        <Card className="flex flex-col gap-4">
          {matricula && (
            <p
              className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-info/8 px-4 py-3"
              role="status"
            >
              <span className="flex items-center gap-2 font-bold">
                <History className="size-5 text-info" aria-hidden />
                Historial de {delHistorial?.estudiante.nombre ?? 'un estudiante'}
              </span>
              <Button variante="fantasma" tamano="chico" onClick={() => historial(null)}>
                <X className="size-4" aria-hidden /> Ver todas
              </Button>
            </p>
          )}
          <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
            <div className="sm:col-span-2 2xl:col-span-1">
              <Buscador placeholder="Nombre o código del estudiante" onBuscar={alBuscar} />
            </div>
            <Selector
              etiqueta="Tipo"
              value={tipo}
              onChange={filtro(setTipo)}
              opciones={[
                { valor: '', texto: 'Todos' },
                ...(data?.tipos ?? []).map((t) => ({ valor: String(t.id), texto: t.nombre })),
              ]}
            />
            <Selector
              etiqueta="Gravedad"
              value={gravedad}
              onChange={filtro(setGravedad)}
              opciones={[{ valor: '', texto: 'Todas' }, ...GRAVEDADES.map((g) => ({ valor: g.valor, texto: g.texto }))]}
            />
            {(data?.aulas.length ?? 0) > 1 && (
              <Selector
                etiqueta="Aula"
                value={aula}
                onChange={filtro(setAula)}
                className="sm:col-span-2 2xl:col-span-1"
                opciones={[
                  { valor: '', texto: 'Todas' },
                  ...(data?.aulas ?? []).map((a) => ({ valor: String(a.id), texto: a.nombre })),
                ]}
              />
            )}
          </div>
          {data && <Resumen resumen={data.resumen} />}
          {isPending ? (
            <Esqueleto className="h-64" />
          ) : isError ? (
            <EstadoVacio
              titulo="No se pudo cargar las incidencias"
              accion={<Button onClick={() => refetch()}>Reintentar</Button>}
            />
          ) : (
            <TablaDatos
              filas={data.datos}
              claveFila={(i) => i.id}
              descripcion="Incidencias"
              vacio={
                buscar || tipo || gravedad || aula
                  ? 'Ninguna incidencia coincide con los filtros.'
                  : 'Aún no hay incidencias registradas.'
              }
              paginacion={{ pagina: data.pagina, total: data.total, porPagina: data.por_pagina, onCambiar: setPagina }}
              columnas={[
                {
                  clave: 'estudiante',
                  titulo: 'Estudiante',
                  principal: true,
                  celda: (i) => (
                    <span className="block leading-tight">
                      <button
                        type="button"
                        onClick={() => historial(i.estudiante.matricula_id)}
                        className="cursor-pointer text-left font-bold hover:text-accion hover:underline"
                        aria-label={`Ver historial de ${i.estudiante.nombre}`}
                      >
                        {i.estudiante.nombre}
                      </button>
                      <span className="block text-sm text-texto-suave">
                        {i.aula} · {i.registrado_por}
                      </span>
                    </span>
                  ),
                },
                {
                  clave: 'tipo',
                  titulo: 'Tipo',
                  celda: (i) => (
                    <span className="block max-w-xs leading-tight">
                      <span className="block font-semibold">{i.tipo}</span>
                      <span className="line-clamp-2 text-sm text-texto-suave" title={i.descripcion}>
                        {i.descripcion}
                      </span>
                    </span>
                  ),
                },
                {
                  clave: 'gravedad',
                  titulo: 'Gravedad',
                  celda: (i) => (
                    <Insignia tono={GRAVEDAD[i.gravedad].tono} punto>
                      {GRAVEDAD[i.gravedad].texto}
                    </Insignia>
                  ),
                },
                {
                  clave: 'fecha',
                  titulo: 'Fecha',
                  celda: (i) => <span className="whitespace-nowrap">{fechaCorta(i.fecha)}</span>,
                },
              ]}
            />
          )}
        </Card>

        <Card id="registrar-incidencia" aria-labelledby="titulo-registrar" className="scroll-mt-20">
          <h2 id="titulo-registrar" className="mb-4 text-2xl font-black">
            Registrar incidencia
          </h2>
          {data ? (
            <FormIncidencia
              key={delHistorial ? matricula : 'nueva'}
              rol={rol}
              tipos={data.tipos}
              inicial={delHistorial ? { ...delHistorial.estudiante, aula: delHistorial.aula } : null}
            />
          ) : (
            <Esqueleto className="h-96" />
          )}
        </Card>
      </div>
    </div>
  )
}

function Resumen({ resumen }: { resumen: ListaIncidencias['resumen'] }) {
  return (
    <ul className="flex flex-wrap gap-2" aria-label="Incidencias por gravedad">
      {GRAVEDADES.map((g) => (
        <li key={g.valor}>
          <Insignia tono={g.tono}>
            {resumen[g.valor]} {g.texto.toLowerCase()}
            {resumen[g.valor] === 1 ? '' : 's'}
          </Insignia>
        </li>
      ))}
    </ul>
  )
}

function FormIncidencia({
  rol,
  tipos,
  inicial,
}: {
  rol: RolIncidencias
  tipos: ListaIncidencias['tipos']
  inicial: EstudianteIncidencia | null
}) {
  const registrar = useRegistrarIncidencia(rol)
  const avisos = useAvisos()
  const idDescripcion = useId()
  const [elegido, setElegido] = useState<EstudianteIncidencia | null>(inicial)
  const valoresIniciales = (): DatosIncidencia => ({
    matricula_id: inicial ? String(inicial.matricula_id) : '',
    tipo_incidencia_id: String(tipos[0]?.id ?? ''),
    gravedad: 'LEVE',
    fecha: hoyIso(),
    descripcion: '',
  })
  const {
    register,
    handleSubmit,
    setError,
    setValue,
    reset,
    control,
    formState: { errors, isSubmitting },
  } = useForm<DatosIncidencia>({ resolver: zodResolver(esquemaIncidencia), defaultValues: valoresIniciales() })
  const descripcion = useWatch({ control, name: 'descripcion' }) ?? ''

  const elegir = (e: EstudianteIncidencia | null) => {
    setElegido(e)
    setValue('matricula_id', e ? String(e.matricula_id) : '', { shouldValidate: Boolean(e) })
  }

  const guardar = handleSubmit(async (d) => {
    try {
      const r = await registrar.mutateAsync({
        matricula_id: Number(d.matricula_id),
        tipo_incidencia_id: Number(d.tipo_incidencia_id),
        gravedad: d.gravedad as Gravedad,
        fecha: d.fecha,
        descripcion: d.descripcion,
      })
      avisos.exito(r.mensaje)
      reset(valoresIniciales())
      setElegido(inicial)
    } catch (e) {
      const error = e as ErrorApi
      const campos = Object.entries(error.errores ?? {})
      for (const [campo, m] of campos) setError(campo as keyof DatosIncidencia, { message: m[0] })
      if (!campos.length) avisos.error(error.message)
    }
  })

  return (
    <form onSubmit={guardar} noValidate className="flex flex-col gap-4">
      <ElegirEstudiante rol={rol} elegido={elegido} onElegir={elegir} error={errors.matricula_id?.message} />
      <div className="grid gap-4 sm:grid-cols-2">
        <Selector
          etiqueta="Tipo"
          opciones={tipos.map((t) => ({ valor: String(t.id), texto: t.nombre }))}
          {...register('tipo_incidencia_id')}
        />
        <Selector
          etiqueta="Gravedad"
          opciones={GRAVEDADES.map((g) => ({ valor: g.valor, texto: g.texto }))}
          {...register('gravedad')}
        />
      </div>
      {(errors.tipo_incidencia_id || errors.gravedad) && (
        <p role="alert" className="text-sm font-semibold text-peligro">
          {errors.tipo_incidencia_id?.message ?? errors.gravedad?.message}
        </p>
      )}
      <Campo etiqueta="Fecha" type="date" max={hoyIso()} error={errors.fecha?.message} {...register('fecha')} />
      <div className="flex flex-col gap-1.5">
        <label htmlFor={idDescripcion} className="font-bold">
          Descripción
        </label>
        <textarea
          id={idDescripcion}
          rows={4}
          maxLength={500}
          placeholder="Describe lo ocurrido con hechos concretos…"
          aria-invalid={Boolean(errors.descripcion)}
          aria-describedby={`${idDescripcion}-ayuda`}
          className="w-full rounded-xl border border-borde bg-superficie px-4 py-3 outline-none placeholder:text-texto-suave/70 focus:border-accion focus:ring-3 focus:ring-primario/20 aria-invalid:border-peligro"
          {...register('descripcion')}
        />
        <p id={`${idDescripcion}-ayuda`} className="flex justify-between gap-2 text-sm text-texto-suave">
          {errors.descripcion ? (
            <span role="alert" className="font-semibold text-peligro">
              {errors.descripcion.message}
            </span>
          ) : (
            <span>De 10 a 500 caracteres.</span>
          )}
          <span aria-hidden>{descripcion.length}/500</span>
        </p>
      </div>
      <p className="flex gap-2 text-sm text-texto-suave">
        <Info className="mt-0.5 size-4 shrink-0" aria-hidden />
        Solo el administrador y los docentes del estudiante pueden verla. El estudiante y su tutor reciben un aviso; si
        es grave, también la administración.
      </p>
      <Button type="submit" disabled={isSubmitting} className="w-full">
        <FilePlus2 className="size-5" aria-hidden /> {isSubmitting ? 'Guardando…' : 'Guardar incidencia'}
      </Button>
    </form>
  )
}

/** Busca entre los estudiantes con matrícula activa que el usuario puede ver */
function ElegirEstudiante({
  rol,
  elegido,
  onElegir,
  error,
}: {
  rol: RolIncidencias
  elegido: EstudianteIncidencia | null
  onElegir: (e: EstudianteIncidencia | null) => void
  error?: string
}) {
  const id = useId()
  const [texto, setTexto] = useState('')
  const [buscar, setBuscar] = useState('')
  useEffect(() => {
    const espera = window.setTimeout(() => setBuscar(texto.trim()), 300)
    return () => window.clearTimeout(espera)
  }, [texto])
  const { data, isFetching } = useEstudiantesIncidencia(rol, buscar)

  if (elegido)
    return (
      <div className="flex flex-col gap-1.5">
        <span className="font-bold">Estudiante</span>
        <div className="flex items-center justify-between gap-2 rounded-xl border border-accion/40 bg-primario-suave/40 px-4 py-2.5">
          <span className="min-w-0 leading-tight">
            <span className="block truncate font-bold" data-testid="estudiante-elegido">
              {elegido.nombre}
            </span>
            <span className="text-sm text-texto-suave">
              {elegido.codigo} · {elegido.aula}
            </span>
          </span>
          <Button variante="fantasma" tamano="chico" onClick={() => onElegir(null)}>
            Cambiar
          </Button>
        </div>
      </div>
    )

  const lista = data?.estudiantes ?? []
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="font-bold">
        Estudiante
      </label>
      <div className="relative">
        <Search
          className="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-texto-suave"
          aria-hidden
        />
        <input
          id={id}
          type="search"
          value={texto}
          maxLength={100}
          placeholder="Buscar por nombre o código…"
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? `${id}-error` : undefined}
          onChange={(e) => setTexto(e.target.value)}
          className="h-11 w-full rounded-xl border border-borde bg-superficie pr-3 pl-10 outline-none focus:border-accion focus:ring-3 focus:ring-primario/20 aria-invalid:border-peligro"
        />
      </div>
      {error && (
        <p id={`${id}-error`} role="alert" className="text-sm font-semibold text-peligro">
          {error}
        </p>
      )}
      <ul
        className="max-h-56 overflow-y-auto rounded-xl border border-borde"
        aria-label="Estudiantes encontrados"
        aria-busy={isFetching}
      >
        {lista.length === 0 ? (
          <li className="px-4 py-3 text-texto-suave">
            {data ? 'Ningún estudiante coincide.' : 'Buscando estudiantes…'}
          </li>
        ) : (
          lista.map((e) => (
            <li key={e.matricula_id} className="border-b border-borde last:border-b-0">
              <button
                type="button"
                onClick={() => onElegir(e)}
                className="flex w-full cursor-pointer items-center justify-between gap-2 px-4 py-2 text-left hover:bg-fondo"
                aria-label={`Elegir a ${e.nombre}`}
              >
                <span className="min-w-0 truncate font-semibold">{e.nombre}</span>
                <span className="shrink-0 text-sm text-texto-suave">{e.aula}</span>
              </button>
            </li>
          ))
        )}
      </ul>
    </div>
  )
}
