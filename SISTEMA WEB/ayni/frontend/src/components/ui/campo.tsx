import { Eye, EyeOff } from 'lucide-react'
import { useId, useState, type ComponentProps, type ReactNode } from 'react'
import { cn } from '@/lib/utils'

type Props = Omit<ComponentProps<'input'>, 'id'> & {
  etiqueta: string
  error?: string
  ayuda?: ReactNode
  /** Filtra lo que se escribe o pega (por ejemplo, solo dígitos) */
  filtro?: (valor: string) => string
}

export function Campo({ etiqueta, error, ayuda, filtro, type = 'text', className, onChange, ...props }: Props) {
  const id = useId()
  const [visible, setVisible] = useState(false)
  const esClave = type === 'password'
  const descripcion = [error && `${id}-error`, ayuda && `${id}-ayuda`].filter(Boolean).join(' ') || undefined

  return (
    <div className={cn('flex flex-col gap-1.5', className)}>
      <label htmlFor={id} className="font-bold">
        {etiqueta}
      </label>
      <div className="relative">
        <input
          id={id}
          type={esClave && visible ? 'text' : type}
          aria-invalid={error ? true : undefined}
          aria-describedby={descripcion}
          className={cn(
            'h-13 w-full rounded-xl border bg-superficie px-4 text-base outline-none transition-colors placeholder:text-texto-suave/70',
            'focus:border-accion focus:ring-3 focus:ring-primario/20',
            error ? 'border-peligro' : 'border-borde',
            esClave && 'pr-13',
          )}
          onChange={(e) => {
            if (filtro) {
              const limpio = filtro(e.target.value)
              if (limpio !== e.target.value) e.target.value = limpio
            }
            onChange?.(e)
          }}
          {...props}
        />
        {esClave && (
          <button
            type="button"
            onClick={() => setVisible((v) => !v)}
            className="absolute inset-y-0 right-0 grid w-12 cursor-pointer place-items-center text-texto-suave hover:text-texto"
            aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
            aria-pressed={visible}
          >
            {visible ? <EyeOff className="size-5" aria-hidden /> : <Eye className="size-5" aria-hidden />}
          </button>
        )}
      </div>
      {error && (
        <p id={`${id}-error`} role="alert" className="text-sm font-semibold text-peligro">
          {error}
        </p>
      )}
      {ayuda && !error && (
        <p id={`${id}-ayuda`} className="text-sm text-texto-suave">
          {ayuda}
        </p>
      )}
    </div>
  )
}
