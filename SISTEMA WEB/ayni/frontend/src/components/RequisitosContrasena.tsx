import { Check, Circle } from 'lucide-react'
import { requisitosContrasena } from '@/lib/validaciones'
import type { Rol } from '@/layout/roles'

/** Lista que se marca mientras se escribe la nueva contraseña (RN-16) */
export function RequisitosContrasena({ rol, valor }: { rol: Rol | null; valor: string }) {
  return (
    <ul className="grid gap-1 text-sm sm:grid-cols-2" aria-label="Requisitos de la contraseña">
      {requisitosContrasena(rol).map((r) => {
        const ok = r.cumple(valor)
        return (
          <li
            key={r.texto}
            className={ok ? 'flex items-center gap-2 text-exito' : 'flex items-center gap-2 text-texto-suave'}
          >
            {ok ? <Check className="size-4" aria-hidden /> : <Circle className="size-3.5" aria-hidden />}
            {r.texto}
            <span className="sr-only">{ok ? '(cumple)' : '(pendiente)'}</span>
          </li>
        )
      })}
    </ul>
  )
}
