import { zodResolver } from '@hookform/resolvers/zod'
import { CalendarClock, Info, Plus, Trash2, X } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useSearchParams } from 'react-router'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { Card } from '@/components/ui/card'
import { Ventana } from '@/components/ui/dialogo'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Selector } from '@/components/ui/selector'
import { TablaDatos } from '@/components/ui/tabla-datos'
import { useAulas, useCambio, useOpciones, type Aula, type Opciones } from '@/lib/estructura'
import { idONulo, marcarErrores } from '@/lib/formularios'
import { esquemaAula, type DatosAula } from '@/lib/validaciones'
import { Encabezado } from './Encabezado'
import { useConfirmar } from './useConfirmar'

const NIVELES = [
  { valor: '', texto: 'Todos' },
  { valor: 'PRIMARIA', texto: 'Primaria' },
  { valor: 'SECUNDARIA', texto: 'Secundaria' },
]

const DE_QUIEN: Record<string, string> = { GRADO: 'del grado', NIVEL: 'del nivel', INSTITUCION: 'del colegio' }

// RF-19, RF-20, RF-24 (mockup admin 06)
export function PestanaAulas() {
  const [periodo, setPeriodo] = useState<number | null>(null)
  const [nivel, setNivel] = useState('')
  const { data: opciones } = useOpciones()
  const periodoId = periodo ?? opciones?.periodo_id ?? null
  const { data, isPending, isError } = useAulas(periodoId, nivel)
  const [form, setForm] = useState<Aula | 'nueva' | null>(null)
  const [, setParametros] = useSearchParams()
  const cerrado = opciones?.periodos.find((p) => p.id === periodoId)?.estado === 'CERRADO'

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div className="grid flex-1 gap-4 sm:max-w-xl sm:grid-cols-2">
          <Selector
            etiqueta="Periodo"
            opciones={(opciones?.periodos ?? []).map((p) => ({ valor: String(p.id), texto: p.nombre }))}
            value={periodoId ?? ''}
            onChange={(e) => setPeriodo(Number(e.target.value))}
          />
          <Selector etiqueta="Nivel" opciones={NIVELES} value={nivel} onChange={(e) => setNivel(e.target.value)} />
        </div>
        {!cerrado && (
          <Button onClick={() => setForm('nueva')} disabled={!periodoId}>
            <Plus className="size-5" aria-hidden /> Nueva aula
          </Button>
        )}
      </div>

      <Card>
        {!periodoId ? (
          <EstadoVacio titulo="No hay periodos" descripcion="Crea un periodo en la pestaña Periodos." />
        ) : isPending ? (
          <Esqueleto className="h-64" />
        ) : isError ? (
          <EstadoVacio titulo="No se pudieron cargar las aulas" />
        ) : (
          <TablaDatos
            filas={data.aulas}
            claveFila={(a) => a.id}
            descripcion="Aulas del periodo"
            vacio="Este periodo aún no tiene aulas."
            porPagina={20}
            columnas={[
              {
                clave: 'aula',
                titulo: 'Aula',
                principal: true,
                celda: (a) => (
                  <span className="block leading-tight">
                    <span className="block font-extrabold">{a.nombre}</span>
                    <span className="text-sm text-texto-suave">
                      {a.nivel === 'PRIMARIA' ? 'Primaria' : 'Secundaria'}
                    </span>
                  </span>
                ),
              },
              {
                clave: 'tutor',
                titulo: 'Docente de aula',
                celda: (a) => a.tutor?.nombre ?? <span className="text-texto-suave">Sin tutor</span>,
              },
              {
                clave: 'coordinador',
                titulo: 'Coordinador',
                celda: (a) =>
                  a.coordinador ? (
                    <span className="block leading-tight">
                      {a.coordinador.nombre}
                      {!a.coordinador.propio && (
                        <span className="block text-sm text-texto-suave">{DE_QUIEN[a.coordinador.ambito]}</span>
                      )}
                    </span>
                  ) : (
                    <span className="text-texto-suave">Sin coordinador</span>
                  ),
              },
              {
                clave: 'matriculados',
                titulo: 'Matriculados',
                celda: (a) => (
                  <span>
                    <strong>{a.matriculados}</strong> / {a.capacidad}
                  </span>
                ),
              },
              {
                clave: 'acciones',
                titulo: 'Acción',
                celda: (a) => (
                  <span className="flex gap-2">
                    <Button
                      variante="secundario"
                      tamano="chico"
                      onClick={() => setForm(a)}
                      aria-label={`Editar ${a.nombre}`}
                      disabled={cerrado}
                    >
                      Editar
                    </Button>
                    <Button
                      variante="fantasma"
                      tamano="chico"
                      onClick={() =>
                        setParametros({ pestana: 'carga', periodo: String(periodoId), aula: String(a.id) })
                      }
                      aria-label={`Carga y horario de ${a.nombre}`}
                    >
                      <CalendarClock className="size-4" aria-hidden /> Cursos ({a.cursos})
                    </Button>
                  </span>
                ),
              },
            ]}
          />
        )}
        <p className="mt-4 flex gap-2 rounded-xl bg-fondo p-3 text-sm text-texto-suave">
          <Info className="size-5 shrink-0" aria-hidden />
          Si un aula no tiene coordinador propio, la coordina el de su grado, su nivel o el de todo el colegio. Si no
          hay ninguno, la coordinación la asume la administración.
        </p>
      </Card>

      {periodoId && data && opciones && (
        <Coordinadores
          periodoId={periodoId}
          coordinaciones={data.coordinaciones}
          opciones={opciones}
          cerrado={cerrado}
        />
      )}

      <Ventana
        abierto={form !== null}
        titulo={form === 'nueva' ? 'Nueva aula' : `Editar ${form?.nombre ?? ''}`}
        onCerrar={() => setForm(null)}
        conCerrar
        className="w-[min(94vw,36rem)]"
      >
        {form !== null && opciones && periodoId && (
          <FormAula
            aula={form === 'nueva' ? null : form}
            periodoId={periodoId}
            opciones={opciones}
            onListo={() => setForm(null)}
          />
        )}
      </Ventana>
    </div>
  )
}

function FormAula({
  aula,
  periodoId,
  opciones,
  onListo,
}: {
  aula: Aula | null
  periodoId: number
  opciones: Opciones
  onListo: () => void
}) {
  const cambio = useCambio()
  const avisos = useAvisos()
  const { confirmar, dialogo } = useConfirmar()
  const docentes = [
    { valor: '', texto: 'Sin asignar' },
    ...opciones.docentes.map((d) => ({ valor: String(d.id), texto: d.nombre })),
  ]
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<DatosAula>({
    resolver: zodResolver(esquemaAula),
    defaultValues: {
      grado_id: String(aula?.grado_id ?? opciones.grados[0]?.id ?? ''),
      seccion: aula?.seccion ?? 'A',
      capacidad: String(aula?.capacidad ?? 30),
      tutor_docente_id: aula?.tutor ? String(aula.tutor.id) : '',
      coordinador_docente_id: aula?.coordinador?.propio ? String(aula.coordinador.id) : '',
      ambiente: aula?.ambiente ?? '',
    },
  })

  const guardar = handleSubmit(async (d) => {
    const comunes = {
      capacidad: Number(d.capacidad),
      tutor_docente_id: idONulo(d.tutor_docente_id),
      coordinador_docente_id: idONulo(d.coordinador_docente_id),
      ambiente: d.ambiente || null,
    }
    try {
      const r = await cambio.mutateAsync(
        aula
          ? { metodo: 'PUT', ruta: `/aulas/${aula.id}`, datos: comunes }
          : {
              metodo: 'POST',
              ruta: '/aulas',
              datos: { ...comunes, periodo_id: periodoId, grado_id: Number(d.grado_id), seccion: d.seccion },
            },
      )
      avisos.exito(r.mensaje)
      onListo()
    } catch (e) {
      const mensaje = marcarErrores(e, setError, [
        'grado_id',
        'seccion',
        'capacidad',
        'tutor_docente_id',
        'coordinador_docente_id',
        'ambiente',
      ])
      if (mensaje) avisos.error(mensaje)
    }
  })

  return (
    <form onSubmit={guardar} noValidate className="flex flex-col gap-4">
      {aula ? (
        <p className="text-texto-suave">
          El grado y la sección no se cambian: si te equivocaste, elimina el aula y créala otra vez.
        </p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2">
          <Selector
            etiqueta="Grado"
            opciones={opciones.grados.map((g) => ({ valor: String(g.id), texto: g.nombre }))}
            {...register('grado_id')}
          />
          <div className="flex flex-col gap-1.5">
            <Selector
              etiqueta="Sección"
              opciones={['Única', 'A', 'B', 'C', 'D', 'E', 'F'].map((s) => ({ valor: s, texto: s }))}
              aria-describedby={errors.seccion ? 'error-seccion' : undefined}
              {...register('seccion')}
            />
            {errors.seccion && (
              <p id="error-seccion" role="alert" className="text-sm font-semibold text-peligro">
                {errors.seccion.message}
              </p>
            )}
          </div>
        </div>
      )}
      <div className="grid gap-4 sm:grid-cols-2">
        <Campo
          etiqueta="Capacidad"
          inputMode="numeric"
          maxLength={2}
          filtro={(v) => v.replace(/\D/g, '')}
          error={errors.capacidad?.message}
          {...register('capacidad')}
        />
        <Campo
          etiqueta="Ambiente (opcional)"
          placeholder="Aula 12"
          maxLength={40}
          error={errors.ambiente?.message}
          {...register('ambiente')}
        />
      </div>
      <Selector etiqueta="Docente de aula (tutor)" opciones={docentes} {...register('tutor_docente_id')} />
      {errors.tutor_docente_id && (
        <p role="alert" className="-mt-2 text-sm font-semibold text-peligro">
          {errors.tutor_docente_id.message}
        </p>
      )}
      <Selector etiqueta="Coordinador propio del aula" opciones={docentes} {...register('coordinador_docente_id')} />
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
        {aula && aula.matriculados === 0 && aula.cursos === 0 ? (
          <Button
            variante="fantasma"
            className="text-peligro"
            onClick={() =>
              confirmar({
                titulo: `¿Eliminar ${aula.nombre}?`,
                texto: 'No tiene matrículas ni cursos. No se puede deshacer.',
                textoConfirmar: 'Sí, eliminar',
                peligro: true,
                metodo: 'DELETE',
                ruta: `/aulas/${aula.id}`,
                despues: onListo,
              })
            }
          >
            <Trash2 className="size-5" aria-hidden /> Eliminar aula
          </Button>
        ) : (
          <span />
        )}
        <div className="flex flex-col-reverse gap-2 sm:flex-row">
          <Button variante="secundario" onClick={onListo}>
            Cancelar
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'Guardando…' : aula ? 'Guardar cambios' : 'Crear aula'}
          </Button>
        </div>
      </div>
      {dialogo}
    </form>
  )
}

const AMBITOS = [
  { valor: 'INSTITUCION', texto: 'Todo el colegio' },
  { valor: 'NIVEL', texto: 'Un nivel' },
  { valor: 'GRADO', texto: 'Un grado' },
]

function Coordinadores({
  periodoId,
  coordinaciones,
  opciones,
  cerrado,
}: {
  periodoId: number
  coordinaciones: { id: number; docente: { nombre: string }; alcance: string }[]
  opciones: Opciones
  cerrado: boolean
}) {
  const [ambito, setAmbito] = useState('NIVEL')
  const [alcance, setAlcance] = useState('')
  const [docente, setDocente] = useState('')
  const cambio = useCambio()
  const avisos = useAvisos()
  const { confirmar, dialogo } = useConfirmar()

  const asignar = () => {
    if (!docente) return avisos.error('Elige un docente.')
    const datos = {
      periodo_id: periodoId,
      docente_id: Number(docente),
      ambito,
      ...(ambito === 'GRADO' ? { grado_id: Number(alcance || opciones.grados[0]?.id) } : {}),
      ...(ambito === 'NIVEL' ? { nivel: alcance || 'PRIMARIA' } : {}),
    }
    cambio.mutate(
      { metodo: 'POST', ruta: '/coordinaciones', datos },
      { onSuccess: (r) => avisos.exito(r.mensaje), onError: (e) => avisos.error((e as Error).message) },
    )
  }

  return (
    <Card>
      <Encabezado
        titulo="Coordinadores"
        detalle="De un grado, de un nivel o de todo el colegio (los de un aula se eligen al editarla)."
      />
      {coordinaciones.length === 0 ? (
        <p className="text-texto-suave">Aún no hay coordinadores de grado, nivel o colegio.</p>
      ) : (
        <ul className="mb-4 flex flex-col divide-y divide-borde">
          {coordinaciones.map((c) => (
            <li key={c.id} className="flex items-center justify-between gap-3 py-2">
              <span>
                <strong>{c.alcance}</strong> · {c.docente.nombre}
              </span>
              {!cerrado && (
                <Button
                  variante="fantasma"
                  tamano="chico"
                  aria-label={`Quitar a ${c.docente.nombre} de ${c.alcance}`}
                  onClick={() =>
                    confirmar({
                      titulo: '¿Quitar este coordinador?',
                      texto: `${c.docente.nombre} dejará de coordinar ${c.alcance.toLowerCase()}.`,
                      textoConfirmar: 'Sí, quitar',
                      metodo: 'DELETE',
                      ruta: `/coordinaciones/${c.id}`,
                    })
                  }
                >
                  <X className="size-4" aria-hidden /> Quitar
                </Button>
              )}
            </li>
          ))}
        </ul>
      )}
      {!cerrado && (
        <div className="mt-2 grid items-end gap-4 md:grid-cols-[1fr_1fr_1.5fr_auto]">
          <Selector
            etiqueta="Ámbito"
            opciones={AMBITOS}
            value={ambito}
            onChange={(e) => {
              setAmbito(e.target.value)
              setAlcance('')
            }}
          />
          {ambito === 'GRADO' ? (
            <Selector
              etiqueta="Grado a coordinar"
              opciones={opciones.grados.map((g) => ({ valor: String(g.id), texto: g.nombre }))}
              value={alcance}
              onChange={(e) => setAlcance(e.target.value)}
            />
          ) : ambito === 'NIVEL' ? (
            <Selector
              etiqueta="Nivel a coordinar"
              opciones={NIVELES.slice(1)}
              value={alcance || 'PRIMARIA'}
              onChange={(e) => setAlcance(e.target.value)}
            />
          ) : (
            <span className="hidden md:block" />
          )}
          <Selector
            etiqueta="Docente"
            opciones={[
              { valor: '', texto: 'Elige un docente' },
              ...opciones.docentes.map((d) => ({ valor: String(d.id), texto: d.nombre })),
            ]}
            value={docente}
            onChange={(e) => setDocente(e.target.value)}
          />
          <Button onClick={asignar} disabled={cambio.isPending}>
            Asignar
          </Button>
        </div>
      )}
      {dialogo}
    </Card>
  )
}
