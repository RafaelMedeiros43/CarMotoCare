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
