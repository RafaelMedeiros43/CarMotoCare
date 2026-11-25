import { useState } from 'react'
import { Link, useNavigate, useOutletContext, useParams } from 'react-router-dom'
import Icon from '../components/Icons.jsx'
import RemoveVehicleDialog from '../components/RemoveVehicleDialog.jsx'
import useApi from '../hooks/useApi.js'
import useVehicleDetail from '../hooks/useVehicleDetail.js'
import {
  currentYear, daysUntil, formatDate, formatDateTime, formatEuro, formatKm, formatPlateDots, formatYearMonth,
} from '../lib/format.js'
import '../styles/vehicle-detail.css'

// Figma: "10 Consultar veículo" / "10b" (Yamaha MT-07)
// Dados: o veículo vem de GET /vehicles (já carregado no AppShell); manutenções, despesas e último estado OBD
// vêm de GET /vehicles/{id}/maintenances, /expenses e /obd-state. Remover: DELETE /vehicles/{id}.

const ALERT_LABEL = { INSPECAO: 'Inspeção', IUC: 'IUC', SEGURO: 'Seguro', MANUTENCAO: 'Manutenção' }

const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`

function inDays(n) {
  if (n === 0) return 'hoje'
  if (n === 1) return 'amanhã'
  if (n > 1) return `em ${n} dias`
  return `em atraso há ${plural(-n, 'dia', 'dias')}`
}

/**
 * Texto do atalho "Lembretes": o prazo mais próximo entre os alertas abertos (GET /alerts) e os prazos
 * pendentes (GET /vehicles/{id}/deadlines); sem datas, o número de alertas abertos.
 */
function remindersText(alerts, deadlines, openAlerts) {
  const dated = [
    ...alerts.filter((a) => a.dueDate).map((a) => ({ type: a.type, dueDate: a.dueDate, label: a.title })),
    ...deadlines.filter((d) => d.status === 'PENDENTE').map((d) => ({ type: d.type, dueDate: d.dueDate })),
  ].sort((a, b) => a.dueDate.localeCompare(b.dueDate))
  if (dated.length) return `${ALERT_LABEL[dated[0].type] ?? dated[0].label} ${inDays(daysUntil(dated[0].dueDate))}`
  if (openAlerts > 0) return plural(openAlerts, 'alerta aberto', 'alertas abertos')
  return null
}

function Detail({ vehicle, alerts, selectVehicle, reload }) {
  const api = useApi()
  const navigate = useNavigate()
  const { maintenances, expenses, obdState, deadlines } = useVehicleDetail(vehicle.id)
  const [removing, setRemoving] = useState(false)

  const isMoto = vehicle.type === 'MOTO'
  const kind = isMoto ? 'Motociclo' : 'Automóvel'
  const next = vehicle.nextService

  const year = String(currentYear())
  const yearCosts = expenses.filter((e) => e.category === 'MANUTENCAO' && e.date.startsWith(year))
  const yearTotal = yearCosts.reduce((sum, e) => sum + Number(e.amount), 0) // Number(): o valor pode chegar como texto
  const allTotal = expenses.reduce((sum, e) => sum + Number(e.amount), 0)
  const reminders = remindersText(
    alerts.filter((a) => a.vehicleId === vehicle.id && a.status === 'ABERTO'),
    deadlines,
    vehicle.openAlerts,
  )

  const confirmRemove = async () => {
    await api.deleteVehicle(vehicle.id)
    await reload()
    navigate('/veiculos')
  }

  const care = [
    { to: '/manutencao', icon: 'wrench', title: 'Manutenção', text: plural(maintenances.length, 'registo', 'registos') + ' no histórico' },
    { to: '/monitorizacao', icon: 'gauge', title: 'Quilometragem', text: 'Consultar' },
    { to: '/custos', icon: 'wallet', title: 'Custos', text: `${formatEuro(allTotal)} em todos os registos` },
    { to: '/lembretes', icon: 'calendarDays', title: 'Lembretes', text: reminders },
  ]

  return (
    <div className="acct vd">
      <div className="acct__head">
        <h1>{isMoto ? 'A sua' : 'O seu'} {vehicle.model}, em detalhe</h1>
        <p>Dados essenciais e acesso ao cuidado deste veículo.</p>
      </div>

      <section className="acct-card vd-main">
        <div className="vd-id">
          <span className="vd-id__tile"><Icon name={isMoto ? 'bike' : 'car'} size={42} /></span>
          <div className="vd-id__text">
            <span className="acct-tag">{kind}</span>
            <h2>{vehicle.brand} {vehicle.model}</h2>
            <p>{formatYearMonth(vehicle.firstRegistration)} · {formatPlateDots(vehicle.plate)}</p>
          </div>
          <div className="vd-id__actions">
            <Link to={`/veiculos/${vehicle.id}/editar`} className="acct-btn" onClick={() => selectVehicle(vehicle.id)}>
              <Icon name="pencil" size={17} />
              Editar veículo
            </Link>
            <button type="button" className="vd-remove" onClick={() => setRemoving(true)}>Remover</button>
          </div>
        </div>
        <hr className="acct-sep" />
        <dl className="vd-facts">
          <div><dt>Marca</dt><dd>{vehicle.brand}</dd></div>
          <div><dt>Modelo</dt><dd>{vehicle.model}</dd></div>
          <div><dt>1.ª matrícula</dt><dd>{formatYearMonth(vehicle.firstRegistration)}</dd></div>
          <div><dt>Matrícula</dt><dd>{formatPlateDots(vehicle.plate)}</dd></div>
        </dl>
      </section>

      <div className="vd-stats">
        <div className="vd-stat vd-stat--accent">
          <p>Quilometragem atual</p>
          <strong>{formatKm(vehicle.km)}</strong>
          <small>{obdState?.readAt ? `Última atualização: ${formatDateTime(obdState.readAt)}` : 'Ainda sem leituras do dispositivo OBD'}</small>
        </div>
        <div className="vd-stat">
          <p>Próxima manutenção</p>
          <strong>{next?.targetKm != null ? formatKm(next.targetKm) : next?.dueDate ? formatDate(next.dueDate) : '—'}</strong>
          <small>
            {!next && 'Sem plano de manutenção ativo'}
            {next && next.kmRemaining != null && (next.kmRemaining > 0
              ? `Faltam ${formatKm(next.kmRemaining)} para a próxima manutenção`
              : 'Manutenção em atraso')}
            {next && next.kmRemaining == null && next.dueDate && `Prevista para ${formatDate(next.dueDate)}`}
          </small>
        </div>
        <div className="vd-stat">
          <p>Manutenção em {year}</p>
          <strong>{formatEuro(yearTotal)}</strong>
          <small>
            {yearCosts.length === 0
              ? 'Nenhuma intervenção registada neste ano'
              : `${plural(yearCosts.length, 'intervenção registada', 'intervenções registadas')} neste ano`}
          </small>
        </div>
      </div>

      <section className="acct-card">
        <h2>Cuidado do veículo</h2>
        <div className="vd-care">
          {care.map((c) => (
            <Link key={c.title} to={c.to} className="vd-care__tile" onClick={() => selectVehicle(vehicle.id)}>
              <span className="vd-care__top"><Icon name={c.icon} size={23} /><Icon name="arrowUpRight" size={17} /></span>
              <strong>{c.title}</strong>
              {c.text && <span>{c.text}</span>}
            </Link>
          ))}
        </div>
      </section>

      <RemoveVehicleDialog vehicle={removing ? vehicle : null} onClose={() => setRemoving(false)} onConfirm={confirmRemove} />
    </div>
  )
}

export default function VehicleDetail() {
  const { vehicleId } = useParams()
  const { vehicles, alerts, loading, error, selectVehicle, reload } = useOutletContext()
  const vehicle = vehicles.find((v) => v.id === vehicleId)

  if (loading) return <p className="muted">A carregar…</p>
  if (!vehicle) {
    return (
      <div className="acct vd">
        <div className="acct__head">
          <h1>Veículo não encontrado</h1>
          <p>{error || 'Este veículo não existe ou já foi removido.'}</p>
        </div>
        <Link to="/veiculos" className="acct-btn acct-btn--self">Voltar aos veículos</Link>
      </div>
    )
  }
  return <Detail key={vehicle.id} vehicle={vehicle} alerts={alerts} selectVehicle={selectVehicle} reload={reload} />
}
