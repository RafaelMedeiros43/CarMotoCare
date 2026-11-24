// Formatação em português de Portugal (datas, km, euros). Datas "AAAA-MM-DD" 


const MONTHS_SHORT = ['jan.', 'fev.', 'mar.', 'abr.', 'mai.', 'jun.', 'jul.', 'ago.', 'set.', 'out.', 'nov.', 'dez.']
const MONTHS_LONG = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro']

const parseDate = (value) => {
  const [y, m, d] = value.split('-').map(Number)
  return { y, m, d }
}

const numberPt = new Intl.NumberFormat('pt-PT')
const euroPt = new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' })

export const formatKm = (km) => (km == null ? '—' : `${numberPt.format(km)} km`)
export const formatEuro = (value) => euroPt.format(value)

/** "2026-10-18" -> "18 out. 2026" */
export function formatDate(value) {
  const { y, m, d } = parseDate(value)
  return `${d} ${MONTHS_SHORT[m - 1]} ${y}`
}

/** "2021-06" -> "Jun. 2021" */
export function formatYearMonth(value) {
  const { y, m } = parseDate(value)
  const month = MONTHS_SHORT[m - 1]
  return `${month[0].toUpperCase()}${month.slice(1)} ${y}`
}

/** "2025-12-10" -> "dezembro de 2025" */
export function formatMonthYearLong(value) {
  const { y, m } = parseDate(value)
  return `${MONTHS_LONG[m - 1]} de ${y}`
}

/** Hoje por extenso: "6 de novembro de 2026" */
export const formatToday = () =>
  new Intl.DateTimeFormat('pt-PT', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date())

/** Dias entre hoje e uma data "AAAA-MM-DD" (negativo = já passou). */
export function daysUntil(value) {
  const { y, m, d } = parseDate(value)
  const target = Date.UTC(y, m - 1, d)
  const now = new Date()
  const today = Date.UTC(now.getFullYear(), now.getMonth(), now.getDate())
  return Math.round((target - today) / 86400000)
}

/** Data/hora ISO -> "há 12 min" */
export function timeAgo(iso) {
  const minutes = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000))
  if (minutes < 1) return 'agora mesmo'
  if (minutes < 60) return `há ${minutes} min`
  const hours = Math.round(minutes / 60)
  if (hours < 24) return `há ${hours} h`
  const days = Math.round(hours / 24)
  return `há ${days} ${days === 1 ? 'dia' : 'dias'}`
}

/** "AB-24-CD" -> "AB • 24 • CD" */
export const formatPlate = (plate) => plate.split('-').join(' • ')

export function initials(name = '') {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return '?'
  const first = parts[0][0]
  const last = parts.length > 1 ? parts[parts.length - 1][0] : ''
  return `${first}${last}`.toUpperCase()
}

/** Minutos desde uma data/hora ISO. */
export const minutesSince = (iso) => (Date.now() - new Date(iso).getTime()) / 60000

export const currentYear = () => new Date().getFullYear()
