import { zodResolver } from '@hookform/resolvers/zod'
import { Plus } from 'lucide-react'
import { useCallback, useId, useState, type FormEvent } from 'react'
import { useForm } from 'react-hook-form'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { Card } from '@/components/ui/card'
import { Ventana } from '@/components/ui/dialogo'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Selector } from '@/components/ui/selector'
import { TablaDatos } from '@/components/ui/tabla-datos'
import type { ErrorApi } from '@/lib/api'
import { useOpciones } from '@/lib/estructura'
import {
  fechaCorta,
  useCambioPersonas,
  useCandidatos,
  useMatriculas,
  type AulaConCupos,
  type FilaMatricula,
} from '@/lib/personas'
import { esquemaMotivo } from '@/lib/validaciones'
import { Buscador } from './Buscador'
import { EstadoMatriculaInsignia } from './Estados'

type Cierre = { matricula: FilaMatricula; tipo: 'retirar' | 'anular' }

const hoy = () => new Date().toLocaleDateString('en-CA')

// CU-12 (mockup admin 11)
export function PestanaMatriculas() {
  const { data: opciones } = useOpciones()
  const [periodoElegido, setPeriodo] = useState<number | null>(null)
  const [aula, setAula] = useState('')
  const [estado, setEstado] = useState('ACTIVA')
  const [buscar, setBuscar] = useState('')
  const [pagina, setPagina] = useState(1)
  const [nueva, setNueva] = useState(false)
  const [cierre, setCierre] = useState<Cierre | null>(null)

  const periodo = periodoElegido ?? opciones?.periodo_id ?? opciones?.periodos[0]?.id ?? null
  const cerrado = opciones?.periodos.find((p) => p.id === periodo)?.estado === 'CERRADO'
  const { data, isPending, isError, refetch } = useMatriculas({
    periodo_id: periodo,
    aula_id: aula,
    estado,
    buscar,
    pagina,
  })
  const alBuscar = useCallback((t: string) => {
    setBuscar(t)
    setPagina(1)
  }, [])

  if (!opciones) return <Esqueleto className="h-96" />
  if (!opciones.periodos.length)
    return (
      <EstadoVacio
        titulo="Aún no hay periodos"
        descripcion="Crea un periodo en Estructura académica para matricular."
      />
    )

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-texto-suave">Un estudiante tiene una sola matrícula activa por periodo (RN-04).</p>
        <Button onClick={() => setNueva(true)} disabled={cerrado}>
          <Plus className="size-5" aria-hidden /> Nueva matrícula
        </Button>
      </div>

      {data && data.aulas.length > 0 && <Cupos aulas={data.aulas} />}

      <Card className="flex flex-col gap-4">
        <div className="grid gap-4 md:grid-cols-4">
          <Selector
            etiqueta="Periodo"
            value={String(periodo ?? '')}
            onChange={(e) => {
              setPeriodo(Number(e.target.value))
              setAula('')
              setPagina(1)
            }}
            opciones={opciones.periodos.map((p) => ({ valor: String(p.id), texto: p.nombre }))}
          />
          <Selector
            etiqueta="Aula"
            value={aula}
            onChange={(e) => {
              setAula(e.target.value)
              setPagina(1)
            }}
            opciones={[
              { valor: '', texto: 'Todas' },
              ...(data?.aulas ?? []).map((a) => ({ valor: String(a.id), texto: a.nombre })),
            ]}
          />
          <Selector
            etiqueta="Estado"
            value={estado}
            onChange={(e) => {
              setEstado(e.target.value)
              setPagina(1)
            }}
            opciones={[
              { valor: 'ACTIVA', texto: 'Activas' },
              { valor: 'RETIRADA', texto: 'Retiradas' },
              { valor: 'ANULADA', texto: 'Anuladas' },
              { valor: '', texto: 'Todas' },
            ]}
          />
          <Buscador placeholder="Nombre o código" onBuscar={alBuscar} />
        </div>
        {isPending ? (
          <Esqueleto className="h-64" />
        ) : isError ? (
          <EstadoVacio
            titulo="No se pudo cargar la lista"
            accion={<Button onClick={() => refetch()}>Reintentar</Button>}
          />
        ) : (
          <TablaDatos
            filas={data.datos}
            claveFila={(m) => m.id}
            descripcion="Matrículas"
            vacio="No hay matrículas con esos filtros."
            paginacion={{ pagina: data.pagina, total: data.total, porPagina: data.por_pagina, onCambiar: setPagina }}
            columnas={[
              {
                clave: 'estudiante',
                titulo: 'Estudiante',
                principal: true,
                celda: (m) => (
                  <span className="block leading-tight">
                    <span className="block font-bold">{m.estudiante.nombre}</span>
                    <span className="text-sm text-texto-suave">{m.estudiante.codigo}</span>
                  </span>
                ),
              },
              { clave: 'aula', titulo: 'Aula', celda: (m) => m.aula },
              { clave: 'fecha', titulo: 'Fecha', celda: (m) => fechaCorta(m.fecha) },
              {
                clave: 'estado',
                titulo: 'Estado',
                celda: (m) => (
                  <span className="flex flex-col items-start gap-1">
                    <EstadoMatriculaInsignia estado={m.estado} />
                    {m.motivo && <span className="text-sm text-texto-suave">{m.motivo}</span>}
                  </span>
                ),
              },
              {
                clave: 'accion',
                titulo: 'Acción',
                celda: (m) =>
                  m.estado === 'ACTIVA' ? (
                    <span className="flex flex-wrap gap-2">
                      <Button
                        variante="secundario"
                        tamano="chico"
                        onClick={() => setCierre({ matricula: m, tipo: 'retirar' })}
                        aria-label={`Retirar a ${m.estudiante.nombre}`}
                      >
                        Retirar
                      </Button>
                      <Button
                        variante="fantasma"
                        tamano="chico"
                        onClick={() => setCierre({ matricula: m, tipo: 'anular' })}
                        aria-label={`Anular la matrícula de ${m.estudiante.nombre}`}
                      >
                        Anular
                      </Button>
                    </span>
                  ) : (
                    <span className="text-texto-suave">—</span>
                  ),
              },
            ]}
          />
        )}
      </Card>

      <Ventana
        abierto={nueva}
        titulo="Nueva matrícula"
        onCerrar={() => setNueva(false)}
        conCerrar
        className="w-[min(94vw,32rem)]"
      >
        {nueva && periodo !== null && (
          <FormMatricula periodoId={periodo} aulas={data?.aulas ?? []} onListo={() => setNueva(false)} />
        )}
      </Ventana>

      <Ventana
        abierto={cierre !== null}
        titulo={cierre?.tipo === 'anular' ? 'Anular matrícula' : 'Retirar matrícula'}
        onCerrar={() => setCierre(null)}
        conCerrar
      >
        {cierre && <FormCierre key={cierre.matricula.id} {...cierre} onListo={() => setCierre(null)} />}
      </Ventana>
    </div>
  )
}

/** Ocupación de cada aula (RN-04: no se pasa de la capacidad) */
function Cupos({ aulas }: { aulas: AulaConCupos[] }) {
  return (
    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6" aria-label="Cupos por aula">
      {aulas.map((a) => {
        const lleno = a.matriculados >= a.capacidad
        return (
          <li key={a.id} className="rounded-xl border border-borde bg-superficie p-3">
            <p className="truncate font-bold" title={a.nombre}>
              {a.nombre}
            </p>
            <p className={lleno ? 'font-bold text-peligro' : 'text-texto-suave'}>
              {a.matriculados} / {a.capacidad} {lleno && '· lleno'}
            </p>
          </li>
        )
      })}
    </ul>
  )
}

function FormMatricula({
  periodoId,
  aulas,
  onListo,
}: {
  periodoId: number
  aulas: AulaConCupos[]
  onListo: () => void
}) {
  const [buscar, setBuscar] = useState('')
  const [estudiante, setEstudiante] = useState('')
  const [aula, setAula] = useState('')
  const [fecha, setFecha] = useState(hoy)
  const [errores, setErrores] = useState<Record<string, string>>({})
  const { data: candidatos } = useCandidatos(periodoId, buscar)
  const cambio = useCambioPersonas()
  const avisos = useAvisos()
  const idLista = useId()

  const guardar = async (e: FormEvent) => {
    e.preventDefault()
    const faltan: Record<string, string> = {}
    if (!estudiante) faltan.estudiante_id = 'Elige un estudiante.'
    if (!aula) faltan.aula_id = 'Elige un aula.'
    if (!fecha) faltan.fecha = 'Ingresa una fecha de matrícula válida.'
    else if (fecha > hoy()) faltan.fecha = 'La fecha de matrícula no puede ser futura.'
    setErrores(faltan)
    if (Object.keys(faltan).length) return
    try {
      const r = await cambio.mutateAsync({
        metodo: 'POST',
        ruta: '/matriculas',
        datos: { estudiante_id: Number(estudiante), aula_id: Number(aula), fecha },
      })
      avisos.exito(r.mensaje)
      onListo()
    } catch (err) {
      const error = err as ErrorApi
      const porCampo = Object.fromEntries(Object.entries(error.errores ?? {}).map(([c, m]) => [c, m[0]]))
      if (Object.keys(porCampo).length) setErrores(porCampo)
      else avisos.error(error.message)
    }
  }

  const error = (c: string) =>
    errores[c] && (
      <p role="alert" className="text-sm font-semibold text-peligro">
        {errores[c]}
      </p>
    )

  return (
    <form onSubmit={guardar} noValidate className="flex flex-col gap-4">
      <Buscador placeholder="Nombre, código o documento" onBuscar={setBuscar} />
      <div className="flex flex-col gap-1.5">
        <Selector
          etiqueta="Estudiante sin matrícula en el periodo"
          value={estudiante}
          onChange={(e) => setEstudiante(e.target.value)}
          aria-describedby={idLista}
          opciones={[
            { valor: '', texto: candidatos?.length ? 'Elige…' : 'Ningún estudiante disponible' },
            ...(candidatos ?? []).map((c) => ({ valor: String(c.id), texto: `${c.nombre} (${c.codigo})` })),
          ]}
        />
        <p id={idLista} className="text-sm text-texto-suave">
          Solo aparecen estudiantes activos que aún no están matriculados.
        </p>
        {error('estudiante_id')}
      </div>
      <div className="flex flex-col gap-1.5">
        <Selector
          etiqueta="Aula"
          value={aula}
          onChange={(e) => setAula(e.target.value)}
          opciones={[
            { valor: '', texto: 'Elige…' },
            ...aulas.map((a) => ({
              valor: String(a.id),
              texto: `${a.nombre} (${a.capacidad - a.matriculados} cupos)`,
            })),
          ]}
        />
        {error('aula_id')}
      </div>
      <Campo
        etiqueta="Fecha de matrícula"
        type="date"
        max={hoy()}
        value={fecha}
        onChange={(e) => setFecha(e.target.value)}
        error={errores.fecha}
      />
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variante="secundario" onClick={onListo}>
          Cancelar
        </Button>
        <Button type="submit" disabled={cambio.isPending}>
          {cambio.isPending ? 'Guardando…' : 'Matricular'}
        </Button>
      </div>
    </form>
  )
}

function FormCierre({ matricula, tipo, onListo }: Cierre & { onListo: () => void }) {
  const cambio = useCambioPersonas()
  const avisos = useAvisos()
  const idMotivo = useId()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<{ motivo: string }>({ resolver: zodResolver(esquemaMotivo), defaultValues: { motivo: '' } })

  const guardar = handleSubmit(async (d) => {
    try {
      const r = await cambio.mutateAsync({ metodo: 'POST', ruta: `/matriculas/${matricula.id}/${tipo}`, datos: d })
      avisos.exito(r.mensaje)
      onListo()
    } catch (e) {
      const error = e as ErrorApi
      if (error.errores?.motivo) setError('motivo', { message: error.errores.motivo[0] })
      else avisos.error(error.message)
    }
  })

  return (
    <form onSubmit={guardar} noValidate className="flex flex-col gap-4">
      <p className="text-texto-suave">
        {tipo === 'anular' ? (
          <>
            Anular deja sin efecto la matrícula de <strong className="text-texto">{matricula.estudiante.nombre}</strong>{' '}
            en {matricula.aula}, como si no hubiera existido. Solo se puede si aún no tiene asistencias, notas ni
            incidencias.
          </>
        ) : (
          <>
            Retirar registra que <strong className="text-texto">{matricula.estudiante.nombre}</strong> dejó{' '}
            {matricula.aula}. Su historial se conserva.
          </>
        )}
      </p>
      <div className="flex flex-col gap-1.5">
        <label htmlFor={idMotivo} className="font-bold">
          Motivo
        </label>
        <textarea
          id={idMotivo}
          rows={3}
          maxLength={300}
          aria-invalid={Boolean(errors.motivo)}
          className="w-full rounded-xl border border-borde bg-superficie px-4 py-3 outline-none focus:border-accion focus:ring-3 focus:ring-primario/20 aria-invalid:border-peligro"
          {...register('motivo')}
        />
        {errors.motivo && (
          <p role="alert" className="text-sm font-semibold text-peligro">
            {errors.motivo.message}
          </p>
        )}
      </div>
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variante="secundario" onClick={onListo}>
          Cancelar
        </Button>
        <Button type="submit" variante={tipo === 'anular' ? 'peligro' : 'principal'} disabled={isSubmitting}>
          {isSubmitting ? 'Guardando…' : tipo === 'anular' ? 'Anular matrícula' : 'Retirar'}
        </Button>
      </div>
    </form>
  )
}
