import { Navigate, useNavigate } from 'react-router'
import { FormCambioContrasena } from '@/components/FormCambioContrasena'
import { Cargando } from '@/components/ui/estados'
import type { Usuario } from '@/lib/sesion'
import { useSesion } from '@/lib/sesion-contexto'
import { ROLES } from '@/layout/roles'
import { PantallaAcceso } from './PantallaAcceso'

// RF-07: la contraseña temporal se cambia antes de usar el sistema
export default function CambiarContrasena() {
  const { usuario, verificando } = useSesion()
  if (verificando) return <Cargando texto="Verificando tu sesión…" />
  if (!usuario) return <Navigate to="/ingresar" replace />
  // Sin contraseña temporal (o recién cambiada) pasa a su inicio; el cambio normal está en Mi perfil
  if (!usuario.debe_cambiar_contrasena) return <Navigate to={ROLES[usuario.acceso].base} replace />
  return <Pantalla usuario={usuario} />
}

function Pantalla({ usuario }: { usuario: Usuario }) {
  const sesion = useSesion()
  const navegar = useNavigate()
  const rol = usuario.acceso

  return (
    <PantallaAcceso
      rol={rol}
      titulo="Crea tu contraseña"
      subtitulo={`Hola, ${usuario.nombre}. Por seguridad, cambia la contraseña temporal antes de continuar.`}
      volver={null}
    >
      <FormCambioContrasena
        usuario={usuario}
        etiquetaActual="Contraseña temporal"
        textoBoton="Guardar y continuar"
        onGuardado={() => navegar(ROLES[rol].base, { replace: true })}
      >
        <button
          type="button"
          onClick={() => sesion.salir().then(() => navegar(ROLES[rol].ingreso, { replace: true }))}
          className="mx-auto cursor-pointer font-bold text-acento-texto hover:underline"
        >
          Cerrar sesión
        </button>
      </FormCambioContrasena>
    </PantallaAcceso>
  )
}
