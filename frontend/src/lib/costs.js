import { formatMonthYearLong } from './format.js'

export const CATEGORY_LABEL = { IUC: 'IUC', SEGURO: 'Seguro', MANUTENCAO: 'Manutenção', INSPECAO: 'Inspeção' }
export const RESULT_LABEL = { APROVADA: 'Aprovada', REPROVADA: 'Reprovada' }

export const sum = (list, field = 'amount') => Math.round(list.reduce((t, x) => t + Number(x[field]), 0) * 100) / 100
export const count = (n, one, many) => `${n} ${n === 1 ? one : many}`

// Período: um ano ("2026") ou "ALL". As despesas vêm todas da API e o período filtra-se aqui, no browser.
export const inPeriod = (date, period) => period === 'ALL' || date.startsWith(period)
export const periodLabel = (period) => (period === 'ALL' ? 'Todos os registos' : period)

/** Anos com registos + o ano atual, do mais recente para o mais antigo */
export function periodOptions(dates, currentYear) {
  const years = new Set([String(currentYear), ...dates.map((d) => d.slice(0, 4))])
  return [...years].sort().reverse().map((y) => [y, y]).concat([['ALL', 'Todos os registos']])
}

/** "3 intervenções em 2026" / "3 intervenções desde dezembro de 2025" / "Sem intervenções em 2026" */
export function interventionsText(maintenances, period) {
  if (maintenances.length === 0) return `Sem intervenções ${period === 'ALL' ? 'registadas' : `em ${period}`}`
  const dates = maintenances.map((m) => m.date).sort()
  const n = count(maintenances.length, 'intervenção', 'intervenções')
  const sameYear = dates[0].slice(0, 4) === period && dates.at(-1).slice(0, 4) === period
  return sameYear ? `${n} em ${period}` : `${n} desde ${formatMonthYearLong(dates[0])}`
}

/** Nova validade de um IUC/seguro pago: um ano depois da data do pagamento (o Figma não pede esta data) */
export function oneYearAfter(iso) {
  const [y, m, d] = iso.split('-').map(Number)
  const last = new Date(Date.UTC(y + 1, m, 0)).getUTCDate()
  return `${y + 1}-${String(m).padStart(2, '0')}-${String(Math.min(d, last)).padStart(2, '0')}`
}
