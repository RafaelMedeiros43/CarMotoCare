//conversões e validação do formulário "Adicionar veículo".
// O ecrã mostra o formato português (MM / AAAA, DD / MM / AAAA, AB·24·CD); a API recebe o do contrato
// (AAAA-MM, AAAA-MM-DD, AB-24-CD).

const digits = (value) => value.replace(/\D/g, '')

/** "062021" -> "06 / 2021" */
export function maskMonthYear(value) {
  const d = digits(value).slice(0, 6)
  return d.length > 2 ? `${d.slice(0, 2)} / ${d.slice(2)}` : d
}

/** "24112026" -> "24 / 11 / 2026" */
export function maskDate(value) {
  const d = digits(value).slice(0, 8)
  const parts = [d.slice(0, 2), d.slice(2, 4), d.slice(4)].filter(Boolean)
  return parts.join(' / ')
}

/** "ab24cd" -> "AB·24·CD" */
export function maskPlate(value) {
  const p = value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6)
  return [p.slice(0, 2), p.slice(2, 4), p.slice(4)].filter(Boolean).join('·')
}

export const maskKm = (value) => digits(value).slice(0, 7)

/** "06 / 2021" -> "2021-06" (ou null se incompleto/inválido) */
export function monthYearToIso(value) {
  const m = /^(\d{2}) \/ (\d{4})$/.exec(value)
  if (!m) return null
  const month = Number(m[1])
  return month >= 1 && month <= 12 ? `${m[2]}-${m[1]}` : null
}

/** "24 / 11 / 2026" -> "2026-11-24" (ou null se não for um dia real do calendário) */
export function dateToIso(value) {
  const m = /^(\d{2}) \/ (\d{2}) \/ (\d{4})$/.exec(value)
  if (!m) return null
  const [d, mo, y] = [Number(m[1]), Number(m[2]), Number(m[3])]
  const real = new Date(Date.UTC(y, mo - 1, d))
  const ok = real.getUTCFullYear() === y && real.getUTCMonth() === mo - 1 && real.getUTCDate() === d
  return ok ? `${m[3]}-${m[2]}-${m[1]}` : null
}

/** "AB·24·CD" -> "AB-24-CD" (ou null se não tiver 6 caracteres) */
export function plateToApi(value) {
  const p = value.replace(/[^A-Z0-9]/gi, '').toUpperCase()
  return p.length === 6 ? `${p.slice(0, 2)}-${p.slice(2, 4)}-${p.slice(4)}` : null
}

const OPTIONAL_DATES = [
  ['iucValidUntil', 'IUC'],
  ['insuranceValidUntil', 'seguro'],
  ['inspectionValidUntil', 'inspeção'],
]

/**
 * Valida o formulário (valores como estão nos campos).
 * Devolve { errors: { campo: mensagem }, body } — `body` (VehicleCreate) só existe se não houver erros.
 */
export function buildVehicleBody(form, now = new Date()) {
  const errors = {}

  const brand = form.brand.trim()
  const model = form.model.trim()
  if (!brand) errors.brand = 'Indique a marca'
  if (!model) errors.model = 'Indique o modelo'

  const firstRegistration = monthYearToIso(form.firstRegistration)
  if (!form.firstRegistration) errors.firstRegistration = 'Indique o mês e o ano da 1.ª matrícula'
  else if (!firstRegistration) errors.firstRegistration = 'Use o formato MM / AAAA (mês entre 01 e 12)'
  else {
    const [y, m] = firstRegistration.split('-').map(Number)
    if (y < 1900) errors.firstRegistration = 'Indique um ano a partir de 1900'
    else if (y > now.getFullYear() || (y === now.getFullYear() && m > now.getMonth() + 1)) {
      errors.firstRegistration = 'A 1.ª matrícula não pode ser no futuro'
    }
  }

  const plate = plateToApi(form.plate)
  if (!form.plate) errors.plate = 'Indique a matrícula'
  else if (!plate) errors.plate = 'A matrícula tem 6 caracteres (letras ou números), ex.: AB·24·CD'

  if (form.km === '') errors.km = 'Indique a quilometragem atual'

  const optional = {}
  for (const [field, label] of OPTIONAL_DATES) {
    if (!form[field]) continue
    const iso = dateToIso(form[field])
    if (iso) optional[field] = iso
    else errors[field] = `Data de validade do ${label} inválida (DD / MM / AAAA)`
  }

  if (Object.keys(errors).length) return { errors, body: null }
  return {
    errors,
    body: { type: form.type, brand, model, firstRegistration, plate, km: Number(form.km), ...optional },
  }
}

/** "2021-06" -> "06 / 2021" (para preencher o campo ao editar) */
export const isoToMonthYear = (value) => {
  const [y, m] = value.split('-')
  return `${m} / ${y}`
}

/**
 * Edição (PATCH /vehicles/{id}, VehicleUpdate): valida os campos descritivos e devolve só os que mudaram
 */
export function buildVehicleUpdate(form, vehicle, now = new Date()) {
  const { errors, body } = buildVehicleBody({ ...form, type: vehicle.type, km: '0', iucValidUntil: '', insuranceValidUntil: '', inspectionValidUntil: '' }, now)
  if (!body) return { errors, body: null }
  const changes = {}
  for (const field of ['brand', 'model', 'firstRegistration', 'plate']) {
    if (body[field] !== vehicle[field]) changes[field] = body[field]
  }
  return { errors: {}, body: changes }
}
