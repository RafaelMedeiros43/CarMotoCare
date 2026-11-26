import { request } from './client.js'

// GET /vehicles => lista com nextService e openAlerts
export const listVehicles = () => request('/vehicles')

// GET /alerts => alertas de todos os veículos (status ABERTO / FECHADO)
export const listAlerts = () => request('/alerts')

// GET /vehicles/{id}/maintenances => histórico de manutenções
export const listMaintenances = (vehicleId) => request(`/vehicles/${vehicleId}/maintenances`)

// GET /vehicles/{id}/expenses => despesas (MANUTENCAO, INSPECAO, IUC, SEGURO)
export const listExpenses = (vehicleId) => request(`/vehicles/${vehicleId}/expenses`)

// GET /vehicles/{id}/obd-state => última leitura do OBD (km, oilTemp, gps podem vir nulos)
export const getObdState = (vehicleId) => request(`/vehicles/${vehicleId}/obd-state`)

// DELETE /vehicles/{id} => 204 (sem corpo). Apaga também manutenções, inspeções, prazos e alertas (cascata)
export const deleteVehicle = (vehicleId) => request(`/vehicles/${vehicleId}`, { method: 'DELETE' })

// POST /vehicles => 201 com o veículo criado (Vehicle). Cria também o plano de peças por defeito
// e, para cada data indicada (iucValidUntil, insuranceValidUntil, inspectionValidUntil), o prazo pendente
// Corpo (VehicleCreate): { type: 'CARRO'|'MOTO', brand, model, firstRegistration: 'AAAA-MM',
//   plate: 'AB-24-CD', km: inteiro, iucValidUntil?, insuranceValidUntil?, inspectionValidUntil? } (datas 'AAAA-MM-DD')
export const createVehicle = (data) => request('/vehicles', { method: 'POST', body: data })

// PATCH /vehicles/{id} => 200 com o Vehicle atualizado. Só dados descritivos (VehicleUpdate):
// { brand?, model?, firstRegistration? 'AAAA-MM', plate? 'AB-24-CD' } com pelo menos um campo
// Não altera km, prazos nem o tipo
export const updateVehicle = (vehicleId, data) => request(`/vehicles/${vehicleId}`, { method: 'PATCH', body: data })

// Manutenções e prazos 

// GET /vehicles/{id}/maintenances/{maintenanceId} => Maintenance
export const getMaintenance = (vehicleId, maintenanceId) => request(`/vehicles/${vehicleId}/maintenances/${maintenanceId}`)

// POST /vehicles/{id}/maintenances => 201 com a manutenção + closedAlertIds (alertas fechados por ela)
// Corpo (MaintenanceCreate): { type: 'REVISAO_PERIODICA'|'MUDANCA_OLEO'|'SUBSTITUICAO_PNEUS'|'REPARACAO'|'AVARIA'|'OUTRO',
//   date: 'AAAA-MM-DD', cost: número em euros, km?: inteiro, notes?: texto, parts?: [{ partId, cost? }] }
// Cada peça indicada atualiza o último serviço (data e km) no plano do veículo.
export const createMaintenance = (vehicleId, data) => request(`/vehicles/${vehicleId}/maintenances`, { method: 'POST', body: data })

// PATCH /vehicles/{id}/maintenances/{maintenanceId} => Maintenance. Só corrige custo e notas (pelo menos um)
export const updateMaintenance = (vehicleId, maintenanceId, data) =>
  request(`/vehicles/${vehicleId}/maintenances/${maintenanceId}`, { method: 'PATCH', body: data })

// GET /vehicles/{id}/deadlines => prazos legais do veículo (IUC, SEGURO, INSPECAO), PENDENTE ou CONCLUIDO
export const listDeadlines = (vehicleId) => request(`/vehicles/${vehicleId}/deadlines`)

// POST /vehicles/{id}/deadlines => 201 com o prazo pendente criado. Corpo: { type: 'IUC'|'SEGURO'|'INSPECAO', dueDate: 'AAAA-MM-DD' }
export const createDeadline = (vehicleId, data) => request(`/vehicles/${vehicleId}/deadlines`, { method: 'POST', body: data })

// ---------- Dispositivo OBD ----------

// GET /vehicles/{id}/obd-device => ObdDevice { deviceId, name, vehicleId, pairedAt, lastReadingAt }. 404 = sem dispositivo
export const getObdDevice = (vehicleId) => request(`/vehicles/${vehicleId}/obd-device`)

// PUT /vehicles/{id}/obd-device => 200 com o ObdDevice. Corpo: { deviceId: 'OBD-7F3A-0001', name?: 'OBD do Golf' }
// Substitui o dispositivo atual; 409 se o deviceId já estiver associado a outro veículo
export const pairObdDevice = (vehicleId, data) => request(`/vehicles/${vehicleId}/obd-device`, { method: 'PUT', body: data })

// DELETE /vehicles/{id}/obd-device => 204
export const unpairObdDevice = (vehicleId) => request(`/vehicles/${vehicleId}/obd-device`, { method: 'DELETE' })

// Peças plano de manutenção
// GET /vehicles/{id}/parts => Part[] { id, vehicleId, name, intervalKm, intervalMonths, intervalSource 'CATALOGO'|'UTILIZADOR',
//   lastServiceKm, lastServiceDate, nextKm, nextDate, kmRemaining, daysRemaining }
export const listParts = (vehicleId) => request(`/vehicles/${vehicleId}/parts`)

// PATCH /vehicles/{id}/parts/{partId} => Part. Corpo: { intervalKm?, intervalMonths?, lastServiceDate?, lastServiceKm? }
// (null desativa um limite; pelo menos um limite ativo; meses até 999; data não futura; km não superior à atual).
// A origem passa a UTILIZADOR só se um intervalo ficar diferente do atual.
export const updatePart = (vehicleId, partId, data) =>
  request(`/vehicles/${vehicleId}/parts/${partId}`, { method: 'PATCH', body: data })

//Oficinas 
// GET /workshops?lat&lon&radiusKm&type&q => Workshop[] { id, name, type 'OFICINA'|'LOJA_PNEUS', address, latitude, longitude,
//   distanceKm, services[] } já por distância. (Rota ainda não existe na EAPI Web)
export function searchWorkshops({ lat, lon, radiusKm, type, q }) {
  const params = new URLSearchParams({ lat, lon })
  if (radiusKm) params.set('radiusKm', radiusKm)
  if (type) params.set('type', type)
  if (q) params.set('q', q)
  return request(`/workshops?${params}`)
}

// Custos 
// GET /vehicles/{id}/inspections => Inspection[] { id, vehicleId, date, result 'APROVADA'|'REPROVADA', amount, km, validUntil, deficiencies[] }
// (Rota ainda não existe na EAPI Web; os custos de inspeção já vêm em GET /expenses com category INSPECAO)
export const listInspections = (vehicleId) => request(`/vehicles/${vehicleId}/inspections`)

// GET /vehicles/{id}/inspections/{inspectionId} => Inspection (resultado, validade, valor pago e deficiências)
export const getInspection = (vehicleId, inspectionId) => request(`/vehicles/${vehicleId}/inspections/${inspectionId}`)

// POST /vehicles/{id}/inspections => 201 InspectionCreated (Inspection + nextDeadline + closedAlertIds)
// Corpo (InspectionCreate): { date, result 'APROVADA'|'REPROVADA', amount >= 0, deficiencies: [{ description, severity 'LIGEIRA'|'GRAVE' }] }
// A km vem do veículo. Se aprovada: calcula a validade, fecha o alerta e cria o prazo seguinte. Se reprovada: o prazo continua pendente
export const createInspection = (vehicleId, data) => request(`/vehicles/${vehicleId}/inspections`, { method: 'POST', body: data })

// POST /vehicles/{id}/expenses (pagar IUC ou seguro) => 201 Expense.
// Corpo (ExpenseCreate): { category: 'IUC'|'SEGURO', date, amount (> 0), description?, validUntil } (rota ainda não existe na EAPI Web)
export const payExpense = (vehicleId, data) => request(`/vehicles/${vehicleId}/expenses`, { method: 'POST', body: data })
