import { Selector } from '@/components/ui/selector'
import { fechaCorta, type FechaClase } from '@/lib/asistencia'
import type { useFechaElegida } from './useFechaElegida'

const ETIQUETA: Record<FechaClase['estado'], string> = {
  SIN_REGISTRO: 'sin registrar',
  BORRADOR: 'borrador',
  DEFINITIVO: 'registrada',
}

export function SelectorFecha({
  bimestres,
  bimestre,
  fechas,
  fecha,
  elegirBimestre,
  elegirFecha,
}: ReturnType<typeof useFechaElegida>) {
  return (
    <>
      <Selector
        etiqueta="Bimestre"
        value={String(bimestre?.id ?? '')}
        onChange={(e) => elegirBimestre(e.target.value)}
        opciones={bimestres.map((b) => ({ valor: String(b.id), texto: b.nombre }))}
      />
      <Selector
        etiqueta="Fecha de la clase"
        value={fecha ?? ''}
        onChange={(e) => elegirFecha(e.target.value)}
        opciones={
          fechas.length
            ? fechas.map((f) => ({
                valor: f.fecha,
                texto: `${fechaCorta(f.fecha)}${f.hoy ? ' (hoy)' : ''} · ${ETIQUETA[f.estado]}`,
              }))
            : [{ valor: '', texto: 'Sin clases hasta hoy' }]
        }
      />
    </>
  )
}
