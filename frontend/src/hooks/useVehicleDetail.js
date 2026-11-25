import { useEffect, useState } from 'react'
import useApi from './useApi.js'

/** Manutenções, despesas, prazos, dispositivo OBD e último estado OBD de um veículo */
export default function useVehicleDetail(vehicleId) {
  const api = useApi()
  const [detail, setDetail] = useState(null)

  useEffect(() => {
    if (!vehicleId) return
    let cancelled = false
    Promise.all([
      api.listMaintenances(vehicleId).catch(() => []),
      api.listExpenses(vehicleId).catch(() => []),
      api.getObdState(vehicleId).catch(() => null), // sem leituras / sem dispositivo (ou endpoint ainda por ligar)
      api.listDeadlines(vehicleId).catch(() => []),
      api.getObdDevice(vehicleId).catch(() => null), // 404 = sem dispositivo emparelhado
    ]).then(([maintenances, expenses, obdState, deadlines, obdDevice]) => {
      if (!cancelled) setDetail({ vehicleId, maintenances, expenses, obdState, deadlines, obdDevice })
    })
    return () => { cancelled = true }
  }, [api, vehicleId])

  const ready = !!vehicleId && detail?.vehicleId === vehicleId
  return {
    loading: !!vehicleId && !ready,
    maintenances: ready ? detail.maintenances : [],
    expenses: ready ? detail.expenses : [],
    obdState: ready ? detail.obdState : null,
    deadlines: ready ? detail.deadlines : [],
    obdDevice: ready ? detail.obdDevice : null,
  }
}
