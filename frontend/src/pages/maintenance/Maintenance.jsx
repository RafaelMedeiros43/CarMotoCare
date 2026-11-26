import { useState } from 'react'
import { Link, useOutletContext } from 'react-router-dom'
import Icon from '../../components/ui/Icons.jsx'
import SelectField from '../../components/ui/SelectField.jsx'
import useApi from '../../hooks/useApi.js'
import useRemote from '../../hooks/useRemote.js'
import { formatDate, formatEuro, formatKm, formatPlateDots } from '../../lib/format.js'
import { MAINTENANCE_TYPES, PERIODS, periodStart, typeTitle } from '../../lib/maintenance.js'
import { vehicleLabel } from '../../lib/obd.js'
import '../../styles/maintenance.css'

// Figma: "18 Manutenção" (histórico)
// Dados: GET /vehicles/{id}/maintenances (devolve tudo; o período e o tipo filtram-se aqui, no browser)
// e vehicle.nextService (GET /vehicles) para o rodapé "Próxima manutenção"

export default function Maintenance() {
  const { vehicles, loading: garageLoading, selected, selectVehicle } = useOutletContext()
  const api = useApi()
  const [period, setPeriod] = useState('ALL')
  const [type, setType] = useState('ALL')
  const { loading, data, error, reload } = useRemote(() => api.listMaintenances(selected.id), selected?.id ?? null)

  if (garageLoading) return <p className="muted">A carregar…</p>

  const header = (
    <div className="mnt-head">
      <div className="acct__head">
        <h1>Histórico de manutenção</h1>
        <p>Cada intervenção, com a data, os quilómetros e o custo associado</p>
      </div>
      <div className="mnt-head__actions">
        <Link to="/manutencao/nova" className="acct-btn acct-btn--primary"><Icon name="plus" size={17} /> Adicionar manutenção</Link>
        <Link to="/manutencao/pecas" className="acct-btn">Peças e intervalos</Link>
      </div>
    </div>
  )

  if (!selected) {
    return (
      <div className="acct mnt">
        {header}
        <section className="acct-card mnt-empty">
          <h2>Ainda não tem veículos</h2>
          <p>Adicione um veículo para começar a registar as manutenções</p>
          <Link to="/veiculos/novo" className="acct-btn acct-btn--primary">Adicionar veículo</Link>
        </section>
      </div>
    )
  }

  const from = periodStart(PERIODS.find(([v]) => v === period)[2])
  const all = data ?? []
  const rows = all.filter((m) => (!from || m.date >= from) && (type === 'ALL' || m.type === type))
  const filtered = period !== 'ALL' || type !== 'ALL'
  const next = selected.nextService

  return (
    <div className="acct mnt">
      {header}

      <div className="mnt-filters">
        <SelectField id="vehicle-select" label="Veículo" value={selected.id} onChange={selectVehicle} options={vehicles.map((v) => [v.id, vehicleLabel(v)])} className="mnt-filters__vehicle" />
        <SelectField id="period" label="Período" value={period} onChange={setPeriod} options={PERIODS.map(([v, text]) => [v, text])} />
        <SelectField id="type" label="Intervenção" value={type} onChange={setType} options={[['ALL', 'Todas'], ...MAINTENANCE_TYPES]} />
      </div>

      <section className="acct-card mnt-card">
        <h2>{selected.brand} {selected.model}</h2>
        <p className="mnt-card__sub">{formatPlateDots(selected.plate)} · {filtered ? 'Histórico filtrado' : 'Histórico completo'}</p>

        {error ? (
          <div className="form-error" role="alert">
            Não foi possível carregar o histórico. {error.message}{' '}
            <button type="button" className="link-arrow" onClick={reload}>Tentar de novo</button>
          </div>
        ) : loading ? (
          <p className="muted mnt-card__state">A carregar o histórico…</p>
        ) : all.length === 0 ? (
          <div className="mnt-empty mnt-empty--inline">
            <strong>Ainda sem manutenções registadas</strong>
            <p>Registe a primeira intervenção deste veículo para construir o histórico</p>
            <Link to="/manutencao/nova" className="acct-btn acct-btn--primary acct-btn--self">Adicionar manutenção</Link>
          </div>
        ) : rows.length === 0 ? (
          <div className="mnt-empty mnt-empty--inline">
            <strong>Nenhuma intervenção com estes filtros</strong>
            <button type="button" className="acct-btn acct-btn--self" onClick={() => { setPeriod('ALL'); setType('ALL') }}>Limpar filtros</button>
          </div>
        ) : (
          <div className="mnt-table" role="table" aria-label="Histórico de manutenção">
            <div className="mnt-table__head" role="row">
              <span role="columnheader">Intervenção</span><span role="columnheader">Data</span>
              <span role="columnheader">Quilometragem</span><span role="columnheader" className="is-end">Custo</span>
            </div>
            {rows.map((m) => (
              <div className="mnt-table__row" role="row" key={m.id}>
                <div role="cell" className="mnt-table__what">
                  <strong>{typeTitle(m.type)}</strong>
                  <small>{vehicleLabel(selected)}{m.parts.length > 0 && ` · ${m.parts.map((p) => p.partName).filter(Boolean).join(', ')}`}</small>
                  {m.notes && <small className="mnt-table__notes">{m.notes}</small>}
                </div>
                <span role="cell" className="mnt-table__muted" data-label="Data">{formatDate(m.date)}</span>
                <span role="cell" className="mnt-table__muted" data-label="Quilometragem">{formatKm(m.km)}</span>
                <b role="cell" className="is-end" data-label="Custo">{formatEuro(m.cost)}</b>
              </div>
            ))}
          </div>
        )}

        {!loading && !error && all.length > 0 && (
          <div className="mnt-card__foot">
            <span>{rows.length} {rows.length === 1 ? 'intervenção' : 'intervenções'} · {filtered ? `${all.length} no total` : 'Todos os registos apresentados'}</span>
            <span className="mon-badge mon-badge--muted">{filtered ? 'Resultados filtrados' : 'Histórico completo'}</span>
          </div>
        )}
      </section>

      {next && (
        <section className="mnt-next">
          <Icon name="wrench" size={30} />
          <div>
            <h2>Próxima manutenção aos {formatKm(next.targetKm)}</h2>
            <p>Quilometragem atual: {formatKm(selected.km)} · Faltam {formatKm(next.kmRemaining)}</p>
          </div>
          <Link to="/oficinas" className="acct-btn acct-btn--primary">Encontrar oficina para manutenção</Link>
        </section>
      )}
    </div>
  )
}
