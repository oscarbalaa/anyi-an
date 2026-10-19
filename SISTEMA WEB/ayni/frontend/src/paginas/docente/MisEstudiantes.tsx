import { Download, Info } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import { Selector } from '@/components/ui/selector'
import { TablaDatos } from '@/components/ui/tabla-datos'
import { NIVEL } from '@/lib/alertas'
import { descargar, type ErrorApi } from '@/lib/api'
import { porcentaje } from '@/lib/asistencia'
import { nota, useMisEstudiantes } from '@/lib/inicio'
import { cn } from '@/lib/utils'

// RF-61 (mockup docente 04)
export default function MisEstudiantes() {
  const [curso, setCurso] = useState<number | null>(null)
  const [nivel, setNivel] = useState('')
  const { data, isPending, isError, refetch } = useMisEstudiantes(curso)
  const avisos = useAvisos()

  if (isPending) return <Esqueleto className="mx-auto h-96 max-w-7xl" />
  if (isError)
    return (
      <EstadoVacio
        titulo="No se pudo cargar tus estudiantes"
        accion={<Button onClick={() => refetch()}>Reintentar</Button>}
      />
    )
  if (!data.curso)
    return (
      <div className="mx-auto max-w-7xl">
        <h1 className="text-3xl font-black sm:text-4xl">Mis estudiantes</h1>
        <EstadoVacio
          titulo="Aún no tienes cursos a cargo"
          descripcion="Cuando la administración te asigne cursos, aparecerán aquí."
        />
      </div>
    )

  const actual = data.curso
  const filas = data.estudiantes.filter((e) => !nivel || e.nivel === nivel)
  const exportar = () =>
    descargar(
      `/docente/reportes/asistencia?aula_id=${actual.aula_id}${data.bimestre ? `&bimestre_id=${data.bimestre.id}` : ''}`,
      'asistencia.xlsx',
    ).catch((e) => avisos.error((e as ErrorApi).message))

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-3xl font-black sm:text-4xl">Mis estudiantes</h1>
          <p className="mt-1 text-lg text-texto-suave">
            Estudiantes de tus cursos{data.bimestre && ` · ${data.bimestre.nombre}`}.
          </p>
        </div>
        <Button variante="secundario" onClick={exportar}>
          <Download className="size-5" aria-hidden /> Asistencia de {actual.aula} en Excel
        </Button>
      </header>
      <p className="flex items-start gap-3 rounded-xl border border-info/25 bg-info/6 p-4" role="note">
        <Info className="mt-0.5 size-5 shrink-0 text-info" aria-hidden />
        <span>
          Solo se muestran tus cursos: <strong>{data.cursos.map((c) => c.nombre).join(', ')}</strong> · {data.total}{' '}
          {data.total === 1 ? 'estudiante' : 'estudiantes'}.
        </span>
      </p>
      <Card className="flex flex-col gap-4">
        <div className="grid gap-4 sm:grid-cols-2 lg:max-w-2xl">
          <Selector
            etiqueta="Curso"
            value={String(actual.id)}
            onChange={(e) => setCurso(Number(e.target.value))}
            opciones={data.cursos.map((c) => ({ valor: String(c.id), texto: c.nombre }))}
          />
          <Selector
            etiqueta="Nivel de riesgo"
            value={nivel}
            onChange={(e) => setNivel(e.target.value)}
            opciones={[
              { valor: '', texto: 'Todos' },
              { valor: 'ALTO', texto: 'Alto' },
              { valor: 'MEDIO', texto: 'Medio' },
              { valor: 'BAJO', texto: 'Bajo' },
            ]}
          />
        </div>
        <TablaDatos
          filas={filas}
          claveFila={(e) => e.matricula_id}
          textoBusqueda={(e) => `${e.nombre} ${e.codigo}`}
          descripcion={`Estudiantes de ${actual.nombre}`}
          vacio="Ningún estudiante coincide con los filtros."
          columnas={[
            {
              clave: 'estudiante',
              titulo: 'Estudiante',
              principal: true,
              celda: (e) => (
                <span className="block leading-tight">
                  <span className="block font-bold">{e.nombre}</span>
                  <span className="text-sm text-texto-suave">{e.codigo}</span>
                </span>
              ),
            },
            {
              clave: 'promedio',
              titulo: `Promedio de ${actual.asignatura}`,
              celda: (e) => (
                <span
                  className={cn('font-bold tabular-nums', e.promedio !== null && e.promedio < 11 && 'text-peligro')}
                >
                  {nota(e.promedio)}
                </span>
              ),
            },
            { clave: 'asistencia', titulo: 'Asistencia', celda: (e) => porcentaje(e.asistencia) },
            {
              clave: 'nivel',
              titulo: 'Nivel de riesgo',
              celda: (e) =>
                e.nivel ? (
                  <Insignia tono={NIVEL[e.nivel].tono} punto>
                    {NIVEL[e.nivel].texto}
                  </Insignia>
                ) : (
                  <span className="text-texto-suave">Sin calcular</span>
                ),
            },
            {
              clave: 'accion',
              titulo: 'Acción',
              celda: (e) => (
                <Button asChild variante="secundario" tamano="chico">
                  <Link to={`/docente/ficha?matricula=${e.matricula_id}`} aria-label={`Ver la ficha de ${e.nombre}`}>
                    Ver ficha
                  </Link>
                </Button>
              ),
            },
          ]}
        />
        <p className="text-sm text-texto-suave">
          El promedio es el del curso en el bimestre en curso, contando como 0 lo que aún no tiene nota (RN-09). La
          asistencia es la de tus clases.
        </p>
      </Card>
    </div>
  )
}
