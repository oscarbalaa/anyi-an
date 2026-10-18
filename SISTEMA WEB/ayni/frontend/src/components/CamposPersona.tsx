import type { FieldErrors, UseFormRegisterReturn } from 'react-hook-form'
import { Campo } from '@/components/ui/campo'
import { Selector } from '@/components/ui/selector'
import type { CampoPersona } from '@/lib/formularios'
import { filtros } from '@/lib/validaciones'

type Props = {
  registrar: (campo: CampoPersona) => UseFormRegisterReturn
  errores: FieldErrors
  /** Docentes: solo DNI y fecha/sexo opcionales */
  estudiante: boolean
  tipo: string
}

const DOCUMENTOS = [
  { valor: 'DNI', texto: 'DNI' },
  { valor: 'CE', texto: 'Carné de extranjería' },
  { valor: 'PASAPORTE', texto: 'Pasaporte' },
]

/** Datos personales comunes (docs/validaciones.md, "Personas") */
export function CamposPersona({ registrar, errores, estudiante, tipo }: Props) {
  const error = (c: CampoPersona) => errores[c]?.message as string | undefined
  const esDni = tipo === 'DNI'

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      {estudiante ? (
        <Selector etiqueta="Tipo de documento" opciones={DOCUMENTOS} {...registrar('tipo_documento')} />
      ) : (
        <input type="hidden" {...registrar('tipo_documento')} />
      )}
      <Campo
        etiqueta={esDni ? 'DNI' : 'Número de documento'}
        inputMode={esDni ? 'numeric' : undefined}
        maxLength={esDni ? 8 : 12}
        autoComplete="off"
        filtro={esDni ? filtros.dni : (v) => v.toUpperCase().replace(/[^A-Z0-9]/g, '')}
        error={error('numero_documento') ?? error('tipo_documento')}
        className={estudiante ? undefined : 'sm:col-span-2'}
        {...registrar('numero_documento')}
      />
      <Campo
        etiqueta="Nombres"
        maxLength={100}
        autoComplete="off"
        filtro={filtros.nombre}
        error={error('nombres')}
        {...registrar('nombres')}
      />
      <Campo
        etiqueta="Apellidos"
        maxLength={100}
        autoComplete="off"
        filtro={filtros.nombre}
        error={error('apellidos')}
        {...registrar('apellidos')}
      />
      <Campo
        etiqueta={estudiante ? 'Fecha de nacimiento' : 'Fecha de nacimiento (opcional)'}
        type="date"
        error={error('fecha_nacimiento')}
        {...registrar('fecha_nacimiento')}
      />
      <Selector
        etiqueta={estudiante ? 'Sexo' : 'Sexo (opcional)'}
        opciones={[
          { valor: '', texto: 'Elige' },
          { valor: 'F', texto: 'Femenino' },
          { valor: 'M', texto: 'Masculino' },
        ]}
        {...registrar('sexo')}
      />
      {error('sexo') && (
        <p role="alert" className="-mt-2 text-sm font-semibold text-peligro sm:col-start-2">
          {error('sexo')}
        </p>
      )}
      <Campo
        etiqueta="Correo (opcional)"
        type="email"
        inputMode="email"
        maxLength={120}
        autoComplete="off"
        error={error('correo')}
        {...registrar('correo')}
      />
      <Campo
        etiqueta="Celular (opcional)"
        inputMode="numeric"
        maxLength={9}
        autoComplete="off"
        filtro={filtros.celular}
        error={error('celular')}
        {...registrar('celular')}
      />
      <Campo
        etiqueta="Dirección (opcional)"
        maxLength={200}
        autoComplete="off"
        error={error('direccion')}
        className="sm:col-span-2"
        {...registrar('direccion')}
      />
    </div>
  )
}
