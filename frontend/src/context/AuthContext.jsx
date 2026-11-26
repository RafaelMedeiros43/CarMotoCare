import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { configureClient } from '../api/client.js'
import { createToken, getMe, registerUser, updateMe } from '../api/auth.js'
import { MOCK_AUTH_ENABLED } from '../config.js'

const AuthContext = createContext(null)
const STORAGE_KEY = 'carmotocare.token'

const MOCK_TOKEN = 'dev-mock-token'
const MOCK_USER = {
  id: '00000000-0000-4000-8000-000000000001',
  name: 'Miguel Santos',
  email: 'miguel.santos@exemplo.pt',
}

export function AuthProvider({ children }) {
  const [token, setToken] = useState(() => {
    const saved = localStorage.getItem(STORAGE_KEY)
    
    if (saved === MOCK_TOKEN && !MOCK_AUTH_ENABLED) {
      localStorage.removeItem(STORAGE_KEY)
      return null
    }
    return saved
  })
  const [account, setAccount] = useState(null)
  const isMock = token === MOCK_TOKEN
  const user = account ?? (isMock ? MOCK_USER : null)

  const logout = useCallback(() => {
    localStorage.removeItem(STORAGE_KEY)
    setToken(null)
    setAccount(null)
  }, [])

  // Configurado durante o render ( não useEffect) para os pedidos dos
  // componentes futuros já usarem o token atual
  configureClient({ tokenGetter: () => token, unauthorizedHandler: logout })

  // Com token guardado, vai buscar os dados da conta
  useEffect(() => {
    if (!token || isMock) return
    let cancelled = false
    getMe()
      .then((u) => !cancelled && setAccount(u))
      .catch(() => {})
    return () => { cancelled = true }
  }, [token, isMock])

  const login = useCallback(async (email, password) => {
    const { accessToken } = await createToken({ email, password })
    localStorage.setItem(STORAGE_KEY, accessToken)
    setToken(accessToken)
  }, [])

  const register = useCallback(async ({ name, email, password }) => {
    await registerUser({ name, email, password })
    await login(email, password) // depois de criar a conta, inicia sessão
  }, [login])

  // Guarda alterações do perfil (nome, e-mail, palavra-passe). Na sessão  teste só muda em memória por agr
  const updateProfile = useCallback(async (changes) => {
    if (isMock) {
      setAccount((current) => {
        const base = current ?? MOCK_USER
        return { ...base, name: changes.name ?? base.name, email: changes.email ?? base.email }
      })
      return
    }
    setAccount(await updateMe(changes))
  }, [isMock])

  // TEMPORÁRIO: entra como se tivesse feito login (só com MOCK_AUTH_ENABLED).
  const mockLogin = useCallback(() => {
    if (!MOCK_AUTH_ENABLED) return
    localStorage.setItem(STORAGE_KEY, MOCK_TOKEN)
    setToken(MOCK_TOKEN)
  }, [])

  const value = useMemo(
    () => ({ token, user, isAuthenticated: !!token, isMock, login, register, logout, mockLogin, updateProfile }),
    [token, user, isMock, login, register, logout, mockLogin, updateProfile],
  )
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth tem de ser usado dentro de <AuthProvider>')
  return ctx
}
