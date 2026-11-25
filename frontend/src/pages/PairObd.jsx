import { useState } from 'react'
import { Link, useNavigate, useOutletContext } from 'react-router-dom'
import Icon from '../components/Icons.jsx'
import Field from '../components/Field.jsx'
import { useAuth } from '../context/AuthContext.jsx'
import useApi from '../hooks/useApi.js'
import useObdDevices from '../hooks/useObdDevices.js'
import { formatKm, formatPlateDots, formatYearMonth } from '../lib/format.js'
import '../styles/obd.css'

// Figma: "15 Emparelhar dispositivo OBD" (já há outro veículo com dispositivo) e "15b" (primeiro dispositivo)
// Dados: GET /vehicles/{id}/obd-device em cada veículo + PUT /vehicles/{id}/obd-device { deviceId, name }.
// Os veículos que já têm dispositivo aparecem bloqueados; 409 = referência já associada a outro veículo.

const STEPS = [
  ['Selecionar veículo', 'Escolha na sua garagem'],
  ['Identificar dispositivo', 'Introduza os dados do equipamento'],
  ['Confirmar associação', 'Reveja antes de emparelhar'],
]

const kindOf = (v) => (v.type === 'MOTO' ? 'motociclo' : 'automóvel')
const defaultName = (v) => `OBD ${v.type === 'MOTO' ? 'da' : 'do'} ${v.model}`

function VehicleOption({ vehicle, hasDevice, selected, onSelect }) {
  const icon = vehicle.type === 'MOTO' ? 'bike' : 'car'
  return (
    <button
      type="button"
      className={`obd-option${selected ? ' is-selected' : ''}`}
      disabled={hasDevice}
      aria-pressed={selected}
      onClick={() => onSelect(vehicle.id)}
    >
      <span className="obd-option__top">
        <Icon name={icon} size={22} />
        {hasDevice && <Icon name="lock" size={18} />}
        {selected && <Icon name="checkCircle" size={22} />}
      </span>
      <strong>{vehicle.brand} {vehicle.model}</strong>
      <span>{formatYearMonth(vehicle.firstRegistration)} · {formatPlateDots(vehicle.plate)}</span>
      <b>{formatKm(vehicle.km)}</b>
      {hasDevice
        ? <span className="mon-badge mon-badge--ok">OBD já associado</span>
        : <em>{selected ? 'Selecionado para nova associação' : 'Sem dispositivo associado'}</em>}
    </button>
  )
}

function Form({ vehicles, devices, selected: contextSelected, selectVehicle }) {
  const api = useApi()
  const navigate = useNavigate()
  const { user } = useAuth()

  const free = vehicles.filter((v) => !devices[v.id])
  const paired = vehicles.filter((v) => devices[v.id])
  const first = paired.length === 0 // 15b: ainda nenhum veículo tem dispositivo

  const [picked, setPicked] = useState(null)
  const [deviceId, setDeviceId] = useState('')
  const [nameEdited, setNameEdited] = useState(null)
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)

  const vehicle = free.find((v) => v.id === picked)
    ?? free.find((v) => v.id === contextSelected?.id)
    ?? free[0]

  if (!vehicle) {
    return (
      <section className="acct-card obd-empty">
        <span className="obd-empty__icon"><Icon name="radio" size={28} /></span>
        <h2>Todos os veículos já têm dispositivo</h2>
        <p>Para associar outro dispositivo, desemparelhe primeiro o atual na página do dispositivo OBD.</p>
        <Link to="/monitorizacao" className="acct-btn acct-btn--primary">Ir para a monitorização</Link>
      </section>
    )
  }

  const kind = kindOf(vehicle)
  const name = nameEdited ?? defaultName(vehicle)
  const ref = deviceId.trim()
  const vehicleName = `${vehicle.brand} ${vehicle.model}`

  const submit = async (e) => {
    e.preventDefault()
    setFormError('')
    if (!ref) {
      setErrors({ deviceId: 'Indique a referência do dispositivo' })
      document.getElementById('deviceId')?.focus()
      return
    }
    setErrors({})
    setSaving(true)
    try {
      await api.pairObdDevice(vehicle.id, { deviceId: ref, ...(name.trim() && { name: name.trim() }) })
      selectVehicle(vehicle.id)
      navigate('/monitorizacao')
    } catch (err) {
      const byField = {}
      for (const f of err.fieldErrors ?? []) if (['deviceId', 'name'].includes(f.field)) byField[f.field] = f.message
      setErrors(byField)
      setFormError(Object.keys(byField).length ? 'Corrija os campos assinalados.' : err.message)
      setSaving(false)
    }
  }

  return (
    <form className="obd-pair" onSubmit={submit} noValidate>
      <div className="obd-pair__main">
        <section className="acct-card obd-step">
          <h2><span>1</span>Selecionar veículo</h2>
          <p className="obd-step__lead">
            {first ? `Selecione o ${kind} da sua garagem que pretende ligar à sua conta.` : 'Escolha o veículo ao qual pretende associar este dispositivo.'}
          </p>
          <div className="obd-options">
            {vehicles.map((v) => (
              <VehicleOption key={v.id} vehicle={v} hasDevice={!!devices[v.id]} selected={v.id === vehicle.id} onSelect={setPicked} />
            ))}
          </div>
          <p className="obd-step__note">
            {first
              ? 'Cada dispositivo fica associado a um veículo da sua conta. Confirme a seleção antes de continuar.'
              : `${paired.map((v) => `${v.brand} ${v.model}`).join(', ')} ${paired.length === 1 ? 'mantém' : 'mantêm'} o dispositivo já associado. Esta é uma nova associação para ${vehicleName}.`}
          </p>
        </section>

        <section className="acct-card obd-step">
          <h2><span>2</span>Identificar dispositivo OBD</h2>
          <p className="obd-step__lead">Consulte a identificação no equipamento ou nas respetivas instruções.</p>
          <div className="obd-fields">
            <Field name="deviceId" label="Referência do dispositivo" error={errors.deviceId}>
              {(d) => (
                <input id="deviceId" name="deviceId" value={deviceId} onChange={(e) => { setDeviceId(e.target.value); setErrors({}) }}
                  placeholder="OBD-7F3A-0002" autoComplete="off" spellCheck={false} maxLength={64} disabled={saving}
                  aria-invalid={errors.deviceId ? 'true' : undefined} aria-describedby={d} />
              )}
            </Field>
            <Field name="name" label="Nome na aplicação" error={errors.name}>
              {(d) => (
                <input id="name" name="name" value={name} onChange={(e) => setNameEdited(e.target.value)}
                  autoComplete="off" maxLength={60} disabled={saving} aria-invalid={errors.name ? 'true' : undefined} aria-describedby={d} />
              )}
            </Field>
          </div>
          <p className="addv-hint">A referência encontra-se na etiqueta ou nas instruções do dispositivo.</p>
          <div className="obd-before">
            <Icon name="plug" size={19} />
            <div>
              <strong>Antes de emparelhar</strong>
              <p>
                {first
                  ? `Com o ${kind} estacionado, ligue o dispositivo à porta OBD e siga as instruções do fabricante. Confirme a compatibilidade com o seu ${kind}.`
                  : 'Ligue o dispositivo à porta OBD do veículo e deixe-o disponível conforme as instruções do próprio equipamento. Confirme também a compatibilidade com este veículo.'}
              </p>
            </div>
          </div>
        </section>
      </div>

      <div className="obd-pair__side">
        <section className="acct-card obd-confirm">
          <h2><span>3</span>Confirmar associação</h2>
          <span className="mon-badge mon-badge--warn obd-confirm__badge">Por emparelhar</span>

          <div className="obd-confirm__row">
            <span className="obd-confirm__tile"><Icon name={vehicle.type === 'MOTO' ? 'bike' : 'car'} size={24} /></span>
            <div>
              <small>Veículo selecionado</small>
              <strong>{vehicleName}</strong>
              <span>{formatYearMonth(vehicle.firstRegistration)} · {formatPlateDots(vehicle.plate)}</span>
              <span>{formatKm(vehicle.km)}</span>
            </div>
          </div>
          <hr className="acct-sep" />
          <div className="obd-confirm__row">
            <span className="obd-confirm__tile"><Icon name="cpu" size={24} /></span>
            <div>
              <small>Dispositivo a associar</small>
              <strong>{name || 'Dispositivo OBD'}</strong>
              <span>{ref || 'OBD-7F3A-0002'}</span>
              <span>{first ? `Conta: ${user?.name ?? 'Conta pessoal'}` : 'Referência do dispositivo'}</span>
            </div>
          </div>
          <hr className="acct-sep" />

          <p className="obd-confirm__text">
            {first
              ? `Ao confirmar, o OBD será associado ao veículo ${vehicleName} e à conta de ${user?.name ?? 'si'}. Ainda não foram recebidos dados.`
              : 'Está a preparar uma nova associação. Ainda não existe ligação nem foram recebidos dados deste dispositivo.'}
          </p>
          {formError && <div className="form-error" role="alert">{formError}</div>}
          <button type="submit" className="acct-btn acct-btn--primary acct-btn--block" disabled={saving}>
            <Icon name="link" size={17} />
            {saving ? 'A emparelhar…' : 'Emparelhar dispositivo'}
          </button>
          <Link to="/monitorizacao" className="acct-btn obd-confirm__cancel" aria-disabled={saving}>Cancelar</Link>
        </section>

        <aside className="mon-info obd-privacy" role="note">
          <Icon name="info" size={19} />
          <div>
            <strong>{first ? 'Dados e privacidade' : 'O que poderá acompanhar'}</strong>
            <p>
              {first
                ? 'Após o emparelhamento, os dados disponíveis dependem do veículo e do OBD. Cada leitura apresenta a origem e a última atualização'
                : 'Os parâmetros dependem do veículo e do dispositivo. Os dados são atualizados sempre que entra na aplicação, com estado da ligação e data/hora da última atualização'}
            </p>
            <p>
              {first
                ? 'Pode gerir ou remover a associação nas definições do dispositivo. A localização é recebida através do GPS do dispositivo'
                : 'A quilometragem é atualizada automaticamente. A localização é recebida através do GPS do dispositivo'}
            </p>
          </div>
        </aside>
      </div>
    </form>
  )
}

export default function PairObd() {
  const { vehicles, loading: garageLoading, selected, selectVehicle } = useOutletContext()
  const { loading, devices } = useObdDevices(vehicles)

  if (garageLoading || loading) return <p className="muted">A carregar…</p>

  const anyDevice = vehicles.some((v) => devices[v.id])
  const kind = selected ? kindOf(selected) : 'veículo'
  const back = anyDevice ? { to: '/monitorizacao', label: 'Voltar à monitorização' } : { to: '/painel', label: 'Voltar ao painel' }

  return (
    <div className="acct obdp">
      <div className="obd-head">
        <div className="acct__head">
          <h1>Emparelhar dispositivo OBD</h1>
          <p>{anyDevice ? 'Associe um dispositivo a um veículo da sua conta, em três passos.' : `Associe o OBD do seu ${kind} à sua conta e comece a acompanhar os dados do veículo`}</p>
        </div>
        <Link to={back.to} className="acct-btn"><Icon name="arrowLeft" size={17} /> {back.label}</Link>
      </div>

      <ol className="obd-steps">
        {STEPS.map(([title, text], i) => (
          <li key={title}><span>{i + 1}</span><div><strong>{title}</strong><small>{text}</small></div></li>
        ))}
      </ol>

      {vehicles.length === 0 ? (
        <section className="acct-card obd-empty">
          <h2>Ainda não tem veículos</h2>
          <p>Adicione um veículo para o poder associar a um dispositivo OBD</p>
          <Link to="/veiculos/novo" className="acct-btn acct-btn--primary">Adicionar veículo</Link>
        </section>
      ) : (
        <Form vehicles={vehicles} devices={devices} selected={selected} selectVehicle={selectVehicle} />
      )}
    </div>
  )
}
