import type { ComponentProps } from 'react'
import type { Imagen } from '@/lib/imagenes'

type Props = Omit<ComponentProps<'img'>, 'src' | 'srcSet' | 'width' | 'height'> & {
  imagen: Imagen
  /** Ancho con el que se muestra, para que el navegador elija el archivo justo */
  sizes: string
}

export function Ilustracion({ imagen, sizes, alt = '', loading = 'lazy', ...props }: Props) {
  return (
    <img
      src={imagen.src}
      srcSet={imagen.srcSet}
      sizes={sizes}
      width={imagen.width}
      height={imagen.height}
      alt={alt}
      loading={loading}
      decoding="async"
      {...props}
    />
  )
}
