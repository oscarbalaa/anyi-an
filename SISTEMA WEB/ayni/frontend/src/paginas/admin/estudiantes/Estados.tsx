import { Insignia } from '@/components/ui/insignia'
import type { EstadoCuenta, EstadoEstudiante, EstadoMatricula } from '@/lib/personas'

const ESTUDIANTE = {
  ACTIVO: ['exito', 'Activo'],
  RETIRADO: ['peligro', 'Retirado'],
  TRASLADADO: ['aviso', 'Trasladado'],
  EGRESADO: ['info', 'Egresado'],
} as const

export function EstadoEstudianteInsignia({ estado, cuenta }: { estado: EstadoEstudiante; cuenta?: EstadoCuenta }) {
  if (cuenta === 'BLOQUEADO') {
    return (
      <Insignia tono="peligro" punto>
        Bloqueado
      </Insignia>
    )
  }
  return (
    <Insignia tono={ESTUDIANTE[estado][0]} punto>
      {ESTUDIANTE[estado][1]}
    </Insignia>
  )
}

const MATRICULA = {
  ACTIVA: ['exito', 'Activa'],
  RETIRADA: ['aviso', 'Retirada'],
  ANULADA: ['neutro', 'Anulada'],
} as const

export function EstadoMatriculaInsignia({ estado }: { estado: EstadoMatricula }) {
  return (
    <Insignia tono={MATRICULA[estado][0]} punto>
      {MATRICULA[estado][1]}
    </Insignia>
  )
}
