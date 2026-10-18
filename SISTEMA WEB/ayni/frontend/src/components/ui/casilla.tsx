import { useId, type ComponentProps } from 'react'

export function Casilla({ etiqueta, ...props }: Omit<ComponentProps<'input'>, 'type' | 'id'> & { etiqueta: string }) {
  const id = useId()
  return (
    <div className="flex items-center gap-3">
      <input id={id} type="checkbox" className="size-5 cursor-pointer rounded accent-accion" {...props} />
      <label htmlFor={id} className="cursor-pointer">
        {etiqueta}
      </label>
    </div>
  )
}
