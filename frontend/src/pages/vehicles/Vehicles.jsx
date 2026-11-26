import { useMemo, useState } from 'react'
import { Link, useOutletContext } from 'react-router-dom'
import Icon from '../../components/ui/Icons.jsx'
import RemoveVehicleDialog from './components/RemoveVehicleDialog.jsx'
import useApi from '../../hooks/useApi.js'
import useMaintenanceCosts from './useMaintenanceCosts.js'
import { currentYear, formatEuro, formatKm, formatYearMonth } from '../../lib/format.js'
import './vehicles.css'

// Figma: "08 Os meus veículos"
// Dados: GET /vehicles (já carregado no AppShell por useGarage), GET /vehicles/{id}/expenses (custos do ano)
// e DELETE /vehicles/{id} (remover).

const TYPE = {
  CARRO: { label: 'Automóvel', icon: 'car' },
  MOTO: { label: 'Motociclo', icon: 'bike' },
}

const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`

function VehicleCard({ vehicle, maintenanceCost, onOpen, onRemove }) {
  const kind = TYPE[vehicle.type] ?? TYPE.CARRO
  const next = vehicle.nextService
  const name = `${vehicle.brand} ${vehicle.model}`

  return (
    <li className="fleet-card">
      <div className="fleet-card__top">
        <span className="fleet-card__icon"><Icon name={kind.icon} size={28} /></span>
        <div className="fleet-card__id">
          <h2>{name}</h2>
          <span>{kind.label} · {formatYearMonth(vehicle.firstRegistration)}</span>
        </div>
        <span className="fleet-card__badge">{kind.label}</span>
      </div>

      <div className="fleet-card__data">
        <div>
          <small>Matrícula</small>
          <span className="fleet-card__plate">{vehicle.plate}</span>
        </div>
        <div>
          <small>Quilometragem atual</small>
          <strong>{formatKm(vehicle.km)}</strong>
        </div>
      </div>

      <div className="fleet-card__summary">
        <p>{next ? `Próxima manutenção aos ${formatKm(next.targetKm)}` : 'Sem plano de manutenção ativo'}</p>
        {maintenanceCost > 0 && <p>Custos de manutenção: {formatEuro(maintenanceCost)} em {currentYear()}</p>}
      </div>

      <div className="fleet-card__actions">
        <Link to={`/veiculos/${vehicle.id}`} className="acct-btn acct-btn--primary" onClick={() => onOpen(vehicle.id)} aria-label={`Consultar ${name}`}>
          <Icon name="arrowRight" size={16} />
          Consultar
        </Link>
        <Link to={`/veiculos/${vehicle.id}/editar`} className="acct-btn" onClick={() => onOpen(vehicle.id)} aria-label={`Editar ${name}`}>
          Editar
        </Link>
        <button type="button" className="fleet-remove" onClick={() => onRemove(vehicle)} aria-label={`Remover ${name}`}>
          Remover
        </button>
      </div>
    </li>
  )
}

export default function Vehicles() {
  const { vehicles, loading, error, selectVehicle, reload } = useOutletContext()
  const api = useApi()
  const costs = useMaintenanceCosts(vehicles)

  const [filter, setFilter] = useState('ALL') // 'ALL' ou o id de um veículo
  const [toRemove, setToRemove] = useState(null) // veículo à espera de confirmação

  // Se o veículo filtrado desaparecer ( removido), volta a "Mostrar todos"
  const activeFilter = vehicles.some((v) => v.id === filter) ? filter : 'ALL'
  const shown = activeFilter === 'ALL' ? vehicles : vehicles.filter((v) => v.id === activeFilter)

  const countText = useMemo(() => {
    const cars = vehicles.filter((v) => v.type === 'CARRO').length
    const bikes = vehicles.filter((v) => v.type === 'MOTO').length
    return [cars && plural(cars, 'automóvel', 'automóveis'), bikes && plural(bikes, 'motociclo', 'motociclos')]
      .filter(Boolean)
      .join(' · ')
  }, [vehicles])

  const confirmRemove = async (vehicle) => {
    await api.deleteVehicle(vehicle.id)
    await reload()
    setToRemove(null)
  }

  return (
    <div className="dash fleet">
      <div className="dash__head">
        <div>
          <h1>Os meus veículos</h1>
          <p>A sua garagem pessoal. Um lugar para cada carro e moto.</p>
        </div>
        <Link to="/veiculos/novo" className="btn btn--primary btn--sm">
          <Icon name="plus" size={16} />
          Adicionar veículo
        </Link>
      </div>

      {error && (
        <div className="form-error" role="alert">
          {error}{' '}
          <button type="button" className="fleet-retry" onClick={reload}>Tentar novamente</button>
        </div>
      )}
      {loading && !error && <p className="muted">A carregar…</p>}

      {!loading && !error && vehicles.length === 0 && (
        <section className="card card--empty">
          <h2 className="card__title">Ainda não tem veículos</h2>
          <p className="card__sub">Adicione o seu primeiro automóvel ou motociclo para começar a acompanhar manutenção, custos e prazos.</p>
          <Link to="/veiculos/novo" className="btn btn--primary btn--sm">
            <Icon name="plus" size={16} />
            Adicionar veículo
          </Link>
        </section>
      )}

      {vehicles.length > 0 && (
        <>
          <div className="fleet-filter">
            <div className="fleet-field">
              <label htmlFor="fleet-select">Selecionar veículo</label>
              <span className="fleet-select">
                <select id="fleet-select" value={activeFilter} onChange={(e) => setFilter(e.target.value)}>
                  <option value="ALL">Mostrar todos</option>
                  {vehicles.map((v) => (
                    <option key={v.id} value={v.id}>{v.brand} {v.model} · {v.plate}</option>
                  ))}
                </select>
                <Icon name="chevronDown" size={16} />
              </span>
            </div>
            <span className="fleet-count">{countText}</span>
          </div>

          <ul className="fleet-grid">
            {shown.map((v) => (
              <VehicleCard key={v.id} vehicle={v} maintenanceCost={costs[v.id]} onOpen={selectVehicle} onRemove={setToRemove} />
            ))}
          </ul>

          <div className="fleet-note">
            <span className="fleet-note__icon"><Icon name="car" size={18} /></span>
            <div>
              <strong>Cada veículo tem o seu histórico.</strong>
              <p>Consulte o detalhe para gerir a manutenção, a quilometragem, os custos e os lembretes.</p>
            </div>
          </div>
        </>
      )}

      <RemoveVehicleDialog vehicle={toRemove} onClose={() => setToRemove(null)} onConfirm={confirmRemove} />
    </div>
  )
}
