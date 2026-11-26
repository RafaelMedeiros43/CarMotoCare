import { useCallback, useEffect, useState } from 'react'
import useApi from './useApi.js'

// Os alertas são acessórios: se falharem, os veículos continuam a aparecer
const fetchGarage = (api) => Promise.all([api.listVehicles(), api.listAlerts().catch(() => [])])

/** Veículos e alertas do utilizador + veículo selecionado (partilhado pelo menu lateral e pelas páginas) */
export default function useGarage() {
  const api = useApi()
  const [state, setState] = useState({ vehicles: null, alerts: [], error: '' })
  const [selectedId, setSelectedId] = useState(null)

  // Guarda o resultado. Mantém o veículo selecionado se ainda existir; senão escolhe o primeiro
  const apply = useCallback(([vehicles, alerts]) => {
    setState({ vehicles, alerts, error: '' })
    setSelectedId((current) => (vehicles.some((v) => v.id === current) ? current : vehicles[0]?.id ?? null))
  }, [])

  useEffect(() => {
    let cancelled = false
    fetchGarage(api)
      .then((result) => !cancelled && apply(result))
      .catch((e) => !cancelled && setState({ vehicles: [], alerts: [], error: e.message }))
    return () => { cancelled = true }
  }, [api, apply])

  // Volta a pedir os dados (ex.: depois de remover um veículo). Se falhar, mantém a lista e mostra o erro
  const reload = useCallback(async () => {
    try {
      apply(await fetchGarage(api))
    } catch (e) {
      setState((current) => ({ ...current, error: e.message }))
    }
  }, [api, apply])

  const vehicles = state.vehicles ?? []
  return {
    loading: state.vehicles === null,
    error: state.error,
    vehicles,
    alerts: state.alerts,
    selected: vehicles.find((v) => v.id === selectedId) ?? null,
    selectVehicle: setSelectedId,
    reload,
  }
}
