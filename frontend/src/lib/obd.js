import { formatPlateDots, minutesSince } from './format.js'

// Uma leitura com mais de 24 h já não é "recente"
export const STALE_AFTER_MINUTES = 24 * 60

export const vehicleLabel = (v) => `${v.brand} ${v.model} · ${formatPlateDots(v.plate)}`

/**
 * Estado da ligação do OBD de um veículo:
 * 'unpaired' (sem dispositivo) | 'waiting' (emparelhado, sem leituras) | 'fresh' (leitura recente) | 'stale' (leitura antiga).
 * `device` vem de GET /obd-device e `obd` de GET /obd-state
 */
export function connectionOf(obd, device) {
  if (!device && !obd?.deviceId) return 'unpaired'
  if (!obd?.readAt) return 'waiting'
  return minutesSince(obd.readAt) > STALE_AFTER_MINUTES ? 'stale' : 'fresh'
}
