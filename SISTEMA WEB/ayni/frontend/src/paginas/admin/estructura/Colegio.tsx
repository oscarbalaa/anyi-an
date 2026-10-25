import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { Card } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import type { ErrorApi } from '@/lib/api'
import { useCambio, useInstitucion, type Institucion } from '@/lib/estructura'
import { esquemaColegio } from '@/lib/validaciones'
import { Encabezado } from './Encabezado'

type Datos = Record<string, string>

const CAMPOS_COLEGIO = [
  ['nombre', 'Nombre del colegio', 150],
  ['codigo_modular', 'Código modular', 7],
  ['ugel', 'UGEL', 80],
  ['direccion', 'Dirección', 200],
  ['telefono', 'Teléfono', 9],
  ['correo', 'Correo', 120],
] as const

const PARAMETROS = [
  ['nota_aprobatoria', 'Nota aprobatoria (0 a 20)', 'Promedio mínimo para aprobar (RN-10).'],
  [
    'dias_cierre_bimestre',
    'Días para el cierre de notas',
    'Días después del fin de clases del bimestre (fecha por defecto).',
  ],
  ['umbral_asistencia_alto', 'Asistencia para riesgo ALTO (%)', 'Por debajo de este porcentaje el riesgo es alto.'],
  ['umbral_asistencia_medio', 'Asistencia para riesgo MEDIO (%)', 'Debe ser mayor que el de riesgo alto.'],
  [
    'umbral_promedio_alto',
    'Promedio para riesgo ALTO',
    'Por debajo de este promedio (desaprobatorio) el riesgo es alto.',
  ],
  ['umbral_promedio_medio', 'Promedio para riesgo MEDIO', 'No puede ser menor que el de riesgo alto.'],
  [
    'umbral_pendientes_alto',
    'Entregas pendientes que suman riesgo',
    'Desde esta cantidad de entregas vencidas el riesgo es medio (alto si además el promedio es desaprobatorio).',
  ],
] as const

const ORDEN_ESCALA = ['AD', 'A', 'B', 'C'] as const

// RF-26: datos del colegio, parámetros de notas y riesgo, y equivalencia literal (RN-11)
export function PestanaColegio() {
  const { data, isPending, isError } = useInstitucion()
  if (isPending) return <Esqueleto className="h-96" />
  if (isError) return <EstadoVacio titulo="No se pudieron cargar los datos del colegio" />
  return <Formulario datos={data} />
}

function Formulario({ datos }: { datos: Institucion }) {
  const cambio = useCambio()
  const avisos = useAvisos()
  const inicial: Datos = {}
  for (const [clave, valor] of Object.entries(datos.institucion)) inicial[clave] = valor === null ? '' : String(valor)
  for (const r of datos.escala) {
    inicial[`min_${r.codigo}`] = String(Number(r.nota_minima))
    inicial[`max_${r.codigo}`] = String(Number(r.nota_maxima))
  }
  const {
    register,
    handleSubmit,
    setError,
    clearErrors,
    formState: { errors, isSubmitting },
  } = useForm<Datos>({ resolver: zodResolver(esquemaColegio), defaultValues: inicial })

  const guardar = handleSubmit(async (d) => {
    clearErrors()
    const cuerpo: Record<string, unknown> = {}
    for (const [clave] of CAMPOS_COLEGIO) cuerpo[clave] = d[clave].trim() || null
    for (const [clave] of PARAMETROS) cuerpo[clave] = d[clave].replace(',', '.')
    cuerpo.escala = ORDEN_ESCALA.map((codigo) => ({
      codigo,
      nota_minima: d[`min_${codigo}`].replace(',', '.'),
      nota_maxima: d[`max_${codigo}`].replace(',', '.'),
    }))
    try {
      const r = await cambio.mutateAsync({ metodo: 'PUT', ruta: '/institucion', datos: cuerpo })
      avisos.exito(r.mensaje)
    } catch (e) {
      const error = e as ErrorApi
      const campos = Object.keys(error.errores ?? {})
      // Los errores de la escala llegan como escala.N.campo
      campos.forEach((c) => setError(c.startsWith('escala') ? 'escala' : c, { message: error.errores[c][0] }))
      if (!campos.length) avisos.error(error.message)
      else avisos.error('Revisa los campos marcados.')
    }
  })

  const numero = (v: string) => v.replace(/[^\d.,]/g, '')

  return (
    <form onSubmit={guardar} noValidate className="grid items-start gap-6 xl:grid-cols-2">
      <Card>
        <Encabezado titulo="Datos del colegio" detalle="Aparecen en la boleta y en los reportes." />
        <div className="grid gap-4 sm:grid-cols-2">
          {CAMPOS_COLEGIO.map(([clave, etiqueta, max]) => (
            <Campo
              key={clave}
              etiqueta={etiqueta}
              maxLength={max}
              inputMode={clave === 'telefono' || clave === 'codigo_modular' ? 'numeric' : undefined}
              filtro={clave === 'telefono' || clave === 'codigo_modular' ? (v) => v.replace(/\D/g, '') : undefined}
              className={clave === 'nombre' || clave === 'direccion' ? 'sm:col-span-2' : undefined}
              error={errors[clave]?.message}
              {...register(clave)}
            />
          ))}
        </div>
      </Card>

      <div className="flex flex-col gap-6">
        <Card>
          <Encabezado titulo="Notas y riesgo" detalle="Los usa el cálculo de promedios y de alertas." />
          <div className="grid gap-4 sm:grid-cols-2">
            {PARAMETROS.map(([clave, etiqueta, ayuda]) => (
              <Campo
                key={clave}
                etiqueta={etiqueta}
                inputMode="decimal"
                maxLength={5}
                filtro={numero}
                ayuda={ayuda}
                error={errors[clave]?.message}
                {...register(clave)}
              />
            ))}
          </div>
        </Card>

        <Card>
          <Encabezado
            titulo="Equivalencia literal"
            detalle="De 0 a 20, sin huecos: cada rango empieza 0,01 después del anterior."
          />
          <div className="grid grid-cols-[auto_1fr_1fr] items-end gap-3">
            {ORDEN_ESCALA.map((codigo) => (
              <div key={codigo} className="contents">
                <span className="pb-3 text-lg font-black">{codigo}</span>
                <Campo
                  etiqueta={`${codigo}: desde`}
                  inputMode="decimal"
                  maxLength={5}
                  filtro={numero}
                  {...register(`min_${codigo}`)}
                />
                <Campo
                  etiqueta={`${codigo}: hasta`}
                  inputMode="decimal"
                  maxLength={5}
                  filtro={numero}
                  {...register(`max_${codigo}`)}
                />
              </div>
            ))}
          </div>
          {errors.escala && (
            <p role="alert" className="mt-3 text-sm font-semibold text-peligro">
              {errors.escala.message}
            </p>
          )}
        </Card>

        <Button type="submit" tamano="grande" disabled={isSubmitting} className="self-end">
          {isSubmitting ? 'Guardando…' : 'Guardar datos del colegio'}
        </Button>
      </div>
    </form>
  )
}
