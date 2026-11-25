import { Link, useOutletContext } from 'react-router-dom'
import Icon from '../components/Icons.jsx'
import SelectField from '../components/SelectField.jsx'
import useApi from '../hooks/useApi.js'
import useRemote from '../hooks/useRemote.js'
import { formatKm, formatPlateDots } from '../lib/format.js'
import { closestPart, intervalText, lastServiceText, remainingText } from '../lib/maintenance.js'
import { vehicleLabel } from '../lib/obd.js'
import '../styles/maintenance.css'

// Figma: "18b Manutenção — Peças e intervalos"
// Dados: GET /vehicles/{id}/parts (plano de peças com próximo valor e quanto falta; calculado no servidor)

export default function PartsPlan() {
  const { vehicles, loading: garageLoading, selected, selectVehicle } = useOutletContext()
  const api = useApi()
  const { loading, data, error, reload } = useRemote(() => api.listParts(selected.id), selected?.id ?? null)

  if (garageLoading) return <p className="muted">A carregar…</p>

  const parts = data ?? []
  const closest = closestPart(parts)

  return (
    <div className="acct mnt">
      <div className="mnt-head">
        <div className="acct__head">
          <h1>Peças e intervalos</h1>
          <p>Todas as peças acompanhadas por defeito, com o intervalo de cada uma. Selecione Editar para definir o intervalo de manutenção.</p>
        </div>
        <div className="mnt-head__actions">
          <Link to="/manutencao" className="acct-btn"><Icon name="history" size={17} /> Histórico</Link>
        </div>
      </div>

      {!selected ? (
        <section className="acct-card mnt-empty">
          <h2>Ainda não tem veículos</h2>
          <Link to="/veiculos/novo" className="acct-btn acct-btn--primary">Adicionar veículo</Link>
        </section>
      ) : (
        <>
          <div className="mnt-filters">
            <SelectField id="vehicle-select" label="Veículo" value={selected.id} onChange={selectVehicle} options={vehicles.map((v) => [v.id, vehicleLabel(v)])} className="mnt-filters__vehicle" />
          </div>

          <section className="acct-card mnt-card">
            <h2>{selected.brand} {selected.model}</h2>
            <p className="mnt-card__sub">{formatPlateDots(selected.plate)} · Quilometragem atual: {formatKm(selected.km)}</p>

            {error ? (
              <div className="form-error" role="alert">
                Não foi possível carregar o plano de peças. {error.message}{' '}
                <button type="button" className="link-arrow" onClick={reload}>Tentar de novo</button>
              </div>
            ) : loading ? (
              <p className="muted mnt-card__state">A carregar as peças…</p>
            ) : parts.length === 0 ? (
              <p className="muted mnt-card__state">Este veículo ainda não tem peças acompanhadas.</p>
            ) : (
              <div className="mnt-table mnt-table--parts" role="table" aria-label="Peças e intervalos">
                <div className="mnt-table__head" role="row">
                  <span role="columnheader">Peça</span><span role="columnheader">Intervalo</span>
                  <span role="columnheader">Último serviço</span><span role="columnheader" className="is-end">Próximo</span>
                  <span role="columnheader" className="is-end">Ação</span>
                </div>
                {parts.map((p) => (
                  <div className="mnt-table__row" role="row" key={p.id}>
                    <div role="cell" className="mnt-table__what">
                      <strong>{p.name}</strong>
                      <small>{p.intervalSource === 'UTILIZADOR' ? 'Intervalo definido por si' : 'Intervalo do catálogo'}</small>
                    </div>
                    <span role="cell" className="mnt-table__muted" data-label="Intervalo">{intervalText(p)}</span>
                    <span role="cell" className="mnt-table__muted" data-label="Último serviço">{lastServiceText(p)}</span>
                    <b role="cell" className={`is-end${p.kmRemaining != null && p.kmRemaining <= 0 ? ' is-late' : ''}`} data-label="Próximo">{remainingText(p)}</b>
                    <span role="cell" className="is-end">
                      <Link to={`/manutencao/pecas/${p.id}`} className="link-arrow" aria-label={`Editar o intervalo de ${p.name}`}>Editar</Link>
                    </span>
                  </div>
                ))}
              </div>
            )}

            {!loading && !error && parts.length > 0 && (
              <div className="mnt-card__foot">
                <span>{parts.length} peças acompanhadas · Os alertas disparam com antecedência</span>
                <span className="mon-badge mon-badge--muted">Catálogo por defeito</span>
              </div>
            )}
          </section>

          {closest && (
            <section className="mnt-next">
              <Icon name="wrench" size={30} />
              <div>
                <small>Intervalo mais próximo · {selected.brand} {selected.model}</small>
                <h2>{closest.name} aos {formatKm(closest.nextKm)}</h2>
                <p>Quilometragem atual: {formatKm(selected.km)} · {closest.kmRemaining > 0 ? `Faltam ${formatKm(closest.kmRemaining)}` : 'Em atraso'}</p>
              </div>
              <Link to="/oficinas" className="acct-btn acct-btn--primary">Encontrar oficina para manutenção</Link>
            </section>
          )}
        </>
      )}
    </div>
  )
}
