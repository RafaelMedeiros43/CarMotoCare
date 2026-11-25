import { ApiError } from '../api/client.js'

// TEMPORÁRIO — dados de exemplo para o painel enquanto a EAPI Web não responde
// Têm a mesma forma do contrato (docs/api/eapi-web.yaml). Só são usados na sessão  teste
// (botão "Seguinte" do login, com VITE_MOCK_AUTH=true). Apagar esta pasta quando o backend estiver pronto.

const GOLF = '11111111-1111-4111-8111-111111111111'
const MT07 = '22222222-2222-4222-8222-222222222222'

const iso = (d) => d.toISOString().slice(0, 10)
const daysFromNow = (n) => {
  const d = new Date()
  d.setDate(d.getDate() + n)
  return iso(d)
}

const vehicles = [
  {
    id: GOLF, type: 'CARRO', brand: 'Volkswagen', model: 'Golf', firstRegistration: '2021-06',
    plate: 'AB-24-CD', km: 68420, openAlerts: 1,
    nextService: { partId: 'p1', partName: 'Pastilhas de travão', targetKm: 75000, kmRemaining: 6580, dueDate: null },
  },
  {
    id: MT07, type: 'MOTO', brand: 'Yamaha', model: 'MT-07', firstRegistration: '2024-03',
    plate: '12-34-EF', km: 24180, openAlerts: 0,
    nextService: { partId: 'p2', partName: 'Revisão', targetKm: 25000, kmRemaining: 820, dueDate: null },
  },
]

// ── Plano de peças (GET /vehicles/{id}/parts). nextKm/nextDate/kmRemaining/daysRemaining calculam-se como no servidor ──
const addMonthsIso = (iso, months) => {
  const [y, m, d] = iso.split('-').map(Number)
  const total = y * 12 + (m - 1) + months
  const last = new Date(Date.UTC(Math.floor(total / 12), (total % 12) + 1, 0)).getUTCDate()
  return `${Math.floor(total / 12)}-${String((total % 12) + 1).padStart(2, '0')}-${String(Math.min(d, last)).padStart(2, '0')}`
}
const withNext = (part, vehicleKm) => {
  const nextKm = part.intervalKm != null ? (part.lastServiceKm ?? 0) + part.intervalKm : null
  const nextDate = part.intervalMonths != null && part.lastServiceDate ? addMonthsIso(part.lastServiceDate, part.intervalMonths) : null
  const days = nextDate ? Math.round((new Date(`${nextDate}T00:00:00`) - new Date(`${iso(new Date())}T00:00:00`)) / 86400000) : null
  return { ...part, nextKm, nextDate, kmRemaining: nextKm != null ? nextKm - vehicleKm : null, daysRemaining: days }
}
// [nome, intervalo km, intervalo meses, origem, último km, última data]
const GOLF_PARTS = [
  ['Óleo e filtros', 10000, 12, 'UTILIZADOR', 67440, '2026-10-18'],
  ['Pastilhas de travão', 30000, null, 'CATALOGO', 45000, '2025-03-03'],
  ['Filtro de ar', 20000, null, 'CATALOGO', 59870, '2025-12-10'],
  ['Pneus', 40000, null, 'CATALOGO', 62180, '2026-06-12'],
  ['Correia de distribuição', 120000, 60, 'CATALOGO', null, null],
  ['Discos de travão', 60000, null, 'CATALOGO', 45000, '2025-03-03'],
  ['Líquido de travões', null, 24, 'CATALOGO', 45000, '2025-03-03'],
  ['Velas de ignição', 40000, null, 'CATALOGO', 40100, '2024-05-14'],
  ['Filtro de combustível', 60000, null, 'CATALOGO', 59870, '2025-12-10'],
  ['Filtro do habitáculo', 20000, 24, 'CATALOGO', 61300, '2026-01-15'],
  ['Bateria', null, 60, 'CATALOGO', null, '2023-03-22'],
  ['Escovas do limpa-para-brisas', null, 12, 'CATALOGO', null, '2026-01-20'],
]
const MT07_PARTS = [
  ['Óleo e filtro de óleo', 10000, null, 'CATALOGO', 19800, '2026-02-14'],
  ['Filtro de ar', 10000, null, 'CATALOGO', 19800, '2026-02-14'],
  ['Pastilhas de travão', 10000, null, 'CATALOGO', 21000, '2026-04-02'],
  ['Corrente de transmissão', 10000, null, 'CATALOGO', 21000, '2026-04-02'],
  ['Pneus', 10000, null, 'CATALOGO', 21900, '2026-05-20'],
  ['Velas de ignição', 10000, null, 'CATALOGO', 19800, '2026-02-14'],
]
const makeParts = (vehicleId, rows) => rows.map(([name, intervalKm, intervalMonths, intervalSource, lastServiceKm, lastServiceDate], i) => ({
  id: `${vehicleId.slice(0, 8)}-0000-4000-8000-${String(i + 1).padStart(12, '0')}`, vehicleId, name,
  intervalKm, intervalMonths, intervalSource, lastServiceKm, lastServiceDate,
}))
const parts = { [GOLF]: makeParts(GOLF, GOLF_PARTS), [MT07]: makeParts(MT07, MT07_PARTS) }
const kmOf = (id) => vehicles.find((v) => v.id === id)?.km ?? 0

const notFound = (detail) => later(null).then(() => { throw new ApiError({ title: 'Não encontrado', status: 404, detail }, 404) })
const invalid = (field, message) => later(null).then(() => { throw new ApiError({ title: 'Pedido inválido', status: 400, detail: 'Há campos inválidos.', errors: [{ field, message }] }, 400) })

// ── Oficinas (GET /workshops): [id, nome, tipo, morada, serviços, deslocação em km a norte, a este]
const WORKSHOPS = [
  ['node/1001', 'Oficina Atlântico Auto', 'OFICINA', 'Rua do Aljube, 34 · Ponta Delgada', ['Revisão', 'Óleo e filtros', 'Travões'], 0.3, 0.7],
  ['node/1002', 'Pneus Micaelense', 'LOJA_PNEUS', 'Av. Infante D. Henrique, 112 · Ponta Delgada', ['Pneus', 'Equilibragem', 'Alinhamento'], -0.9, 0.8],
  ['node/1003', 'Oficina Ilha Verde', 'OFICINA', 'Rua de Lisboa, 78 · Lagoa, São Miguel', ['Mecânica geral', 'Diagnóstico', 'Elétrica'], 1.5, 9.9],
  ['node/1004', 'Pneus Furnas', 'LOJA_PNEUS', 'Estrada Nacional 1, Km 42 · Furnas, São Miguel', ['Montagem de pneus', 'Alinhamento'], 2.8, 39.9],
]
const KM_PER_DEG = 111.32
const distanceKm = (north, east) => Math.sqrt(north ** 2 + east ** 2)
const norm = (t) => t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()

const alerts = [
  {
    id: 'a1', vehicleId: GOLF, type: 'INSPECAO', trigger: 'DATA', status: 'ABERTO',
    title: 'Inspeção periódica', dueDate: daysFromNow(18), targetKm: null, kmRemaining: null,
    createdAt: new Date().toISOString(),
  },
]

const maintenances = {
  [GOLF]: [
    { id: 'm1', vehicleId: GOLF, type: 'MUDANCA_OLEO', date: '2026-10-18', km: 67440, cost: 146, notes: 'Oficina Bosch Car Service', parts: [] },
    { id: 'm2', vehicleId: GOLF, type: 'SUBSTITUICAO_PNEUS', date: '2026-06-12', km: 62180, cost: 210, notes: 'Continental EcoContact 6 · 2 pneus', parts: [] },
    { id: 'm3', vehicleId: GOLF, type: 'REVISAO_PERIODICA', date: '2025-12-10', km: 59870, cost: 285, notes: 'revisão programada', parts: [] },
  ],
  [MT07]: [
    { id: 'm4', vehicleId: MT07, type: 'MUDANCA_OLEO', date: '2026-10-18', km: 23800, cost: 85, notes: null, parts: [] },
    { id: 'm5', vehicleId: MT07, type: 'OUTRO', date: '2026-09-12', km: 23100, cost: 35, notes: 'Afinação e lubrificação da corrente', parts: [] },
    { id: 'm6', vehicleId: MT07, type: 'REPARACAO', date: '2026-07-20', km: 21900, cost: 65, notes: 'Pastilhas do travão dianteiro', parts: [] },
  ],
}

const expenses = {
  [GOLF]: [
    { id: 'e1', category: 'MANUTENCAO', date: '2026-10-18', amount: 146, description: 'Oficina Bosch Car Service', km: 67440 },
    { id: 'e2', category: 'MANUTENCAO', date: '2026-06-12', amount: 210, description: 'Continental EcoContact 6 · 2 pneus', km: 62180 },
    { id: 'e3', category: 'SEGURO', date: '2026-01-12', amount: 162, description: 'Seguro anual', km: null },
    { id: 'e4', category: 'IUC', date: '2026-06-30', amount: 148.2, description: 'IUC 2026', km: null },
    { id: 'e5', category: 'MANUTENCAO', date: '2025-12-10', amount: 285, description: 'revisão programada', km: 59870 },
  ],
  [MT07]: [
    { id: 'e6', category: 'MANUTENCAO', date: '2026-10-18', amount: 85, description: null, km: 23800 },
    { id: 'e7', category: 'MANUTENCAO', date: '2026-09-12', amount: 35, description: 'Afinação e lubrificação da corrente', km: 23100 },
    { id: 'e8', category: 'MANUTENCAO', date: '2026-07-20', amount: 65, description: 'Pastilhas do travão dianteiro', km: 21900 },
    { id: 'e9', category: 'INSPECAO', date: '2026-11-03', amount: 29.6, description: null, km: null },
  ],
}

// GET /vehicles/{id}/inspections (a rota ainda não existe na EAPI Web)
const inspections = {
  [GOLF]: [],
  [MT07]: [{ id: 'i1', vehicleId: MT07, date: '2026-11-03', result: 'REPROVADA', amount: 29.6, km: 24180, validUntil: null, deficiencies: [], createdAt: new Date().toISOString() }],
}

const obdStates = {
  [GOLF]: {
    vehicleId: GOLF, deviceId: 'OBD-DEMO-01',
    readAt: new Date(Date.now() - 12 * 60 * 1000).toISOString(),
    km: 68420, oilTemp: 89, gps: { latitude: 37.7417, longitude: -25.671 },
  },
  // Sem dispositivo (Figma 13b): tudo nulo
  [MT07]: { vehicleId: MT07, deviceId: null, readAt: null, km: null, oilTemp: null, gps: null },
}

// Dispositivos OBD emparelhados (ObdDevice), por veículo
const devices = {
  [GOLF]: { deviceId: 'OBD-DEMO-01', name: 'OBD do Golf', vehicleId: GOLF, pairedAt: '2026-10-02T09:15:00Z', lastReadingAt: obdStates[GOLF].readAt },
}

const deadlines = {
  [GOLF]: [
    { id: 'd1', vehicleId: GOLF, type: 'INSPECAO', status: 'PENDENTE', dueDate: daysFromNow(18), paidAt: null, amount: null, description: null, createdAt: new Date().toISOString() },
    { id: 'd2', vehicleId: GOLF, type: 'SEGURO', status: 'PENDENTE', dueDate: daysFromNow(95), paidAt: null, amount: null, description: null, createdAt: new Date().toISOString() },
  ],
  [MT07]: [
    { id: 'd3', vehicleId: MT07, type: 'SEGURO', status: 'PENDENTE', dueDate: daysFromNow(60), paidAt: null, amount: null, description: null, createdAt: new Date().toISOString() },
  ],
}

const later = (value) => new Promise((resolve) => setTimeout(() => resolve(value), 150))

// Remove de uma lista (em memória) os elementos que cumprem o teste
const removeWhere = (list, test) => {
  for (let i = list.length - 1; i >= 0; i--) if (test(list[i])) list.splice(i, 1)
}

export const mockApi = {
  // Cópias: o estado do React nunca fica ligado às listas internas do mock
  listVehicles: () => later([...vehicles]),
  listAlerts: () => later([...alerts]),
  listMaintenances: (id) => later(maintenances[id] ?? []),
  listExpenses: (id) => later(expenses[id] ?? []),
  getObdState: (id) => later(obdStates[id] ?? null),
  // GET /vehicles/{id}/obd-device: 404 quando não há dispositivo
  getObdDevice: (id) => devices[id]
    ? later({ ...devices[id] })
    : later(null).then(() => { throw new ApiError({ title: 'Não encontrado', status: 404, detail: 'Sem dispositivo OBD emparelhado' }, 404) }),
  // PUT /vehicles/{id}/obd-device: emparelha (ou substitui); 409 se a referência já estiver noutro veículo
  pairObdDevice: (id, data) => {
    if (Object.entries(devices).some(([vehicleId, d]) => vehicleId !== id && d.deviceId === data.deviceId)) {
      return later(null).then(() => {
        throw new ApiError({ title: 'Conflito', status: 409, detail: 'Este dispositivo já está associado a outro veículo' }, 409)
      })
    }
    devices[id] = { deviceId: data.deviceId, name: data.name ?? null, vehicleId: id, pairedAt: new Date().toISOString(), lastReadingAt: null }
    obdStates[id] = { vehicleId: id, deviceId: data.deviceId, readAt: null, km: null, oilTemp: null, gps: null }
    return later({ ...devices[id] })
  },
  // DELETE /vehicles/{id}/obd-device
  unpairObdDevice: (id) => {
    delete devices[id]
    obdStates[id] = { vehicleId: id, deviceId: null, readAt: null, km: null, oilTemp: null, gps: null }
    return later(null)
  },
  listDeadlines: (id) => later([...(deadlines[id] ?? [])]),
  // POST /vehicles/{id}/deadlines
  createDeadline: (id, data) => {
    const deadline = { id: crypto.randomUUID(), vehicleId: id, type: data.type, status: 'PENDENTE', dueDate: data.dueDate, paidAt: null, amount: null, description: null, createdAt: new Date().toISOString() }
    deadlines[id] = [...(deadlines[id] ?? []), deadline]
    return later({ ...deadline })
  },
  // GET /vehicles/{id}/inspections
  listInspections: (id) => later([...(inspections[id] ?? [])]),
  // POST /vehicles/{id}/expenses (pagar IUC ou seguro): { category, date, amount > 0, description?, validUntil }.
  // Como no servidor: fecha o prazo pendente desse tipo (ou cria-o) e cria o seguinte na data `validUntil`
  payExpense: (id, data) => {
    if (!(data.amount > 0)) return invalid('amount', 'O valor tem de ser superior a 0,00 €.')
    if (data.date > iso(new Date())) return invalid('date', 'A data não pode ser no futuro.')
    const list = (deadlines[id] ??= [])
    const now = new Date().toISOString()
    const pending = list.find((d) => d.type === data.category && d.status === 'PENDENTE')
    if (pending) Object.assign(pending, { status: 'CONCLUIDO', paidAt: data.date, amount: data.amount, description: data.description ?? null })
    else list.push({ id: crypto.randomUUID(), vehicleId: id, type: data.category, status: 'CONCLUIDO', dueDate: data.date, paidAt: data.date, amount: data.amount, description: data.description ?? null, createdAt: now })
    list.push({ id: crypto.randomUUID(), vehicleId: id, type: data.category, status: 'PENDENTE', dueDate: data.validUntil, paidAt: null, amount: null, description: null, createdAt: now })
    const expense = { id: crypto.randomUUID(), category: data.category, date: data.date, amount: data.amount, description: data.description ?? null, km: null }
    expenses[id] = [expense, ...(expenses[id] ?? [])]
    return later({ ...expense })
  },
  // GET /vehicles/{id}/parts
  listParts: (id) => parts[id] ? later(parts[id].map((p) => withNext(p, kmOf(id)))) : notFound('Veículo não encontrado'),
  // PATCH /vehicles/{id}/parts/{partId}: intervalo e/ou referência. Intervalo alterado -> origem UTILIZADOR
  updatePart: (id, partId, data) => {
    const part = (parts[id] ?? []).find((p) => p.id === partId)
    if (!part) return notFound('Peça não encontrada')
    const next = { ...part, ...data }
    if (next.intervalKm == null && next.intervalMonths == null) return invalid('intervalKm', 'Ative pelo menos um limite (km ou meses)')
    if (next.lastServiceKm != null && next.lastServiceKm > kmOf(id)) return invalid('lastServiceKm', 'A quilometragem de referência não pode ser superior à do veículo')
    if ('intervalKm' in data || 'intervalMonths' in data) next.intervalSource = 'UTILIZADOR'
    parts[id][parts[id].indexOf(part)] = next
    return later(withNext(next, kmOf(id)))
  },
  // GET /workshops: por distância. Só há resultados de exemplo à volta do ponto pesquisado
  searchWorkshops: ({ lat, lon, radiusKm = 10, type, q }) => {
    const found = WORKSHOPS.map(([wid, name, wtype, address, services, north, east]) => ({
      id: wid, name, type: wtype, address, services,
      latitude: Number(lat) + north / KM_PER_DEG,
      longitude: Number(lon) + east / (KM_PER_DEG * Math.cos((Number(lat) * Math.PI) / 180)),
      distanceKm: Math.round(distanceKm(north, east) * 10) / 10,
    }))
      .filter((w) => w.distanceKm <= Number(radiusKm))
      .filter((w) => !type || w.type === type)
      .filter((w) => !q || norm([w.name, w.address, ...w.services].join(' ')).includes(norm(q)))
      .sort((a, b) => a.distanceKm - b.distanceKm)
    return later(found)
  },
  getMaintenance: (id, maintenanceId) => later((maintenances[id] ?? []).find((m) => m.id === maintenanceId) ?? null),
  // POST /vehicles/{id}/maintenances: guarda a manutenção e a despesa correspondente (só em memória)
  createMaintenance: (id, data) => {
    const vehicle = vehicles.find((v) => v.id === id)
    if (data.date > iso(new Date())) return invalid('date', 'A data não pode ser no futuro.')
    const bad = (data.parts ?? []).findIndex((p) => !(parts[id] ?? []).some((x) => x.id === p.partId))
    if (bad !== -1) return invalid(`parts.${bad}.partId`, 'A peça não existe neste veículo.')
    const km = data.km ?? vehicle?.km ?? 0
    const maintenance = {
      id: crypto.randomUUID(), vehicleId: id, type: data.type, date: data.date, km,
      cost: data.cost, notes: data.notes ?? null,
      parts: (data.parts ?? []).map((p) => ({ partId: p.partId, partName: parts[id].find((x) => x.id === p.partId).name, cost: p.cost ?? null })),
      createdAt: new Date().toISOString(),
    }
    maintenances[id] = [maintenance, ...(maintenances[id] ?? [])].sort((a, b) => b.date.localeCompare(a.date))
    // Como no servidor: cada peça passa a ter este serviço como último (se não for mais antigo) e fecha o seu alerta
    for (const p of data.parts ?? []) {
      const part = parts[id].find((x) => x.id === p.partId)
      if (!part.lastServiceDate || data.date >= part.lastServiceDate) Object.assign(part, { lastServiceKm: km, lastServiceDate: data.date })
    }
    expenses[id] = [{ id: crypto.randomUUID(), category: 'MANUTENCAO', date: data.date, amount: data.cost, description: data.notes ?? null, km }, ...(expenses[id] ?? [])]
    return later({ ...maintenance, closedAlertIds: [] })
  },
  // PATCH /vehicles/{id}/maintenances/{maintenanceId}: só custo e notas
  updateMaintenance: (id, maintenanceId, data) => {
    const list = maintenances[id] ?? []
    const index = list.findIndex((m) => m.id === maintenanceId)
    if (index === -1) return later(null).then(() => { throw new ApiError({ title: 'Não encontrado', status: 404, detail: 'Manutenção não encontrada' }, 404) })
    list[index] = { ...list[index], ...data }
    return later({ ...list[index] })
  },
  // POST /vehicles: devolve o veículo criado (só em memória). Plano de peças por defeito simulado
  createVehicle: (data) => {
    if (vehicles.some((v) => v.plate === data.plate)) {
      const message = 'Já existe um veículo com esta matrícula'
      return later(null).then(() => {
        throw new ApiError({ title: 'Pedido inválido', status: 400, detail: message, errors: [{ field: 'plate', message }] }, 400)
      })
    }
    const interval = data.type === 'MOTO' ? 6000 : 15000
    const vehicle = {
      id: crypto.randomUUID(),
      type: data.type, brand: data.brand, model: data.model, firstRegistration: data.firstRegistration,
      plate: data.plate, km: data.km, openAlerts: 0,
      nextService: { partId: crypto.randomUUID(), partName: 'Revisão', targetKm: data.km + interval, kmRemaining: interval, dueDate: null },
      createdAt: new Date().toISOString(),
    }
    vehicles.push(vehicle)
    expenses[vehicle.id] = []
    maintenances[vehicle.id] = []
    // Plano por defeito do servidor: 10 000 km por peça, último serviço = km atual e hoje
    const plan = vehicle.type === 'MOTO'
      ? ['Óleo e filtro de óleo', 'Filtro de ar', 'Pastilhas de travão', 'Corrente de transmissão', 'Pneus', 'Velas de ignição']
      : ['Óleo e filtro de óleo', 'Filtro de ar', 'Filtro de habitáculo', 'Filtro de combustível', 'Pastilhas de travão', 'Pneus', 'Velas de ignição']
    parts[vehicle.id] = makeParts(vehicle.id, plan.map((name) => [name, 10000, null, 'CATALOGO', vehicle.km, iso(new Date())]))
    obdStates[vehicle.id] = { vehicleId: vehicle.id, deviceId: null, readAt: null, km: null, oilTemp: null, gps: null }
    // Cada data de validade indicada cria o prazo pendente respetivo
    deadlines[vehicle.id] = [['IUC', data.iucValidUntil], ['SEGURO', data.insuranceValidUntil], ['INSPECAO', data.inspectionValidUntil]]
      .filter(([, due]) => due)
      .map(([type, dueDate]) => ({ id: crypto.randomUUID(), vehicleId: vehicle.id, type, status: 'PENDENTE', dueDate, paidAt: null, amount: null, description: null, createdAt: new Date().toISOString() }))
    return later({ ...vehicle })
  },
  // PATCH /vehicles/{id}: só dados descritivos; devolve o veículo atualizado (só em memória)
  updateVehicle: (id, data) => {
    const index = vehicles.findIndex((v) => v.id === id)
    if (index === -1) return later(null).then(() => { throw new ApiError({ title: 'Não encontrado', status: 404, detail: 'Veículo não encontrado' }, 404) })
    if (data.plate && vehicles.some((v) => v.id !== id && v.plate === data.plate)) {
      const message = 'Já existe um veículo com esta matrícula'
      return later(null).then(() => {
        throw new ApiError({ title: 'Pedido inválido', status: 400, detail: message, errors: [{ field: 'plate', message }] }, 400)
      })
    }
    vehicles[index] = { ...vehicles[index], ...data }
    return later({ ...vehicles[index] })
  },
  // DELETE /vehicles/{id}: apaga o veículo e tudo o que lhe pertence (só em memória; um refresh repõe os dados)
  deleteVehicle: (id) => {
    removeWhere(vehicles, (v) => v.id === id)
    removeWhere(alerts, (a) => a.vehicleId === id)
    delete maintenances[id]
    delete expenses[id]
    delete obdStates[id]
    delete deadlines[id]
    delete devices[id]
    delete parts[id]
    delete inspections[id]
    return later(null)
  },
}
