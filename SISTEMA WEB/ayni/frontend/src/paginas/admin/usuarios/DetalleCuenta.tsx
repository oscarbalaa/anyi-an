import { Ban, Check, Copy, Info, KeyRound, LockKeyhole, Pencil, UserCheck } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { DialogoConfirmacion, Ventana } from '@/components/ui/dialogo'
import { EstadoVacio, Esqueleto } from '@/components/ui/estados'
import { Insignia } from '@/components/ui/insignia'
import type { ErrorApi } from '@/lib/api'
import { useSesion } from '@/lib/sesion-contexto'
import {
  fechaHora,
  useCambiarEstado,
  useContrasenaTemporal,
  useUsuario,
  type DetalleUsuario,
  type EstadoCuenta,
  type ResultadoTemporal,
} from '@/lib/usuarios'
import { FormularioUsuario } from './FormularioUsuario'

const ACCIONES: Record<string, string> = {
  CREAR: 'Cuenta creada',
  ACTUALIZAR: 'Datos editados',
  ACTUALIZAR_PERFIL: 'Editó su perfil',
  ACTIVAR: 'Cuenta activada',
  INACTIVAR: 'Cuenta desactivada',
  BLOQUEO: 'Bloqueada por intentos fallidos',
  DESBLOQUEAR: 'Desbloqueada con contraseña temporal',
  CONTRASENA_TEMPORAL: 'Contraseña temporal generada',
  CAMBIAR_CONTRASENA: 'Cambió su contraseña',
  RESTABLECER_CONTRASENA: 'Restableció su contraseña por correo',
}

export function EstadoCuentaInsignia({ estado }: { estado: EstadoCuenta }) {
  const config = {
    ACTIVO: { tono: 'exito', texto: 'Activo' },
    BLOQUEADO: { tono: 'peligro', texto: 'Bloqueado' },
    INACTIVO: { tono: 'neutro', texto: 'Inactivo' },
  } as const
  return (
    <Insignia tono={config[estado].tono} punto>
      {config[estado].texto}
    </Insignia>
  )
}

type Accion = 'temporal' | 'estado' | 'editar' | null

/** Panel derecho del mockup admin/04: estado de la cuenta y sus acciones */
// La clave se muestra desde la página: si el panel se vuelve a montar (girar el celular), no se pierde
export function DetalleCuenta({ id, onClave }: { id: number; onClave: (r: ResultadoTemporal) => void }) {
  const { data: u, isPending, isError } = useUsuario(id)
  const { usuario: yo } = useSesion()
  const [accion, setAccion] = useState<Accion>(null)
  const temporal = useContrasenaTemporal()
  const estado = useCambiarEstado()
  const avisos = useAvisos()

  if (isPending) return <Esqueleto className="h-96" />
  if (isError) return <EstadoVacio titulo="No se pudo cargar la cuenta" />

  const esPropia = yo?.id === u.id
  const titulo = { ACTIVO: 'Cuenta activa', BLOQUEADO: 'Cuenta bloqueada', INACTIVO: 'Cuenta inactiva' }[u.estado]
  const fallo = (e: unknown) => avisos.error((e as ErrorApi).message)

  const generar = () =>
    temporal.mutate(u.id, {
      onSuccess: (r) => {
        setAccion(null)
        onClave(r)
      },
      onError: (e) => {
        setAccion(null)
        fallo(e)
      },
    })

  const alternarEstado = () =>
    estado.mutate(
      { id: u.id, estado: u.estado === 'INACTIVO' ? 'ACTIVO' : 'INACTIVO' },
      {
        onSuccess: (r) => {
          setAccion(null)
          avisos.exito(r.mensaje)
        },
        onError: (e) => {
          setAccion(null)
          fallo(e)
        },
      },
    )

  return (
    <Card className="flex flex-col gap-4" aria-label={`Cuenta de ${u.nombre}`}>
      <div>
        <h2 className="text-2xl font-black">{titulo}</h2>
        <p className="text-lg text-texto-suave">
          {u.nombre} · {u.rol_nombre}
        </p>
      </div>

      {u.estado === 'BLOQUEADO' && (
        <p className="flex items-center gap-3 rounded-xl border border-peligro/30 bg-peligro/8 p-3 font-semibold">
          <LockKeyhole className="size-6 shrink-0 text-peligro" aria-hidden />
          <span>
            Bloqueada tras <strong className="text-peligro">{u.intentos_fallidos} intentos fallidos</strong>.
            {u.bloqueado_hasta && ` Se libera sola: ${fechaHora(u.bloqueado_hasta)}.`}
          </span>
        </p>
      )}
      {u.debe_cambiar_contrasena && u.estado !== 'BLOQUEADO' && (
        <p className="rounded-xl bg-aviso/10 p-3 text-sm">Tiene una contraseña temporal que aún no cambia.</p>
      )}

      <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2">
        <Dato etiqueta="Usuario">{u.usuario}</Dato>
        <Dato etiqueta="Correo">{u.correo ?? 'Sin correo'}</Dato>
        <Dato etiqueta="Celular">{u.celular ?? '—'}</Dato>
        <Dato etiqueta="Documento">{u.documento ?? '—'}</Dato>
        {u.detalle && <Dato etiqueta={u.rol === 'ADMIN' ? 'Cargo' : 'Detalle'}>{u.detalle}</Dato>}
        <Dato etiqueta="Último acceso">{fechaHora(u.ultimo_acceso)}</Dato>
        <Dato etiqueta="Creado por">{u.creado_por ?? 'Datos iniciales'}</Dato>
        {u.actualizado_por && <Dato etiqueta="Modificado por">{u.actualizado_por}</Dato>}
      </dl>

      <hr className="border-borde" />

      {esPropia ? (
        <p className="flex gap-2 text-sm text-texto-suave">
          <Info className="size-5 shrink-0" aria-hidden /> Es tu cuenta: tus datos y tu contraseña se cambian en Mi
          perfil.
        </p>
      ) : (
        <div className="flex flex-col gap-2">
          <Button
            variante={u.estado === 'BLOQUEADO' ? 'principal' : 'secundario'}
            onClick={() => setAccion('temporal')}
            className="h-auto min-h-11 py-2"
          >
            <KeyRound className="size-5 shrink-0" aria-hidden />
            {u.estado === 'BLOQUEADO' ? 'Desbloquear y generar contraseña temporal' : 'Generar contraseña temporal'}
          </Button>
          <Button variante="secundario" onClick={() => setAccion('editar')}>
            <Pencil className="size-5" aria-hidden /> Editar datos
          </Button>
          {u.estado !== 'BLOQUEADO' && (
            <Button variante="secundario" onClick={() => setAccion('estado')}>
              {u.estado === 'INACTIVO' ? (
                <>
                  <UserCheck className="size-5" aria-hidden /> Activar cuenta
                </>
              ) : (
                <>
                  <Ban className="size-5" aria-hidden /> Desactivar cuenta
                </>
              )}
            </Button>
          )}
          <p className="mt-1 flex gap-2 text-sm text-texto-suave">
            <Info className="size-5 shrink-0" aria-hidden /> El usuario deberá cambiar la contraseña temporal en su
            primer ingreso.
          </p>
        </div>
      )}

      {u.historial.length > 0 && (
        <details className="group">
          <summary className="cursor-pointer font-bold">Historial de la cuenta</summary>
          <ol className="mt-2 flex flex-col gap-2 text-sm">
            {u.historial.map((h, i) => (
              <li key={i} className="border-l-2 border-borde pl-3">
                <span className="font-semibold">{ACCIONES[h.accion] ?? h.accion}</span>
                <span className="block text-texto-suave">
                  {fechaHora(h.fecha)} · {h.por}
                </span>
              </li>
            ))}
          </ol>
        </details>
      )}

      <DialogoConfirmacion
        abierto={accion === 'temporal'}
        titulo={u.estado === 'BLOQUEADO' ? '¿Desbloquear la cuenta?' : '¿Generar una contraseña temporal?'}
        textoConfirmar="Sí, generar"
        ocupado={temporal.isPending}
        onConfirmar={generar}
        onCancelar={() => setAccion(null)}
      >
        La contraseña actual de <strong>{u.nombre}</strong> dejará de servir y se cerrarán sus sesiones abiertas.
        {u.correo ? ` La nueva se enviará a ${u.correo}.` : ' No tiene correo: tendrás que entregársela.'}
      </DialogoConfirmacion>

      <DialogoConfirmacion
        abierto={accion === 'estado'}
        titulo={u.estado === 'INACTIVO' ? '¿Activar la cuenta?' : '¿Desactivar la cuenta?'}
        textoConfirmar={u.estado === 'INACTIVO' ? 'Sí, activar' : 'Sí, desactivar'}
        peligro={u.estado !== 'INACTIVO'}
        ocupado={estado.isPending}
        onConfirmar={alternarEstado}
        onCancelar={() => setAccion(null)}
      >
        {u.estado === 'INACTIVO'
          ? `${u.nombre} podrá volver a ingresar con su contraseña.`
          : `${u.nombre} no podrá ingresar y se cerrarán sus sesiones abiertas. Podrás activarla después.`}
      </DialogoConfirmacion>

      <Ventana
        abierto={accion === 'editar'}
        titulo="Editar datos"
        onCerrar={() => setAccion(null)}
        conCerrar
        className="w-[min(94vw,40rem)]"
      >
        {accion === 'editar' && <FormularioUsuario usuario={u} onListo={() => setAccion(null)} />}
      </Ventana>
    </Card>
  )
}

function Dato({ etiqueta, children }: { etiqueta: string; children: ReactNode }) {
  return (
    <>
      <dt className="font-bold">{etiqueta}</dt>
      <dd className="min-w-0 break-words">{children}</dd>
    </>
  )
}

/** Muestra la contraseña temporal una sola vez, para copiarla o dictarla */
export function ResultadoClave({
  resultado,
  onCerrar,
}: {
  resultado:
    | (Pick<ResultadoTemporal, 'contrasena' | 'correo_enviado'> & {
        usuario: Pick<DetalleUsuario, 'nombre' | 'usuario' | 'correo'>
      })
    | null
  onCerrar: () => void
}) {
  const [copiada, setCopiada] = useState(false)
  const copiar = async () => {
    try {
      await navigator.clipboard.writeText(resultado?.contrasena ?? '')
      setCopiada(true)
    } catch {
      setCopiada(false)
    }
  }

  return (
    <Ventana abierto={resultado !== null} titulo="Contraseña temporal" onCerrar={onCerrar}>
      {resultado && (
        <>
          <p>
            Usuario de <strong>{resultado.usuario.nombre}</strong>: <strong>{resultado.usuario.usuario}</strong>
          </p>
          <div className="flex items-center gap-2">
            <code
              className="flex-1 rounded-xl bg-primario-suave px-4 py-3 text-center text-2xl font-black tracking-widest text-primario-oscuro"
              data-testid="clave-temporal"
            >
              {resultado.contrasena}
            </code>
            <Button variante="secundario" onClick={copiar} aria-label="Copiar la contraseña">
              {copiada ? <Check className="size-5" aria-hidden /> : <Copy className="size-5" aria-hidden />}
            </Button>
          </div>
          <p role="status" className="text-texto-suave">
            {resultado.correo_enviado
              ? `También se envió a ${resultado.usuario.correo}.`
              : 'La cuenta no tiene correo: entrégasela al usuario. No se volverá a mostrar.'}{' '}
            Deberá cambiarla en su primer ingreso.
          </p>
          <Button onClick={onCerrar} className="self-end">
            Entendido
          </Button>
        </>
      )}
    </Ventana>
  )
}
