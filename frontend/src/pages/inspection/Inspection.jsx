import { Link, useOutletContext } from 'react-router-dom'
import Icon from '../../components/ui/Icons.jsx'
import SelectField from '../../components/ui/SelectField.jsx'
import useApi from '../../hooks/useApi.js'
import useRemote from '../../hooks/useRemote.js'
import { formatDate, formatEuro, formatKm, formatPlateDots } from '../../lib/format.js'
import { deficienciesText, newestFirst, resultLabel, validityOf, validityText } from '../../lib/inspection.js'
import { vehicleLabel } from '../../lib/obd.js'
import '../../styles/maintenance.css'
import './inspection.css'

// Figma: "27 Inspeção", "27b Inspeção — Yamaha MT-07" (+ seletor de veículo aberto)
// Dados: GET /vehicles/{id}/inspections (resultado, valor, km, validade e deficiências). A validade vem da última inspeção aprovada
// A rota ainda não existe na EAPI Web: nesse caso (404/501) mostra-se um aviso em vez do histórico

export default function Inspection() {
  const { vehicles, loading: garageLoading, selected, selectVehicle } = useOutletContext()
  const api = useApi()
  const { loading, data, error, reload } = useRemote(() => api.listInspections(selected.id), selected?.id ?? null)

  if (garageLoading) return <p className="muted">A carregar…</p>

  const list = newestFirst(data ?? [])
  const last = list[0]
  const validity = validityOf(list)
  const unavailable = error && [404, 405, 501].includes(error.status)

  return (
    <div className="acct mnt">
      <div className="mnt-head">
        <div className="acct__head">
          <h1>Inspeção</h1>
          <p>Resultado, total e validade da inspeção periódica de cada veículo</p>
        </div>
        <Link to="/inspecao/registar" className="acct-btn acct-btn--primary"><Icon name="plus" size={17} /> Registar inspeção</Link>
      </div>

      {!selected ? (
        <section className="acct-card mnt-empty">
          <h2>Ainda não tem veículos</h2>
          <p>Adicione um veículo para acompanhar as inspeções</p>
          <Link to="/veiculos/novo" className="acct-btn acct-btn--primary">Adicionar veículo</Link>
        </section>
      ) : (
        <>
          <div className="mnt-filters">
            <SelectField id="vehicle-select" label="Veículo" value={selected.id} onChange={selectVehicle} className="mnt-filters__vehicle"
              options={vehicles.map((v) => [v.id, vehicleLabel(v)])} />
          </div>

          {error ? (
            <div className="form-error" role="alert">
              {unavailable ? 'As inspeções ainda não estão disponíveis no servidor' : `Não foi possível carregar as inspeções ${error.message}`}{' '}
              {!unavailable && <button type="button" className="link-arrow" onClick={reload}>Tentar de novo</button>}
            </div>
          ) : (
            <>
              <div className="cst-cards">
                <div className="cst-card cst-card--main">
                  <span>Resultado da última inspeção</span>
                  {loading ? <i className="mon-skel ins-skel" /> : last ? (
                    <>
                      <strong className={last.result === 'REPROVADA' ? 'is-late' : ''}>{resultLabel(last.result)}</strong>
                      <small>Realizada a {formatDate(last.date)} · {deficienciesText(last.deficiencies)}</small>
                    </>
                  ) : (<><strong>—</strong><small>Sem inspeções registadas</small></>)}
                </div>
                <div className="cst-card">
                  <span>Total da inspeção mais recente</span>
                  {loading ? <i className="mon-skel ins-skel" /> : <strong>{last ? formatEuro(last.amount) : '—'}</strong>}
                  <small>Valor pago no centro de inspeção</small>
                </div>
                <div className="cst-card">
                  <span>Válida até</span>
                  {loading ? <i className="mon-skel ins-skel" /> : validity ? (
                    <>
                      <strong className={validity.days < 0 ? 'is-late' : ''}>{formatDate(validity.validUntil)}</strong>
                      <small className={validity.days < 0 ? 'is-late' : ''}>{validityText(validity.days)}</small>
                    </>
                  ) : (<><strong>—</strong><small>Sem inspeção aprovada</small></>)}
                </div>
              </div>

              <section className="acct-card mnt-card">
                <h2>Histórico de inspeções</h2>
                <p className="mnt-card__sub">{vehicleLabel(selected)}</p>
                {loading ? (
                  <div className="mnt-card__state" role="status">A carregar…</div>
                ) : list.length === 0 ? (
                  <div className="cst-empty">
                    <strong>Sem inspeções registadas</strong>
                    <span>Registe a primeira inspeção periódica deste veículo</span>
                  </div>
                ) : (
                  <div className="mnt-table ins-table" role="table" aria-label="Histórico de inspeções">
                    <div className="mnt-table__head" role="row">
                      <span>Inspeção</span><span>Data</span><span>Quilometragem</span><span className="is-end">Custo</span><span />
                    </div>
                    {list.map((i) => (
                      <div className="mnt-table__row" role="row" key={i.id}>
                        <div className="mnt-table__what">
                          <strong>Inspeção periódica</strong>
                          <small>{resultLabel(i.result)} · {deficienciesText(i.deficiencies)}</small>
                        </div>
                        <span className="mnt-table__muted">{formatDate(i.date)}</span>
                        <span className="mnt-table__muted">{formatKm(i.km)}</span>
                        <b className="is-end">{formatEuro(i.amount)}</b>
                        <Link to={`/inspecao/${selected.id}/${i.id}`} className="acct-btn ins-detail" aria-label={`Detalhes da inspeção de ${formatDate(i.date)} · ${formatPlateDots(selected.plate)}`}>Detalhes</Link>
                      </div>
                    ))}
                  </div>
                )}
                <div className="mnt-card__foot">
                  <span>Total da inspeção mais recente</span>
                  <b className="ins-total">{last ? formatEuro(last.amount) : '—'}</b>
                </div>
              </section>
            </>
          )}
        </>
      )}
    </div>
  )
}
