import { ChevronDown } from 'lucide-react'
import { useId, type ComponentProps } from 'react'
import { cn } from '@/lib/utils'

type Props = Omit<ComponentProps<'select'>, 'id'> & {
  etiqueta: string
  opciones: { valor: string; texto: string }[]
}

/** Lista desplegable nativa (en el celular abre el selector del sistema) */
export function Selector({ etiqueta, opciones, className, ...props }: Props) {
  const id = useId()
  return (
    <div className={cn('flex flex-col gap-1.5', className)}>
      <label htmlFor={id} className="font-bold">
        {etiqueta}
      </label>
      <div className="relative">
        <select
          id={id}
          className="h-11 w-full cursor-pointer appearance-none rounded-xl border border-borde bg-superficie pr-10 pl-4 outline-none focus:border-accion focus:ring-3 focus:ring-primario/20"
          {...props}
        >
          {opciones.map((o) => (
            <option key={o.valor} value={o.valor}>
              {o.texto}
            </option>
          ))}
        </select>
        <ChevronDown
          className="pointer-events-none absolute top-1/2 right-3 size-5 -translate-y-1/2 text-texto-suave"
          aria-hidden
        />
      </div>
    </div>
  )
}
