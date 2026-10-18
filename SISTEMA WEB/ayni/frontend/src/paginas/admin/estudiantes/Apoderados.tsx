import { zodResolver } from '@hookform/resolvers/zod'
import { Pencil, Plus, Star, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { Casilla } from '@/components/ui/casilla'
import { DialogoConfirmacion, Ventana } from '@/components/ui/dialogo'
import { Selector } from '@/components/ui/selector'
import { marcarErrores } from '@/lib/formularios'
import { PARENTESCOS, useCambioPersonas, type Apoderado } from '@/lib/personas'
import { esquemaApoderado, filtros, type DatosApoderado } from '@/lib/validaciones'

// RF-29: apoderados del estudiante; uno solo es el principal (RN-24)
export function Apoderados({ estudianteId, apoderados }: { estudianteId: number; apoderados: Apoderado[] }) {
  const [editando, setEditando] = useState<Apoderado | 'nuevo' | null>(null)
  const [quitar, setQuitar] = useState<Apoderado | null>(null)
  const cambio = useCambioPersonas()
  const avisos = useAvisos()

  const confirmarQuitar = () => {
    if (!quitar) return
    cambio.mutate(
      { metodo: 'DELETE', ruta: `/apoderados/${quitar.id}` },
      {
        onSuccess: (r) => avisos.exito(r.mensaje),
        onError: (e) => avisos.error(e.message),
        onSettled: () => setQuitar(null),
      },
    )
  }

  return (
    <div>
      <div className="mb-2 flex items-center justify-between gap-2">
        <h3 className="font-extrabold">Apoderados</h3>
        <Button variante="fantasma" tamano="chico" onClick={() => setEditando('nuevo')}>
          <Plus className="size-4" aria-hidden /> Agregar
        </Button>
      </div>
      {apoderados.length ? (
        <ul className="flex flex-col gap-2">
          {apoderados.map((a) => (
            <li key={a.id} className="flex items-start justify-between gap-2 rounded-xl border border-borde p-3">
              <div className="min-w-0">
                <p className="font-bold [overflow-wrap:anywhere]">
                  {a.nombres}
                  {a.es_principal && (
                    <span className="ml-2 inline-flex items-center gap-1 text-sm font-semibold text-primario-oscuro">
                      <Star className="size-4" aria-hidden /> Principal
                    </span>
                  )}
                </p>
                <p className="text-sm text-texto-suave [overflow-wrap:anywhere]">
                  {[a.parentesco, a.celular, a.correo].filter(Boolean).join(' · ')}
                </p>
              </div>
              <div className="flex shrink-0">
                <Button
                  variante="fantasma"
                  tamano="chico"
                  onClick={() => setEditando(a)}
                  aria-label={`Editar a ${a.nombres}`}
                >
                  <Pencil className="size-4" aria-hidden />
                </Button>
                <Button
                  variante="fantasma"
                  tamano="chico"
                  onClick={() => setQuitar(a)}
                  aria-label={`Quitar a ${a.nombres}`}
                >
                  <Trash2 className="size-4" aria-hidden />
                </Button>
              </div>
            </li>
          ))}
        </ul>
      ) : (
        <p className="text-texto-suave">Sin apoderados registrados.</p>
      )}

      <Ventana
        abierto={editando !== null}
        titulo={editando === 'nuevo' ? 'Agregar apoderado' : 'Editar apoderado'}
        onCerrar={() => setEditando(null)}
        conCerrar
      >
        {editando !== null && (
          <FormApoderado
            key={editando === 'nuevo' ? 'nuevo' : editando.id}
            estudianteId={estudianteId}
            apoderado={editando === 'nuevo' ? null : editando}
            primero={apoderados.length === 0}
            onListo={() => setEditando(null)}
          />
        )}
      </Ventana>

      <DialogoConfirmacion
        abierto={quitar !== null}
        titulo="Quitar apoderado"
        textoConfirmar="Quitar"
        peligro
        ocupado={cambio.isPending}
        onCancelar={() => setQuitar(null)}
        onConfirmar={confirmarQuitar}
      >
        ¿Quitar a <strong>{quitar?.nombres}</strong> de los apoderados?
      </DialogoConfirmacion>
    </div>
  )
}

function FormApoderado({
  estudianteId,
  apoderado,
  primero,
  onListo,
}: {
  estudianteId: number
  apoderado: Apoderado | null
  primero: boolean
  onListo: () => void
}) {
  const cambio = useCambioPersonas()
  const avisos = useAvisos()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<DatosApoderado>({
    resolver: zodResolver(esquemaApoderado),
    defaultValues: {
      nombres: apoderado?.nombres ?? '',
      parentesco: apoderado?.parentesco ?? '',
      celular: apoderado?.celular ?? '',
      correo: apoderado?.correo ?? '',
      es_principal: apoderado?.es_principal ?? primero,
    },
  })

  const guardar = handleSubmit(async (d) => {
    const datos = { ...d, celular: d.celular || null, correo: d.correo || null }
    try {
      const r = await cambio.mutateAsync(
        apoderado
          ? { metodo: 'PUT', ruta: `/apoderados/${apoderado.id}`, datos }
          : { metodo: 'POST', ruta: `/estudiantes/${estudianteId}/apoderados`, datos },
      )
      avisos.exito(r.mensaje)
      onListo()
    } catch (e) {
      const mensaje = marcarErrores(e, setError, ['nombres', 'parentesco', 'celular', 'correo', 'es_principal'])
      if (mensaje) avisos.error(mensaje)
    }
  })

  return (
    <form onSubmit={guardar} noValidate className="flex flex-col gap-4">
      <Campo etiqueta="Nombres y apellidos" maxLength={150} error={errors.nombres?.message} {...register('nombres')} />
      <div className="flex flex-col gap-1.5">
        <Selector
          etiqueta="Parentesco"
          opciones={[{ valor: '', texto: 'Elige…' }, ...PARENTESCOS.map((p) => ({ valor: p, texto: p }))]}
          aria-invalid={Boolean(errors.parentesco)}
          {...register('parentesco')}
        />
        {errors.parentesco && (
          <p role="alert" className="text-sm font-semibold text-peligro">
            {errors.parentesco.message}
          </p>
        )}
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <Campo
          etiqueta="Celular (opcional)"
          inputMode="numeric"
          maxLength={9}
          filtro={filtros.celular}
          error={errors.celular?.message}
          {...register('celular')}
        />
        <Campo
          etiqueta="Correo (opcional)"
          type="email"
          maxLength={120}
          error={errors.correo?.message}
          {...register('correo')}
        />
      </div>
      <Casilla etiqueta="Es el apoderado principal" {...register('es_principal')} />
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variante="secundario" onClick={onListo}>
          Cancelar
        </Button>
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Guardando…' : 'Guardar apoderado'}
        </Button>
      </div>
    </form>
  )
}
