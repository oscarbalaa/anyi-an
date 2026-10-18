import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { filtros } from '@/lib/validaciones'
import { useAvisos } from './avisos-contexto'
import { ProveedorAvisos } from './avisos'
import { Campo } from './campo'
import { DialogoConfirmacion } from './dialogo'
import { TablaDatos, type Columna } from './tabla-datos'

describe('Campo', () => {
  it('relaciona la etiqueta con el campo y anuncia el error', () => {
    render(<Campo etiqueta="DNI" error="El DNI debe tener 8 dígitos." />)

    const campo = screen.getByLabelText('DNI')
    expect(campo).toHaveAttribute('aria-invalid', 'true')
    expect(campo).toHaveAccessibleDescription('El DNI debe tener 8 dígitos.')
    expect(screen.getByRole('alert')).toHaveTextContent('El DNI debe tener 8 dígitos.')
  })

  it('no deja escribir lo que el filtro rechaza', async () => {
    render(<Campo etiqueta="DNI" filtro={filtros.dni} />)

    await userEvent.type(screen.getByLabelText('DNI'), '12ab34')
    expect(screen.getByLabelText('DNI')).toHaveValue('1234')
  })

  it('muestra y oculta la contraseña', async () => {
    render(<Campo etiqueta="Contraseña" type="password" />)

    expect(screen.getByLabelText('Contraseña')).toHaveAttribute('type', 'password')
    await userEvent.click(screen.getByRole('button', { name: 'Mostrar contraseña' }))
    expect(screen.getByLabelText('Contraseña')).toHaveAttribute('type', 'text')
    expect(screen.getByRole('button', { name: 'Ocultar contraseña' })).toHaveAttribute('aria-pressed', 'true')
  })
})

type Fila = { id: number; nombre: string; aula: string }
const columnas: Columna<Fila>[] = [
  { clave: 'nombre', titulo: 'Nombre', celda: (f) => f.nombre, principal: true },
  { clave: 'aula', titulo: 'Aula', celda: (f) => f.aula },
]
const filas: Fila[] = ['Andrés Mori', 'Lucía Herrera', 'Valeria Quispe', 'Ñahui Torres', 'Kevin Aranda'].map(
  (nombre, i) => ({ id: i, nombre, aula: '3.º B' }),
)

function tabla(porPagina = 2) {
  return render(
    <TablaDatos
      filas={filas}
      columnas={columnas}
      claveFila={(f) => f.id}
      textoBusqueda={(f) => f.nombre}
      porPagina={porPagina}
      descripcion="Estudiantes"
    />,
  )
}

describe('TablaDatos', () => {
  it('pagina los resultados', async () => {
    tabla()

    expect(screen.getByText('Página 1 de 3')).toBeInTheDocument()
    expect(screen.getByRole('table')).toHaveTextContent('Andrés Mori')
    await userEvent.click(screen.getByRole('button', { name: 'Página siguiente' }))
    expect(screen.getByText('Página 2 de 3')).toBeInTheDocument()
    expect(screen.getByRole('table')).toHaveTextContent('Valeria Quispe')
    expect(screen.getByRole('button', { name: 'Página siguiente' })).toBeEnabled()
  })

  it('busca sin importar tildes ni mayúsculas', async () => {
    tabla()

    await userEvent.type(screen.getByRole('searchbox', { name: 'Buscar en Estudiantes' }), 'ANDRES')
    expect(screen.getByRole('table')).toHaveTextContent('Andrés Mori')
    expect(screen.getByRole('table')).not.toHaveTextContent('Lucía')
    expect(screen.queryByText(/Página \d de/)).not.toBeInTheDocument()
  })

  it('avisa cuando no hay resultados', async () => {
    tabla()

    await userEvent.type(screen.getByRole('searchbox'), 'zzz')
    expect(screen.getByText('Sin resultados')).toBeInTheDocument()
  })

  it('arma también la vista de tarjetas para celular', () => {
    tabla(10)

    const tarjetas = screen.getByRole('list', { name: 'Estudiantes' })
    expect(tarjetas.querySelectorAll('li')).toHaveLength(5)
    expect(tarjetas).toHaveTextContent('Aula')
  })
})

describe('DialogoConfirmacion', () => {
  it('solo muestra el contenido cuando está abierto y responde a los botones', async () => {
    const confirmar = vi.fn()
    const cancelar = vi.fn()
    const { rerender } = render(
      <DialogoConfirmacion abierto={false} titulo="¿Eliminar?" onConfirmar={confirmar} onCancelar={cancelar}>
        No se puede deshacer.
      </DialogoConfirmacion>,
    )
    expect(screen.queryByText('No se puede deshacer.')).not.toBeInTheDocument()

    rerender(
      <DialogoConfirmacion
        abierto
        titulo="¿Eliminar?"
        textoConfirmar="Sí, eliminar"
        onConfirmar={confirmar}
        onCancelar={cancelar}
      >
        No se puede deshacer.
      </DialogoConfirmacion>,
    )
    await userEvent.click(screen.getByText('Sí, eliminar'))
    await userEvent.click(screen.getByText('Cancelar'))
    expect(confirmar).toHaveBeenCalledOnce()
    expect(cancelar).toHaveBeenCalledOnce()
  })
})

function BotonAviso() {
  const avisos = useAvisos()
  return <button onClick={() => avisos.exito('Cambios guardados.')}>Guardar</button>
}

describe('Avisos', () => {
  it('muestra el aviso y permite cerrarlo', async () => {
    render(
      <ProveedorAvisos>
        <BotonAviso />
      </ProveedorAvisos>,
    )

    await userEvent.click(screen.getByText('Guardar'))
    expect(screen.getByRole('status')).toHaveTextContent('Cambios guardados.')
    await userEvent.click(screen.getByRole('button', { name: 'Cerrar aviso' }))
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })

  it('exige estar dentro del proveedor', () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    expect(() => render(<BotonAviso />)).toThrow('useAvisos debe usarse dentro de ProveedorAvisos')
  })
})
