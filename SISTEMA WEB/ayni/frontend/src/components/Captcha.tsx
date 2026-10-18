import { RefreshCw } from 'lucide-react'
import type { UseFormRegisterReturn } from 'react-hook-form'
import { Campo } from '@/components/ui/campo'
import type { EstadoCaptcha } from '@/lib/captcha'
import { filtros } from '@/lib/validaciones'
import { cn } from '@/lib/utils'

type Props = { captcha: EstadoCaptcha; registro: UseFormRegisterReturn; error?: string }

export function Captcha({ captcha, registro, error }: Props) {
  return (
    <div className="flex flex-col gap-3">
      <div className="flex items-center gap-3">
        <div
          className={cn(
            'grid h-16 w-50 shrink-0 place-items-center overflow-hidden rounded-xl border border-borde bg-[#fdf7f0]',
            captcha.cargando && 'animate-pulse',
          )}
        >
          {captcha.imagen ? (
            <img
              src={captcha.imagen}
              width={200}
              height={64}
              alt="Código de verificación: escribe los caracteres que ves en la imagen"
              data-testid="captcha-imagen"
            />
          ) : (
            <span className="px-2 text-center text-sm text-texto-suave">{captcha.error ?? 'Cargando imagen…'}</span>
          )}
        </div>
        <button
          type="button"
          onClick={captcha.renovar}
          disabled={captcha.cargando}
          className="grid size-12 cursor-pointer place-items-center rounded-xl border border-borde text-texto-suave hover:bg-primario-suave hover:text-texto disabled:cursor-wait"
          aria-label="Cambiar la imagen del código"
          title="Cambiar la imagen"
        >
          <RefreshCw className={cn('size-5', captcha.cargando && 'animate-spin')} aria-hidden />
        </button>
      </div>
      <Campo
        etiqueta="Código de verificación"
        placeholder="Escribe los 5 caracteres"
        maxLength={5}
        autoComplete="off"
        autoCapitalize="characters"
        spellCheck={false}
        filtro={filtros.captcha}
        error={error}
        {...registro}
      />
    </div>
  )
}
