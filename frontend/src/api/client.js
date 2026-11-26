// Cliente HTTP único: o browser só fala com a EAPI Web (via Nginx em /api)
// Os erros chegam no formato RFC 7807 (Problem) e são convertidos em ApiError
const BASE_URL = import.meta.env.VITE_API_URL ?? '/api'

export class ApiError extends Error {
  constructor(problem, status) {
    super(problem?.detail || problem?.title || 'Erro inesperado')
    this.status = status
    this.fieldErrors = problem?.errors ?? [] // [{ field, message }]
  }
}

let getToken = () => null
let onUnauthorized = () => {}

// Chamado pelo AuthContext para o cliente saber o token atual e reagir a 401
export function configureClient({ tokenGetter, unauthorizedHandler }) {
  getToken = tokenGetter
  onUnauthorized = unauthorizedHandler
}

export async function request(path, { method = 'GET', body, auth = true } = {}) {
  const headers = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  const token = getToken()
  if (auth && token) headers.Authorization = `Bearer ${token}`

  let res
  try {
    res = await fetch(`${BASE_URL}${path}`, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    })
  } catch {
    throw new ApiError({ title: 'Sem ligação', detail: 'Não foi possível contactar o servidor' }, 0)
  }

  if (res.status === 204) return null
  const data = await res.json().catch(() => null)

  if (!res.ok) {
    if (res.status === 401 && auth) onUnauthorized()
    throw new ApiError(data, res.status)
  }
  return data
}
