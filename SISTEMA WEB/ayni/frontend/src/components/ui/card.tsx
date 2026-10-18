import type { ComponentProps } from 'react'
import { cn } from '@/lib/utils'

export function Card({ className, ...props }: ComponentProps<'section'>) {
  return <section className={cn('rounded-2xl border border-borde bg-superficie p-5 sm:p-6', className)} {...props} />
}

export function CardTitulo({ className, ...props }: ComponentProps<'h2'>) {
  return <h2 className={cn('text-lg font-extrabold', className)} {...props} />
}
