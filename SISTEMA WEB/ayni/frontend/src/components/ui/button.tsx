import { Slot } from '@radix-ui/react-slot'
import { cva, type VariantProps } from 'class-variance-authority'
import type { ComponentProps } from 'react'
import { cn } from '@/lib/utils'

const variantes = cva(
  'inline-flex cursor-pointer items-center justify-center gap-2 font-bold transition-colors disabled:pointer-events-none disabled:opacity-50',
  {
    variants: {
      variante: {
        principal: 'bg-accion text-white hover:bg-accion-hover',
        secundario: 'border-2 border-accion bg-superficie text-accion hover:bg-primario-suave',
        fantasma: 'text-accion hover:bg-primario-suave',
        peligro: 'bg-peligro text-white hover:bg-peligro/90',
      },
      tamano: {
        chico: 'h-9 rounded-lg px-3 text-sm',
        normal: 'h-11 rounded-xl px-5 text-base',
        grande: 'h-14 rounded-2xl px-8 text-lg',
      },
    },
    defaultVariants: { variante: 'principal', tamano: 'normal' },
  },
)

type Props = ComponentProps<'button'> & VariantProps<typeof variantes> & { asChild?: boolean }

export function Button({ className, variante, tamano, asChild = false, type = 'button', ...props }: Props) {
  const Componente = asChild ? Slot : 'button'
  return (
    <Componente className={cn(variantes({ variante, tamano }), className)} {...(asChild ? {} : { type })} {...props} />
  )
}
