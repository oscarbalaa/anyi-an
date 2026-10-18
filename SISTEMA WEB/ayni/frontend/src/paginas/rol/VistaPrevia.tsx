import { zodResolver } from '@hookform/resolvers/zod'
import { BellRing, GraduationCap, ShieldAlert, Users } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Campo } from '@/components/ui/campo'
import { Card, CardTitulo } from '@/components/ui/card'
import { DialogoConfirmacion } from '@/components/ui/dialogo'
import { Cargando, EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia, type Tono } from '@/components/ui/insignia'
import { TablaDatos, type Columna } from '@/components/ui/tabla-datos'
import { TarjetaKpi } from '@/components/ui/tarjeta-kpi'
import { useSesion } from '@/lib/sesion-contexto'
import { filtros, REGLAS } from '@/lib/validaciones'
import { ROLES, type Rol } from '@/layout/roles'

type Fila = {
  id: number
  estudiante: string
  aula: string
  asistencia: number
  promedio: number
  riesgo: 'Bajo' | 'Medio' | 'Alto'
}

// Datos de ejemplo solo para mostrar los componentes; las pantallas reales usan la API
const EJEMPLO: Fila[] = [
  ['Aranda Ruiz, Kevin', '3.º B', 93, 6.6, 'Alto'],
  ['Herrera Campos, Lucía', '3.º B', 98, 15.9, 'Bajo'],
  ['Mori Lazo, Andrés', '3.º B', 90, 11.4, 'Medio'],
  ['Quispe Mendoza, Valeria', '3.º B', 97, 15.4, 'Bajo'],
  ['Ramírez Torres, Diego', '3.º B', 85, 10.2, 'Alto'],
  ['Salas Vega, Paolo', '3.º B', 95, 13.2, 'Bajo'],
  ['Sáenz Palacios, Rodrigo', '3.º A', 78, 7.4, 'Alto'],
  ['Soto Vargas, Camila', '3.º B', 89, 9.2, 'Medio'],
  ['Flores Ccahuana, Mateo', '4.º P', 99, 16.3, 'Bajo'],
  ['Huamán Rojas, Ximena', '2.º A', 96, 14.8, 'Bajo'],
  ['Chávez Ruiz, Gael', '1.º A', 94, 12.9, 'Bajo'],
  ['Torres León, Mía', '5.º A', 91, 12.1, 'Medio'],
].map(
  ([estudiante, aula, asistencia, promedio, riesgo], i) =>
    ({ id: i + 1, estudiante, aula, asistencia, promedio, riesgo }) as Fila,
)

const saludo = (hora = new Date().getHours()) =>
  hora < 12 ? 'Buenos días' : hora < 19 ? 'Buenas tardes' : 'Buenas noches'

const TONO_RIESGO: Record<Fila['riesgo'], Tono> = { Bajo: 'exito', Medio: 'aviso', Alto: 'peligro' }

const columnas: Columna<Fila>[] = [
  {
    clave: 'estudiante',
    titulo: 'Estudiante',
    celda: (f) => <span className="font-semibold">{f.estudiante}</span>,
    principal: true,
  },
  { clave: 'aula', titulo: 'Aula', celda: (f) => f.aula },
  { clave: 'asistencia', titulo: 'Asistencia', celda: (f) => `${f.asistencia} %` },
  {
    clave: 'promedio',
    titulo: 'Promedio',
    celda: (f) => <span className={f.promedio < 11 ? 'font-bold text-peligro' : ''}>{f.promedio.toFixed(1)}</span>,
  },
  {
    clave: 'riesgo',
    titulo: 'Riesgo',
    celda: (f) => (
      <Insignia tono={TONO_RIESGO[f.riesgo]} punto>
        {f.riesgo}
      </Insignia>
    ),
  },
]

const esquema = z.object({
  dni: z.string().regex(REGLAS.dni, 'El DNI debe tener 8 dígitos.'),
  nombres: z
    .string()
    .trim()
    .min(2, 'Los nombres deben tener al menos 2 letras.')
    .regex(/^[\p{L}' -]+$/u, 'Los nombres solo pueden tener letras, espacios, apóstrofo o guion.'),
})

export default function VistaPrevia({ rol }: { rol: Rol }) {
  const avisos = useAvisos()
  const [dialogo, setDialogo] = useState(false)
  const { usuario } = useSesion()
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<z.infer<typeof esquema>>({ resolver: zodResolver(esquema), defaultValues: { dni: '', nombres: '' } })

  return (
    <div className="mx-auto flex max-w-6xl flex-col gap-6">
      <div className="rounded-2xl bg-primario-suave px-5 py-4 sm:px-6">
        <p className="text-xl font-extrabold text-primario-oscuro">Vista previa del diseño · {ROLES[rol].etiqueta}</p>
        <p className="mt-1 text-texto">
          Así se ven los componentes con los colores de este rol. Los datos son de ejemplo; el inicio real llega en el
          Módulo 12.
        </p>
      </div>

      <h1 className="text-3xl font-black sm:text-4xl">
        {saludo()}, {usuario?.nombre.split(' ')[0]}
      </h1>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <TarjetaKpi icono={<Users />} valor={148} etiqueta="Estudiantes matriculados" />
        <TarjetaKpi icono={<GraduationCap />} valor={15} etiqueta="Docentes" tono="exito" />
        <TarjetaKpi icono={<BellRing />} valor={7} etiqueta="Alertas abiertas" tono="peligro" />
        <TarjetaKpi icono={<ShieldAlert />} valor={2} etiqueta="Cuentas bloqueadas" tono="aviso" />
      </div>

      <Card>
        <CardTitulo>Tabla con búsqueda y paginación</CardTitulo>
        <p className="mt-1 mb-4 text-texto-suave">
          Prueba buscar "andrés" (sin tilde también funciona). En celular cada fila se ve como tarjeta.
        </p>
        <TablaDatos
          filas={EJEMPLO}
          columnas={columnas}
          claveFila={(f) => f.id}
          textoBusqueda={(f) => `${f.estudiante} ${f.aula}`}
          porPagina={5}
          descripcion="Estudiantes de ejemplo"
        />
      </Card>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardTitulo>Formulario con validación</CardTitulo>
          <form
            noValidate
            className="mt-4 flex flex-col gap-4"
            onSubmit={handleSubmit(() => avisos.exito('Los datos son válidos.'))}
          >
            <Campo
              etiqueta="DNI"
              inputMode="numeric"
              maxLength={8}
              filtro={filtros.dni}
              error={errors.dni?.message}
              ayuda="Solo números: las letras no se pueden escribir."
              {...register('dni')}
            />
            <Campo etiqueta="Nombres" maxLength={100} error={errors.nombres?.message} {...register('nombres')} />
            <Button type="submit" className="self-start">
              Validar
            </Button>
          </form>
        </Card>

        <Card className="flex flex-col gap-5">
          <CardTitulo>Botones, insignias y avisos</CardTitulo>
          <div className="flex flex-wrap gap-2">
            <Button onClick={() => avisos.exito('Cambios guardados.')}>Principal</Button>
            <Button variante="secundario" onClick={() => avisos.info('Esto es un aviso informativo.')}>
              Secundario
            </Button>
            <Button variante="fantasma" onClick={() => avisos.aviso('Revisa los datos marcados.')}>
              Fantasma
            </Button>
            <Button variante="peligro" onClick={() => setDialogo(true)}>
              Eliminar…
            </Button>
          </div>
          <div className="flex flex-wrap gap-2">
            <Insignia tono="exito" punto>
              Entregado
            </Insignia>
            <Insignia tono="aviso" punto>
              Tardío
            </Insignia>
            <Insignia tono="peligro" punto>
              Crítico
            </Insignia>
            <Insignia tono="info">Publicado</Insignia>
            <Insignia tono="neutro">Borrador</Insignia>
            <Insignia tono="primario">III Bimestre</Insignia>
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="rounded-xl border border-dashed border-borde">
              <EstadoVacio titulo="Sin alertas" descripcion="Cuando haya alertas aparecerán aquí." />
            </div>
            <div className="flex flex-col gap-2 rounded-xl border border-dashed border-borde p-4">
              <Cargando texto="Cargando datos…" />
              <Esqueleto className="h-4" />
              <Esqueleto className="h-4 w-2/3" />
            </div>
          </div>
        </Card>
      </div>

      <DialogoConfirmacion
        abierto={dialogo}
        titulo="¿Eliminar el registro?"
        textoConfirmar="Sí, eliminar"
        peligro
        onCancelar={() => setDialogo(false)}
        onConfirmar={() => {
          setDialogo(false)
          avisos.exito('Registro eliminado (ejemplo).')
        }}
      >
        Esta acción no se puede deshacer.
      </DialogoConfirmacion>
    </div>
  )
}
