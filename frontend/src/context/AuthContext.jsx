import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { configureClient } from '../api/client.js'
import { createToken, getMe, registerUser, updateMe } from '../api/auth.js'

const AuthContext = createContext(null)
const STORAGE_KEY = 'carmotocare.token'

export function AuthProvider({ children }) {
  const [token, setToken] = useState(() => localStorage.getItem(STORAGE_KEY))
  const [account, setAccount] = useState(null)
  const user = account

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
    if (!token) return
    let cancelled = false
    getMe()
      .then((u) => !cancelled && setAccount(u))
      .catch(() => {})
    return () => { cancelled = true }
  }, [token])

  const login = useCallback(async (email, password) => {
    const { accessToken } = await createToken({ email, password })
    localStorage.setItem(STORAGE_KEY, accessToken)
    setToken(accessToken)
  }, [])

  const register = useCallback(async ({ name, email, password }) => {
    await registerUser({ name, email, password })
    await login(email, password) // depois de criar a conta, inicia sessão
  }, [login])

  // Guarda alterações do perfil (nome, e-mail, palavra-passe) em PATCH /users/me
  const updateProfile = useCallback(async (changes) => {
    setAccount(await updateMe(changes))
  }, [])

  const value = useMemo(
    () => ({ token, user, isAuthenticated: !!token, login, register, logout, updateProfile }),
    [token, user, login, register, logout, updateProfile],
  )
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth tem de ser usado dentro de <AuthProvider>')
  return ctx
}
