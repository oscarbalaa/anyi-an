import { BellRing, Building2, Clock, GraduationCap, Info, TrendingUp } from 'lucide-react'
import { useCallback, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import { Pestanas } from '@/components/ui/pestanas'
import { Selector } from '@/components/ui/selector'
import { TablaDatos } from '@/components/ui/tabla-datos'
import { TarjetaKpi } from '@/components/ui/tarjeta-kpi'
import { ESTADO, NIVEL, useAlertas, type RolAlertas } from '@/lib/alertas'
import { hace } from '@/lib/notificaciones'
import { Buscador } from '../admin/estudiantes/Buscador'
import { AlertasAdministrativas } from './AlertasAdministrativas'
import { DetalleAlerta } from './DetalleAlerta'

// CU-25 (mockups admin 14 y 15, docente 09)
export default function Alertas({ rol }: { rol: RolAlertas }) {
  const [parametros, setParametros] = useSearchParams()
  const id = Number(parametros.get('id')) || null
  if (id) return <DetalleAlerta key={id} rol={rol} id={id} />

  if (rol === 'docente')
    return (
      <div className="mx-auto flex max-w-7xl flex-col gap-6">
        <header>
          <h1 className="text-3xl font-black sm:text-4xl">Alertas asignadas</h1>
          <p className="mt-1 text-lg text-texto-suave">Seguimientos académicos a tu cargo.</p>
        </header>
        <p className="flex items-start gap-3 rounded-xl border border-info/25 bg-info/6 p-4" role="note">
          <Info className="mt-0.5 size-5 shrink-0 text-info" aria-hidden />
          Usa estas señales para priorizar el acompañamiento; revisa siempre el contexto del estudiante.
        </p>
        <ListaEstudiantiles rol="docente" />
      </div>
    )

  const pestana = parametros.get('pestana') === 'administrativas' ? 'administrativas' : 'estudiantiles'
  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <header>
        <h1 className="text-3xl font-black sm:text-4xl">Alertas</h1>
        <p className="mt-1 text-lg text-texto-suave">Seguimiento de estudiantes y de la operación del colegio.</p>
      </header>
      <Pestanas
        etiqueta="Tipo de alertas"
        activa={pestana}
        onCambiar={(p) => setParametros({ pestana: p }, { replace: true })}
        pestanas={[
          { id: 'estudiantiles', texto: 'Estudiantiles · riesgo', icono: GraduationCap },
          { id: 'administrativas', texto: 'Administrativas', icono: Building2 },
        ]}
      >
        {pestana === 'estudiantiles' ? <ListaEstudiantiles rol="admin" /> : <AlertasAdministrativas />}
      </Pestanas>
    </div>
  )
}

function ListaEstudiantiles({ rol }: { rol: RolAlertas }) {
  const [buscar, setBuscar] = useState('')
  const [nivel, setNivel] = useState('')
  const [estado, setEstado] = useState('abiertas')
  const [encargado, setEncargado] = useState('mias')
  const [aula, setAula] = useState('')
  const [pagina, setPagina] = useState(1)
  const { data, isPending, isError, refetch } = useAlertas(rol, {
    buscar,
    nivel,
    estado,
    encargado: rol === 'docente' ? encargado : '',
    aula_id: aula,
    pagina,
  })
  const alBuscar = useCallback((t: string) => {
    setBuscar(t)
    setPagina(1)
  }, [])
  const filtro = (cambiar: (v: string) => void) => (e: { target: { value: string } }) => {
    cambiar(e.target.value)
    setPagina(1)
  }
  const r = data?.resumen

  return (
    <div className="flex flex-col gap-6">
      {r && (
        <div className="grid gap-4 sm:grid-cols-3">
          <TarjetaKpi icono={<BellRing />} valor={r.abiertas} etiqueta="Abiertas" tono="peligro" />
          <TarjetaKpi icono={<Clock />} valor={r.sin_intervencion} etiqueta="Sin primera intervención" tono="aviso" />
          <TarjetaKpi icono={<TrendingUp />} valor={r.en_seguimiento} etiqueta="En seguimiento" tono="info" />
        </div>
      )}
      <Card className="flex flex-col gap-4">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))]">
          <div className="sm:col-span-2 xl:col-span-1">
            <Buscador placeholder="Nombre o código del estudiante" onBuscar={alBuscar} />
          </div>
          <Selector
            etiqueta="Nivel"
            value={nivel}
            onChange={filtro(setNivel)}
            opciones={[
              { valor: '', texto: 'Todos' },
              { valor: 'ALTO', texto: 'Alto' },
              { valor: 'MEDIO', texto: 'Medio' },
            ]}
          />
          <Selector
            etiqueta="Estado"
            value={estado}
            onChange={filtro(setEstado)}
            opciones={[
              { valor: 'abiertas', texto: `Abiertas${r ? ` (${r.abiertas})` : ''}` },
              { valor: 'NUEVA', texto: 'Nuevas' },
              { valor: 'EN_SEGUIMIENTO', texto: 'En seguimiento' },
              { valor: 'CERRADA', texto: 'Cerradas' },
              { valor: 'DESCARTADA', texto: 'Descartadas' },
              { valor: 'todas', texto: 'Todas' },
            ]}
          />
          {rol === 'docente' ? (
            <Selector
              etiqueta="Encargado"
              value={encargado}
              onChange={filtro(setEncargado)}
              opciones={[
                { valor: 'mias', texto: 'A mi cargo' },
                { valor: 'todas', texto: 'De mis aulas' },
              ]}
            />
          ) : (
            <Selector
              etiqueta="Aula"
              value={aula}
              onChange={filtro(setAula)}
              opciones={[
                { valor: '', texto: 'Todas' },
                ...(data?.aulas ?? []).map((a) => ({ valor: String(a.id), texto: a.nombre })),
              ]}
            />
          )}
        </div>
        {isPending ? (
          <Esqueleto className="h-64" />
        ) : isError ? (
          <EstadoVacio
            titulo="No se pudo cargar las alertas"
            accion={<Button onClick={() => refetch()}>Reintentar</Button>}
          />
        ) : (
          <TablaDatos
            filas={data.datos}
            claveFila={(a) => a.id}
            descripcion="Alertas de riesgo"
            vacio={
              estado === 'abiertas' && !buscar && !nivel
                ? 'No hay alertas abiertas. ¡Todo al día!'
                : 'Ninguna alerta coincide con los filtros.'
            }
            paginacion={{ pagina: data.pagina, total: data.total, porPagina: data.por_pagina, onCambiar: setPagina }}
            columnas={[
              {
                clave: 'estudiante',
                titulo: 'Estudiante',
                principal: true,
                celda: (a) => (
                  <span className="block leading-tight">
                    <span className="block font-bold">{a.estudiante.nombre}</span>
                    <span className="text-sm text-texto-suave">
                      {a.aula} · {hace(a.creada).toLowerCase()}
                    </span>
                  </span>
                ),
              },
              {
                clave: 'riesgo',
                titulo: 'Riesgo',
                celda: (a) => (
                  <Insignia tono={NIVEL[a.nivel].tono} punto>
                    {NIVEL[a.nivel].texto} · {a.probabilidad} %
                  </Insignia>
                ),
              },
              {
                clave: 'factor',
                titulo: 'Factor principal',
                celda: (a) => (
                  <span className="block max-w-xs text-sm text-texto-suave">{a.factor_principal ?? '—'}</span>
                ),
              },
              {
                clave: 'estado',
                titulo: 'Estado',
                celda: (a) => (
                  <Insignia tono={ESTADO[a.estado].tono} punto>
                    {ESTADO[a.estado].texto}
                  </Insignia>
                ),
              },
              {
                clave: 'encargado',
                titulo: 'Encargado',
                celda: (a) => (
                  <span className="block leading-tight">
                    <span className="block">{a.responsable}</span>
                    <span className="text-sm text-texto-suave">
                      {a.intervenciones === 1 ? '1 intervención' : `${a.intervenciones} intervenciones`}
                    </span>
                  </span>
                ),
              },
              {
                clave: 'accion',
                titulo: 'Acción',
                celda: (a) => (
                  <Button asChild variante="secundario" tamano="chico">
                    <Link to={`/${rol}/alertas?id=${a.id}`} aria-label={`Abrir la alerta de ${a.estudiante.nombre}`}>
                      Abrir
                    </Link>
                  </Button>
                ),
              },
            ]}
          />
        )}
        {rol === 'docente' && (
          <p className="flex items-center gap-2 text-sm text-texto-suave">
            <Info className="size-4 shrink-0" aria-hidden />
            Solo se muestran alertas de estudiantes de tus cursos, tus tutorías y tus coordinaciones.
          </p>
        )}
      </Card>
    </div>
  )
}
