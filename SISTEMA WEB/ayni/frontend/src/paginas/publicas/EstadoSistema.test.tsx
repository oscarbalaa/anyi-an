import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { renderizarEn, simularFetch } from '@/pruebas/utilidades'

const saludOk = {
  estado: 'ok',
  aplicacion: 'AYNI',
  version: '0.1.0',
  colegio: 'IEP El Señor es mi Pastor',
  hora_servidor: '2026-10-16T15:30:00-05:00',
  zona_horaria: 'America/Lima',
  base_de_datos: { conectada: true, motor: 'MySQL', version: '8.4.6' },
  php: '8.1.34',
  laravel: '10.50.3',
}

describe('página de estado del sistema', () => {
  it('muestra que la API y la base de datos están conectadas', async () => {
    simularFetch(200, saludOk)
    renderizarEn('/estado')

    expect(await screen.findByTestId('indicador-Base de datos')).toHaveTextContent('MySQL 8.4.6')
    expect(screen.getByTestId('indicador-API')).toHaveTextContent('AYNI 0.1.0')
    expect(screen.getByTestId('indicador-Colegio')).toHaveTextContent('IEP El Señor es mi Pastor')
    expect(screen.getByTestId('indicador-PHP')).toHaveTextContent('8.1.34')
  })

  it('muestra la hora del servidor en hora de Lima', async () => {
    simularFetch(200, saludOk)
    renderizarEn('/estado')

    expect(await screen.findByTestId('indicador-Hora del servidor')).toHaveTextContent('3:30')
  })

  it('muestra el error en español si la API no responde', async () => {
    simularFetch(503, { mensaje: 'El servicio no está disponible en este momento.' })
    renderizarEn('/estado')

    expect(await screen.findByRole('alert')).toHaveTextContent('El servicio no está disponible en este momento.')
  })

  it('permite volver a verificar el estado', async () => {
    const fetch = simularFetch(200, saludOk)
    renderizarEn('/estado')
    await screen.findByTestId('indicador-API')

    await userEvent.click(screen.getByRole('button', { name: /verificar/i }))

    expect(fetch).toHaveBeenCalledTimes(2)
  })
})
