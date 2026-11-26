import { useMemo, useState } from 'react'
import { Link, useOutletContext } from 'react-router-dom'
import Icon from '../../components/ui/Icons.jsx'
import useVehicleDetail from '../../hooks/useVehicleDetail.js'
import {
  currentYear, daysUntil, formatDate, formatEuro, formatKm, formatMonthYearLong, 
  formatPlate, formatYearMonth, minutesSince, timeAgo,} from '../../lib/format.js'

// Figma: "04 Painel — Visão geral" (camada "Entrada, conta e painel")

const MAINTENANCE_LABEL = {
  REVISAO_PERIODICA: 'Revisão periódica',
  MUDANCA_OLEO: 'Mudança de óleo',
  SUBSTITUICAO_PNEUS: 'Substituição de pneus',
  REPARACAO: 'Reparação',
  AVARIA: 'Avaria',
  OUTRO: 'Outra intervenção',
}
const MAINTENANCE_ICON = { MUDANCA_OLEO: 'droplets', SUBSTITUICAO_PNEUS: 'circleDot' }

const EXPENSE_LABEL = { MANUTENCAO: 'Manutenção', INSPECAO: 'Inspeção', SEGURO: 'Seguro', IUC: 'IUC' }
const COST_COLORS = ['var(--color-primary)', '#7ea4f5', '#c1d3fa', '#e2e8f1']

const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`

function VehicleCard({ vehicle, selected, onSelect }) {
  const alerts = vehicle.openAlerts
  return (
    <button type="button" className={`vcard${selected ? ' is-selected' : ''}`} onClick={() => onSelect(vehicle.id)} aria-pressed={selected}>
      <span className="vcard__head">
        <span className="vcard__id">
          <strong>{vehicle.brand} {vehicle.model}</strong>
          <span>{formatPlate(vehicle.plate)}</span>
        </span>
        <span className={`pill ${alerts > 0 ? 'pill--warn' : 'pill--ok'}`}>
          ●&nbsp; {alerts > 0 ? plural(alerts, 'alerta', 'alertas') : 'Sem alertas'}
        </span>
      </span>
      <span className="vcard__since">
        <Icon name="calendarDays" size={14} />
        {formatYearMonth(vehicle.firstRegistration)}
      </span>
      <span className="vcard__stats">
        <span>
          <small>Quilometragem</small>
          <strong>{formatKm(vehicle.km)}</strong>
        </span>
        <span>
          <small>Próxima manutenção</small>
          <strong>{vehicle.nextService ? formatKm(vehicle.nextService.targetKm) : '—'}</strong>
        </span>
      </span>
    </button>
  )
}

function MonitoringCard({ vehicle, obd, alert, loading }) {
  const hasDevice = !!obd?.deviceId || !!obd?.readAt
  const hasReading = !!obd?.readAt
  const minutes = hasReading ? minutesSince(obd.readAt) : null
  const fresh = minutes !== null && minutes <= 60

  // Mostra até dois parâmetros que o dispositivo/veículo suporta
  const params = []
  if (obd?.km != null) params.push({ label: 'Quilometragem', value: formatKm(obd.km), note: 'Disponível' })
  if (obd?.gps) params.push({ label: 'Localização GPS', value: 'Ativa', note: `${obd.gps.latitude.toFixed(3)}, ${obd.gps.longitude.toFixed(3)}` })
  if (obd?.oilTemp != null) params.push({ label: 'Temperatura do óleo', value: `${obd.oilTemp} °C`, note: 'Disponível' })

  const days = alert?.dueDate ? daysUntil(alert.dueDate) : null
  let when = ''
  if (days !== null) when = days < 0 ? 'Em atraso' : days === 0 ? 'Hoje' : `Em ${plural(days, 'dia', 'dias')}`
  else if (alert?.kmRemaining != null) when = alert.kmRemaining < 0 ? 'Em atraso' : `Faltam ${formatKm(alert.kmRemaining)}`

  return (
    <section className="card card--side">
      <div className="card__title-row">
        <h2 className="card__title card__title--sm">Monitorização e lembretes</h2>
        <Icon name="gauge" size={17} />
      </div>

      {loading ? (
        <p className="muted">A carregar…</p>
      ) : (
        <>
          <div className="obd-status">
            <span className={`obd-status__icon${hasDevice ? '' : ' is-off'}`}><Icon name="shieldCheck" size={17} /></span>
            <span className="obd-status__text">
              <strong>{hasDevice ? 'Dispositivo OBD emparelhado' : 'Sem dispositivo OBD'}</strong>
              <small>
                {!hasDevice && 'Acompanhe manutenção, custos e prazos por data'}
                {hasDevice && hasReading && `Última atualização: ${timeAgo(obd.readAt)}`}
                {hasDevice && !hasReading && 'Sem leituras recentes'}
              </small>
            </span>
            {hasDevice && hasReading && (
              <span className={`pill ${fresh ? 'pill--info' : 'pill--warn'}`}>{fresh ? 'Atualizado' : 'Desatualizado'}</span>
            )}
          </div>

          {params.length > 0 && (
            <div className="obd-params">
              {params.slice(0, 2).map((p) => (
                <div className="obd-param" key={p.label}>
                  <small>{p.label}</small>
                  <strong>{p.value}</strong>
                  <span>{p.note}</span>
                </div>
              ))}
            </div>
          )}

          {alert ? (
            <Link to="/lembretes" className="reminder">
              <span className="reminder__icon"><Icon name="clipboard" size={17} /></span>
              <span className="reminder__text">
                <strong>{alert.title}</strong>
                <small>{alert.dueDate ? formatDate(alert.dueDate) : formatKm(alert.targetKm)}</small>
              </span>
              <span className="reminder__when">{when}</span>
            </Link>
          ) : (
            <p className="muted">Sem lembretes em aberto para {vehicle.brand} {vehicle.model}</p>
          )}
        </>
      )}
    </section>
  )
}

function MaintenanceCard({ vehicle, maintenances, loading }) {
  const rows = useMemo(
    () => [...maintenances].sort((a, b) => b.date.localeCompare(a.date)).slice(0, 3),
    [maintenances],
  )
  const oldest = useMemo(
    () => maintenances.reduce((min, m) => (min === null || m.date < min ? m.date : min), null),
    [maintenances],
  )

  return (
    <section className="card card--table">
      <div className="card__head">
        <div>
          <h2 className="card__title">Manutenções</h2>
          <p className="card__sub">Histórico e próximas intervenções</p>
        </div>
        <Link to="/manutencao" className="btn-sm">
          <Icon name="plus" size={16} />
          Adicionar
        </Link>
      </div>

      <div className="mtable">
        <div className="mtable__cols">
          <span>INTERVENÇÃO</span>
          <span className="w-date">DATA</span>
          <span className="w-km">QUILOMETRAGEM</span>
          <span className="w-cost">CUSTO</span>
        </div>

        {loading && <p className="mtable__empty">A carregar…</p>}
        {!loading && rows.length === 0 && <p className="mtable__empty">Ainda não há manutenções registadas</p>}
        {rows.map((m) => (
          <div className="mtable__row" key={m.id}>
            <span className="mtable__service">
              <span className="mtable__icon"><Icon name={MAINTENANCE_ICON[m.type] ?? 'wrench'} size={16} /></span>
              <span className="mtable__text">
                <strong>{MAINTENANCE_LABEL[m.type] ?? 'Intervenção'}</strong>
                <small>{vehicle.brand} {vehicle.model}{m.notes ? ` · ${m.notes}` : ''}</small>
              </span>
            </span>
            <span className="w-date muted">{formatDate(m.date)}</span>
            <span className="w-km muted">{formatKm(m.km)}</span>
            <strong className="w-cost">{formatEuro(m.cost)}</strong>
          </div>
        ))}
      </div>

      <div className="card__foot">
        <span>
          {oldest ? `${plural(maintenances.length, 'intervenção', 'intervenções')} desde ${formatMonthYearLong(oldest)}` : ' '}
        </span>
        <Link to="/manutencao" className="link-arrow"> Ver histórico completo <Icon name="arrowRight" size={14} /></Link>
      </div>
    </section>
  )
}

function CostsCard({ vehicle, expenses, loading }) {
  const thisYear = currentYear()
  const years = useMemo(() => {
    const set = new Set([thisYear, ...expenses.map((e) => Number(e.date.slice(0, 4)))])
    return [...set].sort((a, b) => b - a)
  }, [expenses, thisYear])
  const [year, setYear] = useState(thisYear)

  const { total, categories } = useMemo(() => {
    const byCategory = {}
    for (const e of expenses) {
      if (Number(e.date.slice(0, 4)) !== year) continue
      byCategory[e.category] = (byCategory[e.category] ?? 0) + e.amount
    }
    const list = Object.entries(byCategory)
      .map(([category, amount]) => ({ category, amount }))
      .sort((a, b) => b.amount - a.amount)
    return { total: list.reduce((sum, c) => sum + c.amount, 0), categories: list }
  }, [expenses, year])

  return (
    <section className="card card--side">
      <div className="card__title-row">
        <h2 className="card__title">Custos</h2>
        <label className="year">
          <select value={year} onChange={(e) => setYear(Number(e.target.value))} aria-label="Ano">
            {years.map((y) => <option key={y} value={y}>{y}</option>)}
          </select>
          <Icon name="chevronDown" size={12} />
        </label>
      </div>

      {loading ? (
        <p className="muted">A carregar…</p>
      ) : (
        <>
          <div className="costs-total">
            <p><strong>{formatEuro(total)}</strong><small>este ano</small></p>
            <small>Custos do {vehicle.brand} {vehicle.model} em {year}</small>
          </div>

          {categories.length > 0 && (
            <>
              <div className="costs-bar" aria-hidden="true">
                {categories.map((c, i) => (
                  <i key={c.category} style={{ flexGrow: c.amount, background: COST_COLORS[i] ?? COST_COLORS[3] }} />
                ))}
              </div>
              <ul className="costs-list">
                {categories.map((c, i) => (
                  <li key={c.category}>
                    <i style={{ background: COST_COLORS[i] ?? COST_COLORS[3] }} />
                    <span>{EXPENSE_LABEL[c.category] ?? c.category}</span>
                    <b>{formatEuro(c.amount)}</b>
                  </li>
                ))}
              </ul>
            </>
          )}
          {categories.length === 0 && <p className="muted">Sem custos registados em {year}</p>}
        </>
      )}

      <Link to="/custos" className="costs-add">  <Icon name="plus" size={14} /> Registar custo </Link>
    </section>
  )
}

export default function Dashboard() {
  const { loading, error, vehicles, alerts, selected, selectVehicle } = useOutletContext()
  const detail = useVehicleDetail(selected?.id)

  const selectedAlert = useMemo(() => {
    if (!selected) return null
    return alerts
      .filter((a) => a.vehicleId === selected.id && a.status === 'ABERTO')
      .sort((a, b) => (a.dueDate ?? '9999').localeCompare(b.dueDate ?? '9999'))[0] ?? null
  }, [alerts, selected])

  return (
    <div className="dash">
      <div className="dash__head">
        <div>
          <h1>Os seus veículos, sob controlo</h1>
          <p>Acompanhe quilometragem, manutenções, custos e lembretes para automóveis e motociclos</p>
        </div>
        <Link to="/veiculos/novo" className="btn btn--primary btn--sm"><Icon name="plus" size={16} />Adicionar veículo </Link>
      </div>

      {error && <div className="form-error" role="alert">{error}</div>}
      {loading && !error && <p className="muted">A carregar…</p>}

      {!loading && !error && vehicles.length === 0 && (
        <section className="card card--empty">
          <h2 className="card__title">Ainda não tem veículos</h2>
          <p className="card__sub">Adicione o seu primeiro automóvel ou motociclo para começar a acompanhar manutenção, custos e prazos</p>
          <Link to="/veiculos/novo" className="btn btn--primary btn--sm">  <Icon name="plus" size={16} /> Adicionar veículo </Link>
        </section>
      )}

      {selected && (
        <>
          <div className="dash__grid">
            <section className="card card--vehicles">
              <div className="card__title-row">
                <p className="eyebrow-sm">OS SEUS VEÍCULOS</p>
                <Icon name="ellipsis" size={20} />
              </div>

              <div className="vcards">
                {vehicles.map((v) => (
                  <VehicleCard key={v.id} vehicle={v} selected={v.id === selected.id} onSelect={selectVehicle} />
                ))}
              </div>

              <div className="quick">
                <Link to="/veiculos/novo"><Icon name="plus" size={14} />Adicionar veículo</Link>
                <Link to="/monitorizacao"><Icon name="gauge" size={14} />Monitorização</Link>
                <Link to="/manutencao"><Icon name="wrench" size={14} />Manutenções</Link>
                <Link to="/custos"><Icon name="wallet" size={14} />Custos</Link>
              </div>
            </section>

            <MonitoringCard vehicle={selected} obd={detail.obdState} alert={selectedAlert} loading={detail.loading} />
          </div>

          <div className="dash__grid">
            <MaintenanceCard vehicle={selected} maintenances={detail.maintenances} loading={detail.loading} />
            <CostsCard key={selected.id} vehicle={selected} expenses={detail.expenses} loading={detail.loading} />
          </div>
        </>
      )}
    </div>
  )
}
