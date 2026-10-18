import type { ReactNode } from 'react'
import { CheckCircle2, Database, LoaderCircle, RefreshCw, Server, XCircle } from 'lucide-react'
import logoAyni from '@/assets/logo-ayni.svg'
import { Button } from '@/components/ui/button'
import { Card, CardTitulo } from '@/components/ui/card'
import { ProveedorDatos } from '@/lib/datos'
import { useSalud } from '@/lib/salud'

// Estado del sistema (/estado): confirma que web, API y base de datos funcionan juntas
export default function EstadoSistema() {
  return (
    <ProveedorDatos>
      <Estado />
    </ProveedorDatos>
  )
}

function Estado() {
  const { data, error, isPending, isFetching, refetch } = useSalud()
  const conectado = data?.base_de_datos.conectada === true

  return (
    <main data-tema="estudiante" className="mx-auto flex min-h-screen max-w-3xl flex-col gap-6 px-4 py-10 sm:px-6">
      <header className="flex flex-col items-center gap-3 text-center">
        <img src={logoAyni} alt="Ayni" width={130} height={62} className="h-16 w-auto" />
        <h1 className="text-3xl font-black text-acento sm:text-4xl">Estado del sistema</h1>
        <p className="text-base text-texto-suave">Verificación de la web, la API y la base de datos</p>
      </header>

      <Card aria-live="polite">
        <div className="flex items-center justify-between gap-3">
          <CardTitulo>Componentes</CardTitulo>
          <Button variante="secundario" onClick={() => refetch()} disabled={isFetching}>
            <RefreshCw className={isFetching ? 'size-4 animate-spin' : 'size-4'} aria-hidden />
            Verificar
          </Button>
        </div>

        {isPending && (
          <p className="mt-4 flex items-center gap-2 text-texto-suave">
            <LoaderCircle className="size-5 animate-spin" aria-hidden /> Consultando la API…
          </p>
        )}

        {error && (
          <p role="alert" className="mt-4 flex items-center gap-2 font-semibold text-peligro">
            <XCircle className="size-5" aria-hidden /> {error.message}
          </p>
        )}

        {data && (
          <ul className="mt-4 grid gap-3 sm:grid-cols-2">
            <Indicador icono={<Server className="size-5" />} titulo="API" valor={`AYNI ${data.version}`} ok />
            <Indicador
              icono={<Database className="size-5" />}
              titulo="Base de datos"
              valor={
                conectado
                  ? [data.base_de_datos.motor, data.base_de_datos.version].filter(Boolean).join(' ') || 'Conectada'
                  : 'Sin conexión'
              }
              ok={conectado}
            />
            <Indicador titulo="Colegio" valor={data.colegio} ok />
            <Indicador titulo="Hora del servidor" valor={formatearHora(data.hora_servidor)} ok />
            {data.php && <Indicador titulo="PHP" valor={data.php} ok />}
            {data.laravel && <Indicador titulo="Laravel" valor={data.laravel} ok />}
          </ul>
        )}
      </Card>
    </main>
  )
}

function Indicador({ icono, titulo, valor, ok }: { icono?: ReactNode; titulo: string; valor: string; ok: boolean }) {
  return (
    <li className="flex items-start gap-3 rounded-xl bg-fondo p-3">
      <span className={ok ? 'text-exito' : 'text-peligro'} aria-hidden>
        {icono ?? (ok ? <CheckCircle2 className="size-5" /> : <XCircle className="size-5" />)}
      </span>
      <span className="min-w-0">
        <span className="block text-xs font-semibold uppercase tracking-wide text-texto-suave">{titulo}</span>
        <span className="block break-words font-semibold" data-testid={`indicador-${titulo}`}>
          {valor}
        </span>
      </span>
    </li>
  )
}

function formatearHora(iso: string) {
  return new Intl.DateTimeFormat('es-PE', {
    dateStyle: 'long',
    timeStyle: 'short',
    timeZone: 'America/Lima',
  }).format(new Date(iso))
}
