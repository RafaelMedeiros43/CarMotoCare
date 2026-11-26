import { useState } from 'react'
import { Link, useNavigate, useOutletContext } from 'react-router-dom'
import Icon from '../../components/ui/Icons.jsx'
import useApi from '../../hooks/useApi.js'
import useVehicleDetail from '../../hooks/useVehicleDetail.js'
import {
  formatCoordinates, formatDateTime, formatKm, formatPlateDots, formatYearMonth,
} from '../../lib/format.js'
import { connectionOf } from '../../lib/obd.js'
import './obd.css'

// Figma: "14 Dispositivo OBD"
// Dados: GET /vehicles/{id}/obd-device (referência, nome, quando foi emparelhado), GET /obd-state (valores recebidos)
// e DELETE /vehicles/{id}/obd-device. Um parâmetro conta como "suportado" se vier preenchido na última leitura.

const time = (iso) => new Date(iso).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' })

function Params({ obd }) {
  const at = obd?.readAt
  const rows = [
    { label: 'Quilometragem', value: obd?.km != null && formatKm(obd.km), sub: at && `Valor recebido às ${time(at)}` },
    { label: 'Temperatura do óleo', value: obd?.oilTemp != null && `${obd.oilTemp.toLocaleString('pt-PT')} °C`, sub: at && `Valor recebido às ${time(at)}` },
    { label: 'Localização GPS', value: obd?.gps != null && formatCoordinates(obd.gps), sub: at && `Posição recebida às ${time(at)}` },
  ]
  return (
    <ul className="obd-params">
      {rows.map((r) => (
        <li key={r.label}>
          <div>
            <strong>{r.label}</strong>
            <span>{r.value ? r.sub : 'Sem dados deste parâmetro'}</span>
          </div>
          <b className={r.value ? undefined : 'is-empty'}>{r.value || '—'}</b>
          <span className={`mon-badge ${r.value ? 'mon-badge--ok' : 'mon-badge--muted'}`}>{r.value ? 'Suportado' : 'Não suportado'}</span>
        </li>
      ))}
    </ul>
  )
}

export default function ObdDevice() {
  const { loading: garageLoading, selected } = useOutletContext()
  const api = useApi()
  const navigate = useNavigate()
  const { loading, obdState, obdDevice } = useVehicleDetail(selected?.id)
  const [confirming, setConfirming] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  if (garageLoading || (selected && loading)) return <p className="muted">A carregar…</p>

  const back = (
    <Link to="/monitorizacao" className="acct-btn"><Icon name="arrowLeft" size={17} /> Voltar à monitorização</Link>
  )

  if (!selected) {
    return (
      <div className="acct obdp">
        <div className="obd-head"><div className="acct__head"><h1>Dispositivo OBD</h1><p>Ainda não tem veículos na garagem.</p></div>{back}</div>
        <Link to="/veiculos/novo" className="acct-btn acct-btn--primary acct-btn--self">Adicionar veículo</Link>
      </div>
    )
  }

  const state = connectionOf(obdState, obdDevice)
  const name = `${selected.brand} ${selected.model}`

  const unpair = async () => {
    setBusy(true)
    setError('')
    try {
      await api.unpairObdDevice(selected.id)
      navigate('/monitorizacao')
    } catch (e) {
      setError(e.message)
      setBusy(false)
    }
  }

  return (
    <div className="acct obdp">
      <div className="obd-head">
        <div className="acct__head">
          <h1>Dispositivo OBD</h1>
          <p>Ligação e disponibilidade dos dados do {name}.</p>
        </div>
        {back}
      </div>

      {state === 'unpaired' ? (
        <section className="acct-card obd-empty">
          <span className="obd-empty__icon"><Icon name="radio" size={28} /></span>
          <h2>Dispositivo OBD não emparelhado</h2>
          <p>Emparelhe um dispositivo OBD ao {name} para receber a quilometragem, a temperatura do óleo e a localização.</p>
          <Link to="/dispositivo/emparelhar" className="acct-btn acct-btn--primary">Emparelhar dispositivo OBD</Link>
        </section>
      ) : (
        <>
          <div className="obd-grid">
            <section className="acct-card obd-link">
              <h2>Ligação ao veículo</h2>
              <div className="obd-link__visual" aria-hidden="true">
                <Icon name={selected.type === 'MOTO' ? 'bike' : 'car'} size={40} />
                <Icon name="link" size={22} />
                <Icon name="radio" size={34} />
              </div>
              <span className={`mon-badge ${state === 'stale' ? 'mon-badge--warn' : 'mon-badge--ok'} obd-link__badge`}>
                {state === 'stale' ? 'Sem leituras recentes' : 'Dispositivo emparelhado'}
              </span>
              <div className="obd-link__id">
                <h3>{name}</h3>
                <p>{formatYearMonth(selected.firstRegistration)} · {formatPlateDots(selected.plate)}</p>
                {obdDevice && <p>{obdDevice.deviceId}{obdDevice.name ? ` · ${obdDevice.name}` : ''}</p>}
              </div>
              <hr className="acct-sep" />
              <div className="obd-link__last">
                <span>Últimos dados recebidos</span>
                <strong>{obdState?.readAt ? formatDateTime(obdState.readAt) : 'Ainda sem leituras'}</strong>
                <span>Dados atualizados sempre que entra na aplicação.</span>
              </div>

              {error && <div className="form-error" role="alert">{error}</div>}
              {confirming ? (
                <div className="obd-link__confirm">
                  <p>Deixa de receber dados deste dispositivo. Pode voltar a emparelhá-lo mais tarde.</p>
                  <button type="button" className="acct-btn obd-danger" onClick={unpair} disabled={busy}>{busy ? 'A desemparelhar…' : 'Confirmar desemparelhamento'}</button>
                  <button type="button" className="acct-btn" onClick={() => setConfirming(false)} disabled={busy}>Cancelar</button>
                </div>
              ) : (
                <button type="button" className="acct-btn acct-btn--block obd-danger" onClick={() => setConfirming(true)}>Desemparelhar dispositivo</button>
              )}
            </section>

            <section className="acct-card obd-params-card">
              <div className="obd-params-card__intro">
                <h2>Parâmetros deste conjunto</h2>
                <p>A disponibilidade depende do veículo e do dispositivo utilizado.</p>
              </div>
              <Params obd={obdState} />
            </section>
          </div>

          <aside className="mon-info obd-note" role="note">
            <Icon name="info" size={19} />
            <div>
              <strong>Se a quilometragem não estiver disponível</strong>
              <p>A aplicação mantém visível o último valor recebido e a respetiva data de atualização.</p>
            </div>
          </aside>
        </>
      )}
    </div>
  )
}
