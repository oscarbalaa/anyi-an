import { Insignia } from '@/components/ui/insignia'
import type { EstadoCuenta, EstadoDocente } from '@/lib/personas'

/** Estado laboral del docente; si su cuenta está bloqueada, se muestra eso (mockup admin/08) */
export function EstadoDocenteInsignia({ estado, cuenta }: { estado: EstadoDocente; cuenta: EstadoCuenta }) {
  if (cuenta === 'BLOQUEADO') {
    return (
      <Insignia tono="peligro" punto>
        Bloqueado
      </Insignia>
    )
  }
  const config = {
    ACTIVO: ['exito', 'Activo'],
    LICENCIA: ['aviso', 'Licencia'],
    INACTIVO: ['neutro', 'Inactivo'],
  } as const
  return (
    <Insignia tono={config[estado][0]} punto>
      {config[estado][1]}
    </Insignia>
  )
}
