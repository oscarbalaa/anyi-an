import borde1100Admin from '@/assets/img/borde-admin-1100.webp'
import borde600Admin from '@/assets/img/borde-admin-600.webp'
import borde1100Docente from '@/assets/img/borde-docente-1100.webp'
import borde600Docente from '@/assets/img/borde-docente-600.webp'
import borde1100Estudiante from '@/assets/img/borde-estudiante-1100.webp'
import borde600Estudiante from '@/assets/img/borde-estudiante-600.webp'
import cesped1600 from '@/assets/img/cesped-1600.webp'
import cesped800 from '@/assets/img/cesped-800.webp'
import colinas1100 from '@/assets/img/colinas-1100.webp'
import colinas1672 from '@/assets/img/colinas-1672.webp'
import colinas720 from '@/assets/img/colinas-720.webp'
import piki360 from '@/assets/img/piki-360.webp'
import piki640 from '@/assets/img/piki-640.webp'
import tambo360 from '@/assets/img/tambo-360.webp'
import tambo640 from '@/assets/img/tambo-640.webp'

export type Imagen = { src: string; srcSet: string; width: number; height: number }

const imagen = (anchos: [string, number][], alto: number, anchoBase: number): Imagen => ({
  src: anchos[anchos.length - 1][0],
  srcSet: anchos.map(([url, w]) => `${url} ${w}w`).join(', '),
  width: anchoBase,
  height: alto,
})

export const IMG = {
  colinas: imagen(
    [
      [colinas1672, 1672],
      [colinas1100, 1100],
      [colinas720, 720],
    ],
    655,
    1672,
  ),
  piki: imagen(
    [
      [piki640, 640],
      [piki360, 360],
    ],
    611,
    640,
  ),
  tambo: imagen(
    [
      [tambo640, 640],
      [tambo360, 360],
    ],
    681,
    640,
  ),
  cesped: imagen(
    [
      [cesped1600, 1600],
      [cesped800, 800],
    ],
    464,
    1600,
  ),
  bordeEstudiante: imagen(
    [
      [borde1100Estudiante, 1100],
      [borde600Estudiante, 600],
    ],
    330,
    1100,
  ),
  bordeDocente: imagen(
    [
      [borde1100Docente, 1100],
      [borde600Docente, 600],
    ],
    313,
    1100,
  ),
  bordeAdmin: imagen(
    [
      [borde1100Admin, 1100],
      [borde600Admin, 600],
    ],
    436,
    1100,
  ),
}
