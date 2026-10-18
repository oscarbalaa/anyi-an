import '@testing-library/jest-dom/vitest'
import { cleanup, configure } from '@testing-library/react'
import { afterEach, vi } from 'vitest'
import { clienteDatos } from '@/lib/cliente-datos'

// En Docker sobre Windows la primera carga de cada pantalla diferida tarda más
configure({ asyncUtilTimeout: 15_000 })

// jsdom no implementa <dialog>.showModal(): se simula con el atributo open
HTMLDialogElement.prototype.showModal ??= function (this: HTMLDialogElement) {
  this.setAttribute('open', '')
}
HTMLDialogElement.prototype.close ??= function (this: HTMLDialogElement) {
  this.removeAttribute('open')
}

// Sin reintentos en las pruebas y sin datos de una prueba a otra
clienteDatos.setDefaultOptions({ queries: { retry: false } })

afterEach(() => {
  cleanup()
  clienteDatos.clear()
  localStorage.clear()
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})
