import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { createBrowserRouter, RouterProvider } from 'react-router'
import { ProveedorAvisos } from './components/ui/avisos'
import { ProveedorSesion } from './lib/sesion-proveedor'
import { rutas } from './rutas'
import './index.css'

const router = createBrowserRouter(rutas, {
  basename: import.meta.env.BASE_URL.replace(/\/$/, '') || '/',
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <ProveedorAvisos>
      <ProveedorSesion>
        <RouterProvider router={router} />
      </ProveedorSesion>
    </ProveedorAvisos>
  </StrictMode>,
)
