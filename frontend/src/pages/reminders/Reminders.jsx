import { Link, useOutletContext } from 'react-router-dom'
import { useState } from 'react'
import Icon from '../../components/ui/Icons.jsx'
import SelectField from '../../components/ui/SelectField.jsx'
import useApi from '../../hooks/useApi.js'
import useRemote from '../../hooks/useRemote.js'
import { daysUntil, formatDate, formatKm, formatPlateDots, formatToday } from '../../lib/format.js'
import '../../styles/maintenance.css'
import './reminders.css'

// Figma: "26 Alertas e lembretes" e "26 Alertas e lembretes — Lembrete em atraso"
// Dados: GET /vehicles/{id}/deadlines (prazos PENDENTE de IUC, SEGURO e INSPECAO) e `nextService` do veículo (GET /vehicles)
// GET /alerts (ainda sem rota na EAPI Web) só é usado no painel; aqui os lembretes saem dos prazos e da próxima manutenção

const TYPE_FILTERS = [['', 'Todos os lembretes'], ['INSPECAO', 'Inspeção'], ['SEGURO', 'Seguro'], ['IUC', 'IUC'], ['MANUTENCAO', 'Manutenção']]
const DEADLINE_TEXT = {
  INSPECAO: { title: 'Inspeção periódica', short: 'inspeção', action: 'Registar inspeção', to: '/inspecao' },
  SEGURO: { title: 'Pagamento do seguro', short: 'seguro', action: 'Registar custo', to: '/custos/registar' },
  IUC: { title: 'Pagamento do IUC', short: 'IUC', action: 'Registar custo', to: '/custos/registar' },
}
const MONTHS = ['jan.', 'fev.', 'mar.', 'abr.', 'mai.', 'jun.', 'jul.', 'ago.', 'set.', 'out.', 'nov.', 'dez.']
const shortDate = (iso) => `${Number(iso.slice(8, 10))} ${MONTHS[Number(iso.slice(5, 7)) - 1]}`
const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`

const load = (api, vehicles) =>
  Promise.all(vehicles.map((v) => api.listDeadlines(v.id).catch(() => []))).then((lists) =>
    Object.fromEntries(vehicles.map((v, i) => [v.id, lists[i]])))

/** Lembretes de um veículo: prazos pendentes (por data) e próxima manutenção (por km) */
function remindersOf(vehicle, deadlines = []) {
  const items = deadlines
    .filter((d) => d.status === 'PENDENTE')
    .map((d) => ({ key: d.id, kind: d.type, dueDate: d.dueDate, days: daysUntil(d.dueDate), vehicle }))
    .sort((a, b) => a.days - b.days)
  const next = vehicle.nextService
  if (next) items.push({ key: `km-${vehicle.id}`, kind: 'MANUTENCAO', targetKm: next.targetKm, kmRemaining: next.kmRemaining, vehicle })
  return items
}

const summaryOf = (items) => {
  if (!items.length) return 'Sem alertas previstos'
  const parts = items.map((i) => (i.kind === 'MANUTENCAO' ? `manutenção aos ${formatKm(i.targetKm)}` : `${DEADLINE_TEXT[i.kind].short} (${shortDate(i.dueDate)})`))
  const text = parts.length > 1 ? `${parts.slice(0, -1).join(', ')} e ${parts.at(-1)}` : parts[0]
  return `${plural(items.length, 'alerta previsto', 'alertas previstos')}: ${text}`
}

function Row({ item }) {
  const { kind, vehicle } = item
  const isKm = kind === 'MANUTENCAO'
  const late = !isKm && item.days < 0
  const name = `${vehicle.brand} ${vehicle.model} · ${formatPlateDots(vehicle.plate)}`
  const action = isKm ? { title: 'Próxima manutenção', action: 'Registar manutenção', to: '/manutencao/nova' } : DEADLINE_TEXT[kind]
  const when = late ? `Em atraso há ${plural(-item.days, 'dia', 'dias')}` : item.days === 0 ? 'Hoje' : `Em ${plural(item.days, 'dia', 'dias')}`
  return (
    <li className="rem-row">
      <span className={`rem-row__icon${isKm ? ' is-blue' : ''}`}><Icon name={isKm ? 'wrench' : 'clipboard'} size={22} /></span>
      <div className="rem-row__main">
        <div className="rem-row__tags">
          <span className="mon-badge mon-badge--muted">{isKm ? 'Manutenção' : 'Obrigação legal'}</span>
          {isKm
            ? <span className="mon-badge rem-tag--blue">Por quilometragem</span>
            : <span className={`mon-badge ${late ? 'rem-tag--late' : 'mon-badge--warn'}`}>{when}</span>}
        </div>
        <h3>{action.title}</h3>
        <p>{name}{isKm && item.kmRemaining != null ? ` · Faltam ${formatKm(item.kmRemaining)}` : ''}</p>
      </div>
      <div className="rem-row__when">
        <small>{isKm ? 'Km previstos' : 'Data limite'}</small>
        <strong className={late ? 'is-late' : ''}>{isKm ? formatKm(item.targetKm) : formatDate(item.dueDate)}</strong>
      </div>
      <Link to={action.to} className="acct-btn rem-row__btn">{action.action}</Link>
    </li>
  )
}

export default function Reminders() {
  const { vehicles, loading: garageLoading } = useOutletContext()
  const api = useApi()
  const [vehicleId, setVehicleId] = useState('')
  const [type, setType] = useState('')
  const { loading, data, error, reload } = useRemote(() => load(api, vehicles), vehicles.map((v) => v.id).join(','))

  if (garageLoading) return <p className="muted">A carregar…</p>

  const perVehicle = vehicles.map((v) => ({ vehicle: v, items: remindersOf(v, data?.[v.id]) }))
  const matches = ({ kind }) => !type || kind === type
  // "Todos": detalhe do primeiro veículo e resumo dos outros; com um veículo escolhido, só esse
  const detailed = vehicleId ? perVehicle.filter((p) => p.vehicle.id === vehicleId) : perVehicle.slice(0, 1)
  const summarized = vehicleId ? [] : perVehicle.slice(1)
  const rows = detailed.flatMap((p) => p.items).filter(matches)
  const late = perVehicle.flatMap((p) => p.items).filter((i) => i.days < 0).length

  return (
    <div className="acct mnt">
      <div className="acct__head">
        <h1>Alertas e lembretes</h1>
        <p>{late
          ? `${plural(late, 'alerta em atraso', 'alertas em atraso')}. Os alertas desaparecem quando registar a respetiva intervenção`
          : 'Saiba o que se aproxima e a que veículo pertence cada prazo'}</p>
      </div>

      {vehicles.length === 0 ? (
        <section className="acct-card mnt-empty">
          <h2>Ainda não tem veículos</h2>
          <p>Adicione um veículo para acompanhar os prazos e a manutenção</p>
          <Link to="/veiculos/novo" className="acct-btn acct-btn--primary">Adicionar veículo</Link>
        </section>
      ) : (
        <>
          <div className="mnt-filters">
            <SelectField id="rem-vehicle" label="Veículo" value={vehicleId} onChange={setVehicleId} className="mnt-filters__vehicle"
              options={[['', `Todos os veículos (${vehicles.length})`], ...vehicles.map((v) => [v.id, `${v.brand} ${v.model} · ${formatPlateDots(v.plate)}`])]} />
            <SelectField id="rem-type" label="Tipo" value={type} onChange={setType} options={TYPE_FILTERS} />
          </div>

          {error ? (
            <div className="form-error" role="alert">
              Não foi possível carregar os prazos {error.message}{' '}
              <button type="button" className="link-arrow" onClick={reload}>Tentar de novo</button>
            </div>
          ) : (
            <section className="acct-card rem-card" aria-busy={loading}>
              <h2>Próximos cuidados</h2>
              <p className="mnt-card__sub">Referência: {formatToday()}</p>
              {loading ? (
                <div className="rem-skel" role="status">{[0, 1, 2].map((i) => <span key={i} className="mon-skel" />)}</div>
              ) : rows.length === 0 ? (
                <div className="cst-empty">
                  <strong>Sem lembretes</strong>
                  <span>Não há prazos pendentes para esta seleção</span>
                </div>
              ) : (
                <ul className="rem-list">{rows.map((item) => <Row key={item.key} item={item} />)}</ul>
              )}
            </section>
          )}

          {!loading && !error && summarized.map(({ vehicle, items }) => (
            <button type="button" key={vehicle.id} className="acct-card rem-other" onClick={() => setVehicleId(vehicle.id)}>
              <Icon name={vehicle.type === 'MOTO' ? 'bike' : 'car'} size={26} />
              <span>
                <strong>{vehicle.brand} {vehicle.model} · {formatPlateDots(vehicle.plate)}</strong>
                <small>{summaryOf(items)}.</small>
              </span>
            </button>
          ))}
          <p className="rem-note">Os lembretes por quilometragem dependem da leitura mais recente do dispositivo OBD</p>
        </>
      )}
    </div>
  )
}
