import { X } from 'lucide-react'
import { useEffect, useId, useRef, type ReactNode } from 'react'
import { cn } from '@/lib/utils'
import { Button } from './button'

type PropsVentana = {
  abierto: boolean
  titulo: string
  children: ReactNode
  onCerrar: () => void
  /** Botón X arriba a la derecha (no en las confirmaciones) */
  conCerrar?: boolean
  className?: string
}

/** Ventana modal con <dialog> nativo: atrapa el foco y se cierra con Esc. */
export function Ventana({ abierto, titulo, children, onCerrar, conCerrar = false, className }: PropsVentana) {
  const ref = useRef<HTMLDialogElement>(null)
  const idTitulo = useId()

  useEffect(() => {
    const dialogo = ref.current
    if (!dialogo) return
    if (abierto && !dialogo.open) dialogo.showModal?.()
    if (!abierto && dialogo.open) dialogo.close?.()
  }, [abierto])

  return (
    <dialog
      ref={ref}
      aria-labelledby={idTitulo}
      onCancel={(e) => {
        e.preventDefault()
        onCerrar()
      }}
      className={cn(
        'm-auto max-h-[92vh] w-[min(92vw,28rem)] rounded-2xl border border-borde bg-superficie p-0 text-texto shadow-xl backdrop:bg-black/40',
        className,
      )}
    >
      {abierto && (
        <div className="flex flex-col gap-4 p-6">
          <div className="flex items-start justify-between gap-3">
            <h2 id={idTitulo} className="text-xl font-extrabold">
              {titulo}
            </h2>
            {conCerrar && (
              <button
                type="button"
                onClick={onCerrar}
                className="-mt-1 -mr-2 grid size-10 shrink-0 cursor-pointer place-items-center rounded-lg text-texto-suave hover:bg-fondo hover:text-texto"
                aria-label="Cerrar"
              >
                <X className="size-5" aria-hidden />
              </button>
            )}
          </div>
          {children}
        </div>
      )}
    </dialog>
  )
}

type Props = {
  abierto: boolean
  titulo: string
  children: ReactNode
  textoConfirmar?: string
  peligro?: boolean
  ocupado?: boolean
  onConfirmar: () => void
  onCancelar: () => void
}

/** Confirmación antes de una acción que no se puede deshacer */
export function DialogoConfirmacion({
  abierto,
  titulo,
  children,
  textoConfirmar = 'Confirmar',
  peligro,
  ocupado,
  onConfirmar,
  onCancelar,
}: Props) {
  return (
    <Ventana abierto={abierto} titulo={titulo} onCerrar={onCancelar}>
      <div className="text-texto-suave">{children}</div>
      <div className="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variante="secundario" onClick={onCancelar} autoFocus>
          Cancelar
        </Button>
        <Button variante={peligro ? 'peligro' : 'principal'} onClick={onConfirmar} disabled={ocupado}>
          {textoConfirmar}
        </Button>
      </div>
    </Ventana>
  )
}
