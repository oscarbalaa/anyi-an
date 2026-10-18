import { Search } from 'lucide-react'
import { useEffect, useId, useState } from 'react'

/** Buscador con espera de 300 ms antes de avisar el texto */
export function Buscador({ placeholder, onBuscar }: { placeholder: string; onBuscar: (texto: string) => void }) {
  const [texto, setTexto] = useState('')
  const id = useId()

  useEffect(() => {
    const espera = window.setTimeout(() => onBuscar(texto.trim()), 300)
    return () => window.clearTimeout(espera)
  }, [texto, onBuscar])

  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="font-bold">
        Buscar
      </label>
      <div className="relative">
        <Search
          className="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-texto-suave"
          aria-hidden
        />
        <input
          id={id}
          type="search"
          value={texto}
          maxLength={100}
          placeholder={placeholder}
          onChange={(e) => setTexto(e.target.value)}
          className="h-11 w-full rounded-xl border border-borde bg-superficie pr-3 pl-10 outline-none focus:border-accion focus:ring-3 focus:ring-primario/20"
        />
      </div>
    </div>
  )
}
