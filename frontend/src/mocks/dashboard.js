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
    nextService: { partId: 'p1', partName: 'Revisão', targetKm: 75000, kmRemaining: 6580, dueDate: null },
  },
  {
    id: MT07, type: 'MOTO', brand: 'Yamaha', model: 'MT-07', firstRegistration: '2024-03',
    plate: '12-34-EF', km: 24180, openAlerts: 0,
    nextService: { partId: 'p2', partName: 'Revisão', targetKm: 25000, kmRemaining: 820, dueDate: null },
  },
]

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
    { id: 'm4', vehicleId: MT07, type: 'MUDANCA_OLEO', date: '2026-02-14', km: 19800, cost: 60, notes: null, parts: [] },
    { id: 'm5', vehicleId: MT07, type: 'REVISAO_PERIODICA', date: '2026-04-02', km: 21000, cost: 75, notes: null, parts: [] },
    { id: 'm6', vehicleId: MT07, type: 'SUBSTITUICAO_PNEUS', date: '2026-05-20', km: 21900, cost: 50, notes: null, parts: [] },
  ],
}

const expenses = {
  [GOLF]: [
    { id: 'e1', category: 'MANUTENCAO', date: '2026-10-18', amount: 146, description: null, km: 67440 },
    { id: 'e2', category: 'MANUTENCAO', date: '2026-06-12', amount: 210, description: null, km: 62180 },
    { id: 'e3', category: 'SEGURO', date: '2026-03-02', amount: 162, description: null, km: null },
    { id: 'e4', category: 'IUC', date: '2026-06-30', amount: 148.2, description: null, km: null },
    { id: 'e5', category: 'MANUTENCAO', date: '2025-12-10', amount: 285, description: null, km: 59870 },
  ],
  [MT07]: [
    { id: 'e6', category: 'MANUTENCAO', date: '2026-02-14', amount: 60, description: null, km: 19800 },
    { id: 'e7', category: 'MANUTENCAO', date: '2026-04-02', amount: 75, description: null, km: 21000 },
    { id: 'e8', category: 'MANUTENCAO', date: '2026-05-20', amount: 50, description: null, km: 21900 },
  ],
}

const obdStates = {
  [GOLF]: {
    vehicleId: GOLF, deviceId: 'OBD-DEMO-01',
    readAt: new Date(Date.now() - 12 * 60 * 1000).toISOString(),
    km: 68420, oilTemp: 89, gps: { latitude: 37.7417, longitude: -25.671 },
  },
  [MT07]: { vehicleId: MT07, deviceId: 'OBD-DEMO-02', readAt: new Date(Date.now() - 40 * 60 * 1000).toISOString(), km: 24180, oilTemp: 74, gps: null },
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
    obdStates[vehicle.id] = { vehicleId: vehicle.id, deviceId: null, readAt: null, km: null, oilTemp: null, gps: null }
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
    return later(null)
  },
}
