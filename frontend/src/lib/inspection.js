import { daysUntil } from './format.js'

export const RESULT_OPTIONS = [['APROVADA', 'Aprovada'], ['REPROVADA', 'Reprovada']]
export const SEVERITY_OPTIONS = [['LIGEIRA', 'Ligeira'], ['GRAVE', 'Grave']]
export const severityLabel = (s) => SEVERITY_OPTIONS.find(([v]) => v === s)?.[1] ?? s
export const resultLabel = (r) => RESULT_OPTIONS.find(([v]) => v === r)?.[1] ?? r

/* Mais recente primeiro */
export const newestFirst = (list) => [...list].sort((a, b) => b.date.localeCompare(a.date) || b.createdAt?.localeCompare(a.createdAt ?? '') || 0)

/*sem deficiências" / "1 deficiência ligeira" / "2 deficiências ligeiras" / "1 deficiência grave */
export function deficienciesText(list) {
  if (!list.length) return 'sem deficiências'
  const light = list.filter((d) => d.severity === 'LIGEIRA').length
  const grave = list.length - light
  const part = (n, one, many) => `${n} ${n === 1 ? one : many}`
  const bits = []
  if (light) bits.push(part(light, 'deficiência ligeira', 'deficiências ligeiras'))
  if (grave) bits.push(light ? part(grave, 'grave', 'graves') : part(grave, 'deficiência grave', 'deficiências graves'))
  return bits.join(' e ')
}

/** Validade da inspeção: a data da aprovada mais recente  */
export function validityOf(inspections) {
  const approved = newestFirst(inspections).find((i) => i.result === 'APROVADA' && i.validUntil)
  if (!approved) return null
  return { validUntil: approved.validUntil, days: daysUntil(approved.validUntil) }
}

/** "Em 18 dias · dentro do prazo" / "Expirou há 17 dias · inspeção em atraso" */
export function validityText(days) {
  if (days < 0) return `Expirou há ${-days} ${days === -1 ? 'dia' : 'dias'} · inspeção em atraso`
  if (days === 0) return 'Expira hoje'
  return `Em ${days} ${days === 1 ? 'dia' : 'dias'} · dentro do prazo`
}
