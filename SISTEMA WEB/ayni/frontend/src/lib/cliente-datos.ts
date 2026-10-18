import { QueryClient } from '@tanstack/react-query'

export const clienteDatos = new QueryClient({
  defaultOptions: { queries: { refetchOnWindowFocus: false, retry: 1 } },
})
