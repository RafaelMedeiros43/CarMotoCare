import { request } from './client.js'

// POST /users  -> { name, email, password (mín. 8) }  => User
export const registerUser = (data) =>
  request('/users', { method: 'POST', body: data, auth: false })

// POST /auth/tokens -> { email, password } => { accessToken, tokenType, expiresIn }
export const createToken = (credentials) =>
  request('/auth/tokens', { method: 'POST', body: credentials, auth: false })

// GET /users/me => User
export const getMe = () => request('/users/me')

// PATCH /users/me -> { name?, email?, newPassword?, currentPassword? } => User
// currentPassword é obrigatória para mudar o e-mail ou a palavra-passe.
export const updateMe = (data) => request('/users/me', { method: 'PATCH', body: data })
