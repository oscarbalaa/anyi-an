import { Pencil } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { fechaCorta, useDocente } from '@/lib/personas'
import { fechaHora } from '@/lib/usuarios'
import { EstadoDocenteInsignia } from './EstadoDocente'
import { FormDocente } from './FormDocente'

/** Panel derecho: datos del docente, su carga y su coordinación; con "Editar" pasa a formulario */
export function FichaDocente({ id }: { id: number }) {
  const { data: d, isPending, isError } = useDocente(id)
  const [editando, setEditando] = useState(false)

  if (isPending) return <Esqueleto className="h-96" />
  if (isError) return <EstadoVacio titulo="No se pudo cargar el docente" />

  return (
    <Card className="flex flex-col gap-4" aria-label={`Docente ${d.nombre}`}>
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="text-2xl font-black">{d.nombre}</h2>
          <p className="text-texto-suave">
            {d.documento} · usuario <strong className="text-texto">{d.usuario}</strong>
          </p>
        </div>
        <EstadoDocenteInsignia estado={d.estado} cuenta={d.cuenta} />
      </div>

      {editando ? (
        <FormDocente docente={d} onListo={() => setEditando(false)} />
      ) : (
        <>
          <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2">
            <Dato etiqueta="Especialidad">{d.especialidad ?? '—'}</Dato>
            <Dato etiqueta="Título">{d.titulo ?? '—'}</Dato>
            <Dato etiqueta="Coordina">{d.coordina ?? 'Sin coordinación'}</Dato>
            <Dato etiqueta="Tutoría">{d.tutoria ?? '—'}</Dato>
            <Dato etiqueta="Correo">{d.correo ?? '—'}</Dato>
            <Dato etiqueta="Celular">{d.celular ?? '—'}</Dato>
            <Dato etiqueta="Nacimiento">{fechaCorta(d.fecha_nacimiento)}</Dato>
            <Dato etiqueta="Contrato">
              {d.fecha_inicio_contrato
                ? `${fechaCorta(d.fecha_inicio_contrato)} – ${fechaCorta(d.fecha_fin_contrato)}`
                : '—'}
            </Dato>
            <Dato etiqueta="Último acceso">{fechaHora(d.ultimo_acceso)}</Dato>
          </dl>
          <div>
            <h3 className="mb-2 font-extrabold">Cursos a cargo (periodo en curso)</h3>
            {d.cursos.length ? (
              <ul className="list-inside list-disc text-texto-suave">
                {d.cursos.map((c) => (
                  <li key={c}>{c}</li>
                ))}
              </ul>
            ) : (
              <p className="text-texto-suave">
                Aún no tiene cursos. Se asignan en Estructura académica → Carga y horarios.
              </p>
            )}
          </div>
          <Button variante="secundario" onClick={() => setEditando(true)}>
            <Pencil className="size-5" aria-hidden /> Editar datos
          </Button>
        </>
      )}
    </Card>
  )
}

function Dato({ etiqueta, children }: { etiqueta: string; children: ReactNode }) {
  return (
    <>
      <dt className="font-bold">{etiqueta}</dt>
      <dd className="min-w-0 [overflow-wrap:anywhere]">{children}</dd>
    </>
  )
}
