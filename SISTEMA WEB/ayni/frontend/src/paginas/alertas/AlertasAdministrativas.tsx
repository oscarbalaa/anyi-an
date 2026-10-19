import { ChevronRight } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia, type Tono } from '@/components/ui/insignia'
import { Selector } from '@/components/ui/selector'
import { TablaDatos } from '@/components/ui/tabla-datos'
import { useAlertasAdministrativas, type AlertaAdministrativa } from '@/lib/alertas'
import { hace } from '@/lib/notificaciones'

const SEVERIDAD: Record<AlertaAdministrativa['severidad'], { texto: string; tono: Tono }> = {
  CRITICO: { texto: 'Crítica', tono: 'peligro' },
  ATENCION: { texto: 'Atención', tono: 'aviso' },
  PENDIENTE: { texto: 'Pendiente', tono: 'info' },
}

// RF-56 (mockup admin 15): se resuelven solas cuando la situación desaparece
export function AlertasAdministrativas() {
  const [tipo, setTipo] = useState('')
  const [estado, setEstado] = useState('ABIERTA')
  const { data, isPending, isError, refetch } = useAlertasAdministrativas({ tipo, estado })

  return (
    <Card className="flex flex-col gap-4">
      <div className="grid gap-4 sm:grid-cols-2 lg:max-w-2xl">
        <Selector
          etiqueta="Tipo"
          value={tipo}
          onChange={(e) => setTipo(e.target.value)}
          opciones={[{ valor: '', texto: 'Todos' }, ...(data?.tipos ?? [])]}
        />
        <Selector
          etiqueta="Estado"
          value={estado}
          onChange={(e) => setEstado(e.target.value)}
          opciones={[
            { valor: 'ABIERTA', texto: `Abiertas${data ? ` (${data.conteo.abiertas})` : ''}` },
            { valor: 'RESUELTA', texto: `Resueltas${data ? ` (${data.conteo.resueltas})` : ''}` },
            { valor: 'todas', texto: 'Todas' },
          ]}
        />
      </div>
      {isPending ? (
        <Esqueleto className="h-64" />
      ) : isError ? (
        <EstadoVacio
          titulo="No se pudo cargar las alertas"
          accion={<Button onClick={() => refetch()}>Reintentar</Button>}
        />
      ) : (
        <TablaDatos
          filas={data.datos}
          claveFila={(a) => a.id}
          descripcion="Alertas administrativas"
          vacio={estado === 'ABIERTA' ? 'No hay alertas administrativas abiertas.' : 'No hay alertas con esos filtros.'}
          columnas={[
            {
              clave: 'alerta',
              titulo: 'Alerta',
              principal: true,
              celda: (a) => (
                <span className="block leading-tight">
                  <span className="block font-bold">{a.titulo}</span>
                  {a.detalle && <span className="text-sm text-texto-suave">{a.detalle}</span>}
                </span>
              ),
            },
            { clave: 'tipo', titulo: 'Tipo', celda: (a) => a.categoria },
            {
              clave: 'severidad',
              titulo: 'Severidad',
              celda: (a) => (
                <Insignia tono={SEVERIDAD[a.severidad].tono} punto>
                  {SEVERIDAD[a.severidad].texto}
                </Insignia>
              ),
            },
            {
              clave: 'estado',
              titulo: 'Estado',
              celda: (a) =>
                a.estado === 'ABIERTA' ? (
                  <span className="text-sm text-texto-suave">Abierta {hace(a.creada).toLowerCase()}</span>
                ) : (
                  <Insignia tono="exito">Resuelta</Insignia>
                ),
            },
            {
              clave: 'accion',
              titulo: 'Acción',
              celda: (a) =>
                a.estado === 'ABIERTA' ? (
                  <Button asChild tamano="chico">
                    <Link to={a.enlace} aria-label={`Resolver: ${a.titulo}`}>
                      Resolver <ChevronRight className="size-4" aria-hidden />
                    </Link>
                  </Button>
                ) : null,
            },
          ]}
        />
      )}
      <p className="text-sm text-texto-suave">
        «Resolver» lleva a la pantalla donde se corrige. La alerta se marca resuelta sola cuando la situación
        desaparece.
      </p>
    </Card>
  )
}
