import { Pencil } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { fechaCorta, useEstudiante } from '@/lib/personas'
import { fechaHora } from '@/lib/usuarios'
import { Apoderados } from './Apoderados'
import { EstadoEstudianteInsignia, EstadoMatriculaInsignia } from './Estados'
import { FormEstudiante } from './FormEstudiante'

/** Ficha del estudiante: datos, matrículas por periodo y apoderados */
export function FichaEstudiante({ id }: { id: number }) {
  const { data: e, isPending, isError } = useEstudiante(id)
  const [editando, setEditando] = useState(false)

  if (isPending) return <Esqueleto className="h-96" />
  if (isError) return <EstadoVacio titulo="No se pudo cargar el estudiante" />

  return (
    <Card className="flex flex-col gap-5" aria-label={`Estudiante ${e.nombre}`}>
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <h2 className="text-2xl font-black [overflow-wrap:anywhere]">{e.nombre}</h2>
          <p className="text-texto-suave">
            Código <strong className="text-texto">{e.codigo}</strong> · {e.documento}
          </p>
        </div>
        <EstadoEstudianteInsignia estado={e.estado} cuenta={e.cuenta} />
      </div>

      {editando ? (
        <FormEstudiante estudiante={e} onListo={() => setEditando(false)} />
      ) : (
        <>
          <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2">
            <Dato etiqueta="Aula actual">{e.aula ?? 'Sin matrícula en el periodo en curso'}</Dato>
            <Dato etiqueta="Nacimiento">{fechaCorta(e.fecha_nacimiento)}</Dato>
            <Dato etiqueta="Sexo">{e.sexo === 'M' ? 'Masculino' : e.sexo === 'F' ? 'Femenino' : '—'}</Dato>
            <Dato etiqueta="Ingreso">{fechaCorta(e.fecha_ingreso)}</Dato>
            <Dato etiqueta="Correo">{e.correo ?? '—'}</Dato>
            <Dato etiqueta="Celular">{e.celular ?? '—'}</Dato>
            <Dato etiqueta="Dirección">{e.direccion ?? '—'}</Dato>
            <Dato etiqueta="Registrado por">{e.registrado_por}</Dato>
            <Dato etiqueta="Último acceso">{fechaHora(e.ultimo_acceso)}</Dato>
          </dl>
          <Button variante="secundario" onClick={() => setEditando(true)}>
            <Pencil className="size-5" aria-hidden /> Editar datos
          </Button>
        </>
      )}

      <div>
        <h3 className="mb-2 font-extrabold">Matrículas</h3>
        {e.matriculas.length ? (
          <ul className="flex flex-col gap-2">
            {e.matriculas.map((m) => (
              <li key={m.id} className="flex items-start justify-between gap-2 rounded-xl border border-borde p-3">
                <div className="min-w-0">
                  <p className="font-bold">
                    {m.periodo} · {m.aula}
                  </p>
                  <p className="text-sm text-texto-suave [overflow-wrap:anywhere]">
                    Desde el {fechaCorta(m.fecha)}
                    {m.motivo && ` · ${m.motivo}`}
                  </p>
                </div>
                <EstadoMatriculaInsignia estado={m.estado} />
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-texto-suave">Aún no tiene matrículas. Se matricula en la pestaña Matrículas.</p>
        )}
      </div>

      <Apoderados estudianteId={e.id} apoderados={e.apoderados} />
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
