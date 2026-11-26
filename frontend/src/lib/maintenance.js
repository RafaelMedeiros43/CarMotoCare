import { formatDate, formatKm } from './format.js'

// Tipos de intervenção do contrato (MaintenanceType)
export const MAINTENANCE_TYPES = [
  ['REVISAO_PERIODICA', 'Revisão periódica'],
  ['MUDANCA_OLEO', 'Mudança de óleo'],
  ['SUBSTITUICAO_PNEUS', 'Substituição de pneus'],
  ['REPARACAO', 'Reparação'],
  ['AVARIA', 'Avaria'],
  ['OUTRO', 'Outro'],
]
export const typeLabel = (type) => MAINTENANCE_TYPES.find(([v]) => v === type)?.[1] ?? type
// Título na lista do histórico (Figma 18)
const TITLES = { MUDANCA_OLEO: 'Mudança de óleo e filtros', SUBSTITUICAO_PNEUS: 'Substituição de pneus' }
export const typeTitle = (type) => TITLES[type] ?? typeLabel(type)

// Filtro "Período" do histórico (feito no browser: a API devolve tudo)
export const PERIODS = [
  ['ALL', 'Todos os registos', null],
  ['3M', 'Últimos 3 meses', 3],
  ['6M', 'Últimos 6 meses', 6],
  ['12M', 'Últimos 12 meses', 12],
]

const pad = (n) => String(n).padStart(2, '0')
export const isoOf = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
export const todayIso = () => isoOf(new Date())

/** "2026-10-18" + 12 meses -> "2027-10-18"  */
export function addMonths(iso, months) {
  const [y, m, d] = iso.split('-').map(Number)
  const total = (y * 12 + (m - 1)) + months
  const ny = Math.floor(total / 12)
  const nm = total % 12
  const last = new Date(Date.UTC(ny, nm + 1, 0)).getUTCDate()
  return `${ny}-${pad(nm + 1)}-${pad(Math.min(d, last))}`
}

export const periodStart = (months, now = new Date()) => {
  if (!months) return null
  const d = new Date(now)
  d.setMonth(d.getMonth() - months)
  return isoOf(d)
}

/** Euros do campo ("146,50" ou "146.5") -> número, ou null se inválido (máx. 2 casas) */
export function parseEuro(text) {
  const t = String(text).trim().replace(/\s/g, '').replace(',', '.')
  if (!/^\d+(\.\d{1,2})?$/.test(t)) return null
  return Number(t)
}

/** 63.5 -> "63,50" (para mostrar no campo) */
export const euroInput = (n) => n.toFixed(2).replace('.', ',')

// Peças e intervalos
const unit = (n, one, many) => `${n.toLocaleString('pt-PT', { useGrouping: 'always' })} ${n === 1 ? one : many}`
export function monthsText(m) {
  return m >= 24 && m % 12 === 0 ? unit(m / 12, 'ano', 'anos') : unit(m, 'mês', 'meses')
}

/** "10 000 km ou 12 meses" | "2 anos" | "30 000 km" */
export function intervalText(part) {
  const parts = []
  if (part.intervalKm != null) parts.push(formatKm(part.intervalKm))
  if (part.intervalMonths != null) parts.push(monthsText(part.intervalMonths))
  return parts.join(' ou ')
}

/** "67 440 km · 18 out. 2026" (ou só a parte que existir; "Sem registo") */
export function lastServiceText(part) {
  const bits = []
  if (part.lastServiceKm != null) bits.push(formatKm(part.lastServiceKm))
  if (part.lastServiceDate) bits.push(formatDate(part.lastServiceDate))
  return bits.length ? bits.join(' · ') : 'Sem registo'
}

/** Quanto falta: prefere km quando há os dois limites. "Faltam 9 020 km", "Faltam 75 dias", "Faltam 4 meses", "Em atraso" */
export function remainingText(part) {
  const { kmRemaining: km, daysRemaining: days } = part
  if (km != null) return km <= 0 ? 'Em atraso' : `Faltam ${formatKm(km)}`
  if (days != null) {
    if (days <= 0) return 'Em atraso'
    return days < 90 ? `Faltam ${unit(days, 'dia', 'dias')}` : `Faltam ${unit(Math.round(days / 30.4), 'mês', 'meses')}`
  }
  return '—'
}

/** Peça com menos km em falta (para o banner "Intervalo mais próximo") */
export const closestPart = (parts) =>
  parts.filter((p) => p.kmRemaining != null).sort((a, b) => a.kmRemaining - b.kmRemaining)[0] ?? null

/** 0.8 -> "0,8 km"; 10 -> "10 km" */
export const formatDistance = (km) =>
  `${km.toLocaleString('pt-PT', { maximumFractionDigits: km < 10 ? 1 : 0 })} km`
