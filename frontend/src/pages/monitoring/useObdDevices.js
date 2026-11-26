import { useEffect, useState } from 'react'
import useApi from '../../hooks/useApi.js'

/** Dispositivo OBD de cada veículo: { [vehicleId]: ObdDevice | null } (404 = sem dispositivo) */
export default function useObdDevices(vehicles, version = 0) {
  const api = useApi()
  const [result, setResult] = useState({ key: '', devices: {} })
  const key = vehicles.map((v) => v.id).join(',') + `#${version}`

  useEffect(() => {
    if (!vehicles.length) return
    let cancelled = false
    Promise.all(vehicles.map((v) => api.getObdDevice(v.id).catch(() => null))).then((list) => {
      if (!cancelled) setResult({ key, devices: Object.fromEntries(vehicles.map((v, i) => [v.id, list[i]])) })
    })
    return () => { cancelled = true }
  }, [api, vehicles, key])

  return { loading: vehicles.length > 0 && result.key !== key, devices: result.devices }
}
