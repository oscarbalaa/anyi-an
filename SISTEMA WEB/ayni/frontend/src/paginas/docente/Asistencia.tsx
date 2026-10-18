import { useSearchParams } from 'react-router'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Selector } from '@/components/ui/selector'
import { fechaLarga, useClase, useMisCursos } from '@/lib/asistencia'
import { RegistroClase } from '../asistencia/RegistroClase'
import { SelectorFecha } from '../asistencia/SelectorFecha'
import { useFechaElegida } from '../asistencia/useFechaElegida'

// CU-13 (mockup docente 05)
export default function Asistencia() {
  const [parametros, setParametros] = useSearchParams()
  const { data, isPending, isError, refetch } = useMisCursos()

  const cursos = data?.cursos ?? []
  const pedido = Number(parametros.get('curso'))
  const curso = cursos.find((c) => c.id === pedido) ?? cursos.find((c) => c.pendientes > 0) ?? cursos[0] ?? null
  const elegido = useFechaElegida('docente', curso?.id ?? null)
  const clase = useClase('docente', curso?.id ?? null, elegido.fecha)

  const cambiar = (cambios: Record<string, string | null>) => {
    const p = new URLSearchParams(parametros)
    for (const [k, v] of Object.entries(cambios)) {
      if (v) p.set(k, v)
      else p.delete(k)
    }
    setParametros(p, { replace: true })
  }

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6">
      <header>
        <h1 className="text-3xl font-black sm:text-4xl">Registro de asistencia</h1>
        <p className="mt-1 text-lg text-texto-suave">
          {curso && elegido.fecha
            ? `${curso.nombre} · ${fechaLarga(elegido.fecha)}`
            : 'Elige tu curso y la fecha de la clase.'}
        </p>
      </header>

      {isPending ? (
        <Esqueleto className="h-96" />
      ) : isError ? (
        <EstadoVacio
          titulo="No se pudo cargar tus cursos"
          accion={<Button onClick={() => refetch()}>Reintentar</Button>}
        />
      ) : !curso ? (
        <Card>
          <EstadoVacio
            titulo="Aún no tienes cursos a cargo"
            descripcion="Cuando el administrador te asigne cursos en el periodo en curso, aparecerán aquí."
          />
        </Card>
      ) : (
        <>
          <div className="grid gap-4 md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)]">
            <Selector
              etiqueta="Curso"
              value={String(curso.id)}
              onChange={(e) => cambiar({ curso: e.target.value, fecha: null, bimestre: null })}
              opciones={cursos.map((c) => ({
                valor: String(c.id),
                texto: c.pendientes ? `${c.nombre} (${c.pendientes} sin registrar)` : c.nombre,
              }))}
            />
            <SelectorFecha {...elegido} />
          </div>
          {!elegido.cargando && !elegido.fecha ? (
            <Card>
              <EstadoVacio
                titulo="No hay clases para registrar"
                descripcion="Este curso aún no tiene días de clase en el bimestre elegido."
              />
            </Card>
          ) : clase.isPending ? (
            <Esqueleto className="h-96" />
          ) : clase.isError ? (
            <EstadoVacio titulo="No se pudo cargar la clase" descripcion={clase.error.message} />
          ) : (
            <RegistroClase
              key={`${clase.data.curso.id}-${clase.data.fecha}-${clase.data.sesion.actualizado_en}`}
              clase={clase.data}
              rol="docente"
            />
          )}
        </>
      )}
    </div>
  )
}
