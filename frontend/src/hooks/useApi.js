import { useMemo } from 'react'
import { useAuth } from '../context/AuthContext.jsx'
import * as realApi from '../api/vehicles.js'
import { mockApi } from '../mocks/dashboard.js'

/** Sessão real -> EAPI Web. Sessão de teste ("Seguinte" no login) -> dados de exemplo (src/mocks). */
export default function useApi() {
  const { isMock } = useAuth()
  return useMemo(() => (isMock ? mockApi : realApi), [isMock])
}
