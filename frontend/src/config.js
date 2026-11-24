// Para onde vai o utilizador depois de iniciar sessão (Figma: "05 Conta pessoal")
export const AFTER_LOGIN_PATH = '/conta'

// TEMPORÁRIO — modo de desenvolvimento sem login
// Num build de produção (import.meta.env.DEV === false) está sempre desligado
export const MOCK_AUTH_ENABLED = import.meta.env.DEV && import.meta.env.VITE_MOCK_AUTH !== 'false'
