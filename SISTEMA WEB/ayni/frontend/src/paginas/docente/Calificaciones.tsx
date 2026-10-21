import { FileDown, Info, Send, TriangleAlert } from 'lucide-react'
import { useState } from 'react'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { DialogoConfirmacion } from '@/components/ui/dialogo'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import { descargar, type ErrorApi } from '@/lib/api'
import { fechaCorta } from '@/lib/asistencia'
import { nota, useCalificaciones, useCambioEvaluaciones, type VistaCalificaciones } from '@/lib/evaluaciones'
import { cn } from '@/lib/utils'
import { FiltrosCurso } from './FiltrosCurso'
import { useCursoElegido } from './useCursoElegido'

const ESTADO = {
  BORRADOR: { texto: 'Borrador', tono: 'aviso' },
  PUBLICADA: { texto: 'Publicada', tono: 'exito' },
  CERRADA: { texto: 'Cerrada', tono: 'neutro' },
} as const

// CU-19 (mockup docente 08)
export default function Calificaciones() {
  const { cursos, lista, curso, bimestre, cambiar } = useCursoElegido()
  const { data, isPending, isError, refetch } = useCalificaciones(curso?.id ?? null, bimestre)

  if (cursos.isPending) return <Esqueleto className="mx-auto h-96 max-w-7xl" />
  if (!curso) return <EstadoVacio titulo="Aún no tienes cursos a cargo" />

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-3xl font-black sm:text-4xl">Calificaciones{data && ` del ${data.bimestre.nombre}`}</h1>
          <p className="mt-1 text-lg text-texto-suave">{curso.nombre} · Promedio ponderado calculado automáticamente</p>
        </div>
        {data && (
          <Insignia tono={ESTADO[data.estado].tono} punto>
            {ESTADO[data.estado].texto}
          </Insignia>
        )}
      </header>

      <FiltrosCurso
        cursos={lista}
        curso={curso}
        bimestres={data?.bimestres ?? []}
        bimestre={data?.bimestre ?? null}
        onCurso={(id) => cambiar({ curso: id, bimestre: null })}
        onBimestre={(id) => cambiar({ bimestre: id })}
      />

      {isPending ? (
        <Esqueleto className="h-96" />
      ) : isError ? (
        <EstadoVacio
          titulo="No se pudo cargar las calificaciones"
          accion={<Button onClick={() => refetch()}>Reintentar</Button>}
        />
      ) : (
        <Contenido v={data} />
      )}
    </div>
  )
}

function Contenido({ v }: { v: VistaCalificaciones }) {
  const [confirmar, setConfirmar] = useState(false)
  const cambio = useCambioEvaluaciones()
  const avisos = useAvisos()
  const baja = (p: number | null) => p !== null && p < v.nota_aprobatoria

  const publicar = () =>
    cambio.mutate(
      {
        metodo: 'POST',
        ruta: `/docente/cursos/${v.curso.id}/calificaciones/publicar`,
        datos: { bimestre_id: v.bimestre.id },
      },
      {
        onSuccess: (r) => avisos.exito(r.mensaje),
        onError: (e) => avisos.error((e as ErrorApi).message),
        onSettled: () => setConfirmar(false),
      },
    )

  return (
    <>
      <div className="grid gap-4 sm:grid-cols-3">
        <Indicador valor={v.resumen.estudiantes} etiqueta="Estudiantes" tono="text-primario-oscuro" />
        <Indicador valor={nota(v.resumen.promedio)} etiqueta="Promedio actual" />
        <Indicador
          valor={v.resumen.desaprobados}
          etiqueta={`Por debajo de ${nota(v.nota_aprobatoria)}`}
          tono="text-peligro"
        />
      </div>

      <Card className="flex flex-col gap-4">
        {v.evaluaciones.length === 0 ? (
          <EstadoVacio
            titulo="Aún no hay evaluaciones en este bimestre"
            descripcion="Créalas en Evaluaciones para calcular el promedio."
          />
        ) : (
          <div
            className="overflow-x-auto rounded-2xl border border-borde"
            tabIndex={0}
            role="region"
            aria-label="Calificaciones del curso"
          >
            <table className="w-full min-w-[36rem] text-left">
              <caption className="sr-only">Calificaciones del {v.bimestre.nombre}</caption>
              <thead className="bg-primario-suave/60 text-sm">
                <tr>
                  <th scope="col" className="sticky left-0 bg-primario-suave px-4 py-3 font-bold">
                    Estudiante
                  </th>
                  {v.evaluaciones.map((e) => (
                    <th
                      key={e.id}
                      scope="col"
                      className="px-3 py-3 text-center font-bold"
                      title={`${e.titulo} · ${fechaCorta(e.fecha_limite)}`}
                    >
                      <span className="block max-w-32 truncate">{e.titulo}</span>
                      <span className="font-normal text-texto-suave">peso {e.peso}</span>
                    </th>
                  ))}
                  <th scope="col" className="px-4 py-3 text-center font-bold">
                    Promedio
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-borde bg-superficie">
                {v.estudiantes.map((s) => (
                  <tr key={s.matricula_id}>
                    <th scope="row" className="sticky left-0 bg-superficie px-4 py-3 font-bold">
                      {s.nombre}
                    </th>
                    {v.evaluaciones.map((e) => {
                      const n = s.notas[e.id]
                      return (
                        <td
                          key={e.id}
                          className={cn(
                            'px-3 py-3 text-center',
                            n === 0 || baja(n) ? 'font-bold text-peligro' : '',
                            n === null && 'text-texto-suave',
                          )}
                        >
                          {nota(n)}
                        </td>
                      )
                    })}
                    <td className="px-4 py-2 text-center">
                      <span
                        className={cn(
                          'inline-flex min-w-16 justify-center gap-1 rounded-lg border-2 px-2 py-1 font-black',
                          baja(s.promedio) ? 'border-peligro/50 bg-peligro/8 text-peligro' : 'border-borde',
                        )}
                      >
                        {nota(s.promedio)}
                        {s.literal && <span className="font-semibold text-texto-suave">· {s.literal}</span>}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {v.sin_nota.length > 0 && (
          <div className="flex gap-3 rounded-xl border border-aviso/30 bg-aviso/10 px-4 py-3" role="note">
            <TriangleAlert className="mt-0.5 size-5 shrink-0 text-aviso" aria-hidden />
            <div>
              <p className="font-bold">Faltan notas: al publicar o cerrar el bimestre se contarán como 0 (RN-09).</p>
              <ul className="mt-1 list-disc pl-5 text-texto-suave">
                {v.sin_nota.map((s) => (
                  <li key={s.id}>
                    {s.titulo} ({fechaCorta(s.fecha_limite)}): {s.estudiantes.length}{' '}
                    {s.estudiantes.length === 1 ? 'estudiante' : 'estudiantes'}
                  </li>
                ))}
              </ul>
            </div>
          </div>
        )}

        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <p className="flex items-center gap-2 text-texto-suave">
            <Info className="size-5 shrink-0" aria-hidden />
            {v.estado === 'BORRADOR'
              ? 'Al publicar, los estudiantes podrán ver sus notas.'
              : v.estado === 'PUBLICADA'
                ? 'Publicado: los estudiantes ven sus notas y los cambios que guardes.'
                : 'Bimestre cerrado: las calificaciones ya no se modifican.'}
          </p>
          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button
              variante="secundario"
              onClick={() =>
                descargar(
                  `/docente/cursos/${v.curso.id}/calificaciones/excel?bimestre_id=${v.bimestre.id}`,
                  'calificaciones.xlsx',
                ).catch((e) => avisos.error((e as ErrorApi).message))
              }
            >
              <FileDown className="size-5" aria-hidden /> Exportar
            </Button>
            {v.estado === 'BORRADOR' && (
              <Button onClick={() => setConfirmar(true)} disabled={!v.editable || v.evaluaciones.length === 0}>
                <Send className="size-5" aria-hidden /> Publicar promedios
              </Button>
            )}
          </div>
        </div>
      </Card>

      <DialogoConfirmacion
        abierto={confirmar}
        titulo="Publicar promedios"
        textoConfirmar="Sí, publicar"
        ocupado={cambio.isPending}
        onCancelar={() => setConfirmar(false)}
        onConfirmar={publicar}
      >
        <p>
          Los estudiantes de {v.curso.aula} verán sus notas y su promedio del {v.bimestre.nombre} en{' '}
          {v.curso.asignatura}.
        </p>
        {v.sin_nota.length > 0 ? (
          <>
            <p className="mt-3 font-bold text-texto">Se pondrá 0 en lo que falta calificar:</p>
            <ul className="mt-1 max-h-48 list-disc overflow-y-auto pl-5">
              {v.sin_nota.map((s) => (
                <li key={s.id}>
                  <strong className="text-texto">{s.titulo}:</strong> {s.estudiantes.join('; ')}
                </li>
              ))}
            </ul>
          </>
        ) : (
          <p className="mt-3">Todas las evaluaciones tienen nota.</p>
        )}
      </DialogoConfirmacion>
    </>
  )
}

function Indicador({
  valor,
  etiqueta,
  tono = 'text-texto',
}: {
  valor: string | number
  etiqueta: string
  tono?: string
}) {
  return (
    <div className="rounded-2xl border border-borde bg-superficie p-5">
      <p className={cn('text-4xl leading-none font-black', tono)}>{valor}</p>
      <p className="mt-2 text-texto-suave">{etiqueta}</p>
    </div>
  )
}
