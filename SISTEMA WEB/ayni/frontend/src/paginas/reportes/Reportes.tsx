import { CalendarCheck, FileSpreadsheet, FileText, Sheet } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Card, CardTitulo } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Selector } from '@/components/ui/selector'
import { descargar, type ErrorApi } from '@/lib/api'
import { useOpcionesReportes } from '@/lib/inicio'

// CU-28: reportes exportables (RF-62 a RF-64)
export default function Reportes() {
  const { data, isPending, isError, refetch } = useOpcionesReportes('admin')
  const [aula, setAula] = useState('')
  const [bimestre, setBimestre] = useState('')
  const [descargando, setDescargando] = useState<string | null>(null)
  const avisos = useAvisos()

  if (isPending) return <Esqueleto className="mx-auto h-96 max-w-5xl" />
  if (isError)
    return (
      <EstadoVacio
        titulo="No se pudo cargar los reportes"
        accion={<Button onClick={() => refetch()}>Reintentar</Button>}
      />
    )

  const aulaElegida = aula || String(data.aulas[0]?.id ?? '')
  const bimestreElegido = bimestre || String(data.bimestre ?? data.bimestres[0]?.id ?? '')
  const nombreAula = data.aulas.find((a) => String(a.id) === aulaElegida)?.nombre ?? ''

  const bajar = async (tipo: 'asistencia' | 'consolidado') => {
    setDescargando(tipo)
    try {
      await descargar(`/admin/reportes/${tipo}?aula_id=${aulaElegida}&bimestre_id=${bimestreElegido}`, `${tipo}.xlsx`)
      avisos.exito(tipo === 'asistencia' ? 'Asistencia descargada.' : 'Consolidado descargado.')
    } catch (e) {
      avisos.error((e as ErrorApi).message)
    } finally {
      setDescargando(null)
    }
  }

  return (
    <div className="mx-auto flex max-w-5xl flex-col gap-6">
      <header>
        <h1 className="text-3xl font-black sm:text-4xl">Reportes</h1>
        <p className="mt-1 text-lg text-texto-suave">Asistencia y notas por aula en Excel, y boletas en PDF.</p>
      </header>
      {data.aulas.length === 0 ? (
        <EstadoVacio titulo="No hay aulas en el periodo en curso" />
      ) : (
        <>
          <Card className="grid gap-4 sm:grid-cols-2">
            <Selector
              etiqueta="Aula"
              value={aulaElegida}
              onChange={(e) => setAula(e.target.value)}
              opciones={data.aulas.map((a) => ({ valor: String(a.id), texto: a.nombre }))}
            />
            <Selector
              etiqueta="Bimestre"
              value={bimestreElegido}
              onChange={(e) => setBimestre(e.target.value)}
              opciones={data.bimestres.map((b) => ({ valor: String(b.id), texto: b.nombre }))}
            />
          </Card>
          <div className="grid gap-6 md:grid-cols-2">
            <Card className="flex flex-col gap-3">
              <CardTitulo className="flex items-center gap-2">
                <CalendarCheck className="size-6 text-primario" aria-hidden /> Asistencia por aula
              </CardTitulo>
              <p className="text-texto-suave">
                Clases, presentes, faltas, tardanzas, justificadas y % de asistencia de cada estudiante de {nombreAula}.
              </p>
              <Button onClick={() => bajar('asistencia')} disabled={descargando !== null} className="self-start">
                <FileSpreadsheet className="size-5" aria-hidden />{' '}
                {descargando === 'asistencia' ? 'Descargando…' : 'Descargar asistencia'}
              </Button>
            </Card>
            <Card className="flex flex-col gap-3">
              <CardTitulo className="flex items-center gap-2">
                <Sheet className="size-6 text-primario" aria-hidden /> Consolidado del bimestre
              </CardTitulo>
              <p className="text-texto-suave">
                El promedio de cada curso, el promedio general y la asistencia de cada estudiante de {nombreAula}.
              </p>
              <Button onClick={() => bajar('consolidado')} disabled={descargando !== null} className="self-start">
                <FileSpreadsheet className="size-5" aria-hidden />{' '}
                {descargando === 'consolidado' ? 'Descargando…' : 'Descargar consolidado'}
              </Button>
            </Card>
          </div>
          <Card className="flex flex-col gap-2">
            <CardTitulo className="flex items-center gap-2">
              <FileText className="size-6 text-primario" aria-hidden /> Boleta de notas
            </CardTitulo>
            <p className="text-texto-suave">
              La boleta en PDF de cada estudiante se descarga desde su <strong>ficha 360°</strong> (Estudiantes → la
              ficha del estudiante). Los estudiantes la descargan desde su inicio. Solo incluye promedios publicados o
              cerrados.
            </p>
            <Button asChild variante="secundario" className="self-start">
              <Link to="/admin/estudiantes">Ir a Estudiantes</Link>
            </Button>
          </Card>
        </>
      )}
    </div>
  )
}
