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
// e, para cada data indicada (iucValidUntil, insuranceValidUntil, inspectionValidUntil), o prazo pendente.
// Corpo (VehicleCreate): { type: 'CARRO'|'MOTO', brand, model, firstRegistration: 'AAAA-MM',
//   plate: 'AB-24-CD', km: inteiro, iucValidUntil?, insuranceValidUntil?, inspectionValidUntil? } (datas 'AAAA-MM-DD')
export const createVehicle = (data) => request('/vehicles', { method: 'POST', body: data })

// PATCH /vehicles/{id} => 200 com o Vehicle atualizado. Só dados descritivos (VehicleUpdate):
// { brand?, model?, firstRegistration? 'AAAA-MM', plate? 'AB-24-CD' } com pelo menos um campo.
// Não altera km, prazos nem o tipo.
export const updateVehicle = (vehicleId, data) => request(`/vehicles/${vehicleId}`, { method: 'PATCH', body: data })
