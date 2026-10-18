import { useState, type ReactNode } from 'react'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { DialogoConfirmacion } from '@/components/ui/dialogo'
import type { ErrorApi } from '@/lib/api'
import { useCambio } from '@/lib/estructura'

type Pedido = {
  titulo: string
  texto: ReactNode
  textoConfirmar: string
  peligro?: boolean
  metodo: 'POST' | 'PATCH' | 'DELETE'
  ruta: string
  datos?: unknown
  /** Tras hacerlo (por ejemplo, deseleccionar lo eliminado) */
  despues?: () => void
}

/** Confirmación + llamada a la API + aviso del resultado */
export function useConfirmar() {
  const [pedido, setPedido] = useState<Pedido | null>(null)
  const cambio = useCambio()
  const avisos = useAvisos()

  const dialogo = (
    <DialogoConfirmacion
      abierto={pedido !== null}
      titulo={pedido?.titulo ?? ''}
      textoConfirmar={pedido?.textoConfirmar}
      peligro={pedido?.peligro}
      ocupado={cambio.isPending}
      onCancelar={() => setPedido(null)}
      onConfirmar={() => {
        if (!pedido) return
        cambio.mutate(
          { metodo: pedido.metodo, ruta: pedido.ruta, datos: pedido.datos },
          {
            onSuccess: (r) => {
              avisos.exito(r.mensaje)
              pedido.despues?.()
            },
            onError: (e) => avisos.error((e as ErrorApi).message),
            onSettled: () => setPedido(null),
          },
        )
      }}
    >
      {pedido?.texto}
    </DialogoConfirmacion>
  )

  return { confirmar: setPedido, dialogo }
}
