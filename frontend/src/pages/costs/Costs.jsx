import { Link, useLocation, useOutletContext } from 'react-router-dom'
import { useState } from 'react'
import Icon from '../../components/ui/Icons.jsx'
import SelectField from '../../components/ui/SelectField.jsx'
import useApi from '../../hooks/useApi.js'
import useRemote from '../../hooks/useRemote.js'
import { count, CATEGORY_LABEL, inPeriod, interventionsText, periodLabel, periodOptions, RESULT_LABEL, sum } from '../../lib/costs.js'
import { currentYear, formatDate, formatEuro, formatKm, formatPlateDots } from '../../lib/format.js'
import { typeTitle } from '../../lib/maintenance.js'
import { vehicleLabel } from '../../lib/obd.js'
import '../../styles/maintenance.css'
import '../../styles/costs.css'

// Figma: "22 Custos de manutenção", "22b Custos — Yamaha MT-07" (+ seletor aberto, estado vazio, a carregar, custo guardado)
// Dados: GET /vehicles/{id}/expenses (MANUTENCAO, INSPECAO, IUC, SEGURO; o período filtra-se aqui), GET /maintenances (tipo e km),
// GET /deadlines (próxima inspeção) e GET /inspections (resultado). Os dois últimos são opcionais: se falharem, o ecrã continua

const load = (api, id) => Promise.all([
  api.listMaintenances(id),
  api.listExpenses(id),
  api.listDeadlines(id).catch(() => []),
  api.listInspections(id).catch(() => []),
]).then(([maintenances, expenses, deadlines, inspections]) => ({ maintenances, expenses, deadlines, inspections }))

export default function Costs() {
  const { vehicles, loading: garageLoading, selected, selectVehicle } = useOutletContext()
  const api = useApi()
  const location = useLocation()
  const [period, setPeriod] = useState(String(currentYear()))
  const { loading, data, error, reload } = useRemote(() => load(api, selected.id), selected?.id ?? null)

  if (garageLoading) return <p className="muted">A carregar…</p>

  const saved = location.state?.saved
  const name = selected ? `${selected.brand} ${selected.model}` : ''

  return (
    <div className="acct mnt">
      {saved && selected && (
        <div className="cst-banner cst-banner--ok" role="status">
          <strong>Custo guardado</strong>
          <span>
            O {saved.category === 'SEGURO' ? 'seguro' : 'IUC'} {saved.vehicleType === 'MOTO' ? 'da mota' : 'do carro'} ({formatEuro(saved.amount)}) foi
            adicionado aos custos da {saved.vehicleName} e os totais foram atualizados
          </span>
        </div>
      )}
      {loading && selected && (
        <div className="cst-banner cst-banner--loading" role="status">
          <strong>A carregar os custos...</strong>
          <span>A obter os registos da {name} · {formatPlateDots(selected.plate)}</span>
        </div>
      )}

      <div className="mnt-head">
        <div className="acct__head">
          <h1>Custos</h1>
          <p>Manutenção, IUC e seguro de cada veículo, num só lugar</p>
        </div>
        <Link to="/custos/registar" className="acct-btn acct-btn--primary"><Icon name="plus" size={17} /> Registar custo</Link>
      </div>

      {!selected ? (
        <section className="acct-card mnt-empty">
          <h2>Ainda não tem veículos</h2>
          <p>Adicione um veículo para ver os custos</p>
          <Link to="/veiculos/novo" className="acct-btn acct-btn--primary">Adicionar veículo</Link>
        </section>
      ) : error ? (
        <div className="form-error" role="alert">
          Não foi possível carregar os custos. {error.message}{' '}
          <button type="button" className="link-arrow" onClick={reload}>Tentar de novo</button>
        </div>
      ) : (
        <Content vehicles={vehicles} selected={selected} selectVehicle={selectVehicle} period={period} setPeriod={setPeriod} data={loading ? null : data} />
      )}
    </div>
  )
}

function Content({ vehicles, selected, selectVehicle, period, setPeriod, data }) {
  const name = `${selected.brand} ${selected.model}`
  const years = data ? periodOptions([...data.expenses, ...data.maintenances].map((x) => x.date), currentYear()) : [[period, period]]

  const filters = (
    <div className="mnt-filters">
      <SelectField id="vehicle-select" label="Veículo" value={selected.id} onChange={selectVehicle} options={vehicles.map((v) => [v.id, vehicleLabel(v)])} className="mnt-filters__vehicle" />
      <SelectField id="period" label="Período" value={period} onChange={setPeriod} options={years} />
    </div>
  )

  if (!data) return <>{filters}<Skeleton /></>

  // O período filtra-se aqui
  const maintenances = data.maintenances.filter((m) => inPeriod(m.date, period))
  const payments = data.expenses.filter((e) => ['IUC', 'SEGURO'].includes(e.category) && inPeriod(e.date, period))
  const inspectionCosts = data.expenses.filter((e) => e.category === 'INSPECAO' && inPeriod(e.date, period))
  const maintenanceTotal = sum(maintenances, 'cost')
  const paymentsTotal = sum(payments)
  const inspectionsTotal = sum(inspectionCosts)
  const empty = maintenances.length + payments.length + inspectionCosts.length === 0
  const nextInspection = data.deadlines.filter((d) => d.type === 'INSPECAO' && d.status === 'PENDENTE').map((d) => d.dueDate).sort()[0]
  const resultOf = (e) => data.inspections.find((i) => i.date === e.date && Number(i.amount) === Number(e.amount))?.result

  // Peso de cada intervenção (por tipo) no período
  const byType = {}
  for (const m of maintenances) byType[typeTitle(m.type)] = (byType[typeTitle(m.type)] ?? 0) + Number(m.cost)
  const bars = Object.entries(byType)
  const biggest = Math.max(...bars.map(([, v]) => v), 1)

  const sub = `${name} · ${formatPlateDots(selected.plate)} · ${periodLabel(period)}`
  const allMaintenances = data.maintenances

  return (
    <>
      {filters}

      <div className="cst-cards">
        <div className="cst-card cst-card--main">
          <span>Manutenção · {periodLabel(period)}</span>
          <strong>{formatEuro(maintenanceTotal)}</strong>
          <small>{count(maintenances.length, 'custo de manutenção registado', 'custos de manutenção registados')}</small>
        </div>
        <div className="cst-card">
          <span>{name} · Todos os registos</span>
          <strong>{formatEuro(sum(allMaintenances, 'cost'))}</strong>
          <small>{interventionsText(allMaintenances, period)}</small>
        </div>
        <div className="cst-card">
          <span>{name} · IUC e seguro · {periodLabel(period)}</span>
          <strong>{formatEuro(paymentsTotal)}</strong>
          <small>{payments.length ? count(payments.length, 'pagamento registado', 'pagamentos registados') : 'Sem pagamentos registados'}</small>
        </div>
      </div>

      <section className="acct-card mnt-card">
        <h2>Custos associados às manutenções</h2>
        <p className="mnt-card__sub">{sub}</p>
        <div className="mnt-table" role="table" aria-label="Custos associados às manutenções">
          <div className="mnt-table__head" role="row">
            <span role="columnheader">Intervenção</span><span role="columnheader">Data</span>
            <span role="columnheader">Quilometragem</span><span role="columnheader" className="is-end">Custo</span>
          </div>
          {maintenances.length === 0 && (
            <div className="cst-empty" role="row">
              <strong>Ainda não há custos registados para {selected.type === 'MOTO' ? 'esta mota' : 'este carro'}</strong>
              <span>Use «Registar custo» para começar o histórico da {name}</span>
            </div>
          )}
          {maintenances.map((m) => (
            <div className="mnt-table__row" role="row" key={m.id}>
              <div role="cell" className="mnt-table__what">
                <strong>{typeTitle(m.type)}</strong>
                <small>{name} · {formatPlateDots(selected.plate)}</small>
                {m.notes && <small className="mnt-table__notes">{m.notes}</small>}
              </div>
              <span role="cell" className="mnt-table__muted" data-label="Data">{formatDate(m.date)}</span>
              <span role="cell" className="mnt-table__muted" data-label="Quilometragem">{formatKm(m.km)}</span>
              <b role="cell" className="is-end" data-label="Custo">{formatEuro(m.cost)}</b>
            </div>
          ))}
        </div>
        <p className="cst-total"><span>Acumulado dos registos no período selecionado</span><b>{formatEuro(maintenanceTotal)}</b></p>
      </section>

      {payments.length > 0 && (
        <section className="acct-card mnt-card">
          <h2>IUC e seguro</h2>
          <p className="mnt-card__sub">{sub}</p>
          <div className="mnt-table" role="table" aria-label="IUC e seguro">
            <div className="mnt-table__head" role="row">
              <span role="columnheader">Descrição</span><span role="columnheader">Data</span>
              <span role="columnheader">Categoria</span><span role="columnheader" className="is-end">Valor</span>
            </div>
            {payments.map((e) => (
              <div className="mnt-table__row" role="row" key={e.id}>
                <div role="cell" className="mnt-table__what">
                  <strong>{e.description || CATEGORY_LABEL[e.category]}</strong>
                  <small>{name} · {formatPlateDots(selected.plate)}</small>
                </div>
                <span role="cell" className="mnt-table__muted" data-label="Data">{formatDate(e.date)}</span>
                <span role="cell" className="mnt-table__muted" data-label="Categoria">{CATEGORY_LABEL[e.category]}</span>
                <b role="cell" className="is-end" data-label="Valor">{formatEuro(e.amount)}</b>
              </div>
            ))}
          </div>
          <p className="cst-total"><span>Acumulado do período selecionado</span><b>{formatEuro(paymentsTotal)}</b></p>
        </section>
      )}

      {!empty && (
        <section className="acct-card mnt-card">
          <h2>Inspeções</h2>
          <p className="mnt-card__sub">{sub}</p>
          <div className="mnt-table" role="table" aria-label="Inspeções">
            <div className="mnt-table__head" role="row">
              <span role="columnheader">Descrição</span><span role="columnheader">Data</span>
              <span role="columnheader">Resultado</span><span role="columnheader" className="is-end">Valor</span>
            </div>
            {inspectionCosts.length === 0 && (
              <div className="mnt-table__row" role="row">
                <div role="cell" className="mnt-table__what">
                  <strong>{period === 'ALL' ? 'Sem inspeções registadas' : `Sem inspeções em ${period}`}</strong>
                  {nextInspection && <small>Próxima inspeção periódica: {formatDate(nextInspection)}</small>}
                </div>
                <span role="cell" className="mnt-table__muted">—</span>
                <span role="cell" className="mnt-table__muted">—</span>
                <b role="cell" className="is-end">{formatEuro(0)}</b>
              </div>
            )}
            {inspectionCosts.map((e) => (
              <div className="mnt-table__row" role="row" key={e.id}>
                <div role="cell" className="mnt-table__what">
                  <strong>{e.description || 'Inspeção periódica'}</strong>
                  <small>{name} · {formatPlateDots(selected.plate)}</small>
                </div>
                <span role="cell" className="mnt-table__muted" data-label="Data">{formatDate(e.date)}</span>
                <span role="cell" className="mnt-table__muted" data-label="Resultado">{RESULT_LABEL[resultOf(e)] ?? '—'}</span>
                <b role="cell" className="is-end" data-label="Valor">{formatEuro(e.amount)}</b>
              </div>
            ))}
          </div>
          <p className="cst-total"><span>Acumulado do período selecionado</span><b>{formatEuro(inspectionsTotal)}</b></p>
        </section>
      )}

      {bars.length > 1 && (
        <ul className="cst-bars" aria-label="Peso de cada intervenção no período">
          {bars.map(([label, value]) => (
            <li key={label}>
              <span>{label}</span><b>{formatEuro(value)}</b>
              <i style={{ width: `${(value / biggest) * 100}%` }} />
            </li>
          ))}
        </ul>
      )}

      <aside className="mon-info" role="note">
        <Icon name="info" size={19} />
        {empty ? (
          <div>
            <strong>Sem registos não significa sem despesas</strong>
            <p>Os totais refletem apenas os custos introduzidos no CarMotoCare. Ainda não existem registos de manutenção, IUC e seguro para a {selected.brand}.</p>
          </div>
        ) : (
          <div>
            <strong>Histórico da {selected.brand}</strong>
            <p>
              Manutenção: {formatEuro(maintenanceTotal)} em {count(maintenances.length, 'intervenção', 'intervenções')}.
              {inspectionCosts.length > 0 && ` Inspeção: ${formatEuro(inspectionsTotal)}.`}
              {payments.length > 0 ? ` IUC e seguro: ${formatEuro(paymentsTotal)}.` : ' IUC e seguro sem pagamentos registados.'}
            </p>
          </div>
        )}
      </aside>
    </>
  )
}

function Skeleton() {
  return (
    <>
      <div className="cst-cards" aria-hidden="true">
        {[0, 1, 2].map((i) => (
          <div key={i} className={`cst-card${i === 0 ? ' cst-card--main' : ''}`}>
            <i className="mon-skel" style={{ width: '60%' }} /><i className="mon-skel" style={{ width: '40%', height: 24 }} /><i className="mon-skel" style={{ width: '70%' }} />
          </div>
        ))}
      </div>
      <section className="acct-card mnt-card" aria-hidden="true">
        <h2>Custos associados às manutenções</h2>
        {[0, 1, 2, 3].map((i) => <i key={i} className="mon-skel cst-skel-row" />)}
      </section>
    </>
  )
}
