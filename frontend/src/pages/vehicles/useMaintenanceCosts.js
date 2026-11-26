import { useEffect, useState } from 'react'
import useApi from '../../hooks/useApi.js'
import { currentYear } from '../../lib/format.js'

/**
 * Custo de manutenção do ano corrente por veículo: { [vehicleId]: euros }.
 * Usa GET /vehicles/{id}/expenses (categoria MANUTENCAO). Se o pedido de um veículo falhar,
 * esse veículo fica de fora e o cartão simplesmente não mostra a linha de custos.
 */
export default function useMaintenanceCosts(vehicles) {
  const api = useApi()
  const [costs, setCosts] = useState({})
  const key = vehicles.map((v) => v.id).join(',') // só volta a pedir quando a lista de veículos muda

  useEffect(() => {
    if (!key) return
    let cancelled = false
    const year = String(currentYear())

    Promise.all(
      key.split(',').map(async (id) => {
        try {
          const expenses = await api.listExpenses(id)
          const total = expenses
            .filter((e) => e.category === 'MANUTENCAO' && e.date.startsWith(year))
            .reduce((sum, e) => sum + Number(e.amount), 0) // Number(): o valor pode chegar como texto
          return [id, total]
        } catch {
          return [id, null]
        }
      }),
    ).then((entries) => !cancelled && setCosts(Object.fromEntries(entries)))

    return () => { cancelled = true }
  }, [api, key])

  return costs
}
