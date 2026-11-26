import { Link, useOutletContext } from 'react-router-dom'
import Icon from '../../components/ui/Icons.jsx'
import VehicleSelect from '../../components/ui/VehicleSelect.jsx'
import useVehicleDetail from '../../hooks/useVehicleDetail.js'
import { formatCoordinates, formatDateTime, formatKm } from '../../lib/format.js'
import { connectionOf, vehicleLabel } from '../../lib/obd.js'
import '../../styles/monitoring.css'

// Figma: "13 Monitorização" / "13b" (Yamaha, sem dispositivo) / "Sem leituras recentes" / "A carregar"
// Dados: GET /vehicles/{id}/obd-state (km, oilTemp e gps anuláveis; sem leituras vem tudo nulo).
// A localização chega só como latitude/longitude: mostra-se em coordenadas (o nome do local não vem da API).

const BANNER = {
  unpaired: { title: 'Dispositivo OBD não emparelhado', text: () => 'Sem dados recebidos · Nenhuma última receção registada', action: 'Emparelhar dispositivo OBD', to: '/dispositivo/emparelhar' },
  waiting: { title: 'Dispositivo OBD emparelhado', text: () => 'Ainda sem leituras recebidas', action: 'Ver dispositivo', to: '/dispositivo' },
  fresh: { title: 'Dispositivo OBD emparelhado', text: (obd) => `Atualizado ao entrar · Última leitura: ${formatDateTime(obd.readAt)}`, action: 'Ver dispositivo', to: '/dispositivo' },
  stale: { title: 'Sem leituras recentes', text: (obd) => `Última leitura: ${formatDateTime(obd.readAt)}`, action: 'Ver dispositivo', to: '/dispositivo' },
}

const INFO = {
  unpaired: { title: 'Disponibilidade e atualização dos dados', text: 'Os dados são atualizados sempre que entra na aplicação, depois de emparelhar um dispositivo OBD. Até lá, a quilometragem mantém o valor inicial.' },
  waiting: { title: 'À espera da primeira leitura', text: 'O dispositivo está emparelhado, mas ainda não enviou leituras. Os dados aparecem aqui assim que chegarem.' },
  fresh: { title: 'Dados atualizados ao entrar', text: 'As leituras chegam do dispositivo OBD e são atualizadas sempre que entra na aplicação. Sem leituras recentes, mantém-se o último valor recebido, com a respetiva hora.' },
  stale: { title: 'Sem leituras recentes', text: 'Os valores mostrados são os últimos recebidos do dispositivo.' },
}

/** Etiqueta de estado de um valor: lida da leitura (verde), antiga (laranja) ou inexistente (cinzento) */
function Badge({ tone, children }) {
  return <span className={`mon-badge mon-badge--${tone}`}>{children}</span>
}

function Parameter({ label, icon, value, badge, lines }) {
  return (
    <div className="mon-param">
      <div className="mon-param__head">
        <span>{label}</span>
        <Icon name={icon} size={21} />
      </div>
      <strong className={value === null ? 'is-empty' : undefined}>{value ?? '—'}</strong>
      {badge}
      <p>{lines.map((l) => <span key={l}>{l}</span>)}</p>
    </div>
  )
}

function Skeleton() {
  return (
    <>
      <div className="mon-status mon-status--loading" aria-hidden="true"><span className="mon-skel" style={{ width: '40%', height: 20 }} /></div>
      <div className="mon-values" aria-hidden="true">
        {[0, 1].map((i) => (
          <div key={i} className="mon-param">
            <span className="mon-skel" style={{ width: '45%' }} />
            <span className="mon-skel" style={{ width: '30%', height: 34 }} />
            <span className="mon-skel" style={{ width: 90, height: 24 }} />
            <span className="mon-skel" style={{ width: '45%' }} />
          </div>
        ))}
      </div>
      <div className="mon-bottom" aria-hidden="true">
        <div className="mon-card"><span className="mon-skel" style={{ width: '40%', height: 22 }} /><span className="mon-skel" style={{ width: '60%' }} /></div>
        <div className="mon-info"><span className="mon-skel" style={{ width: '50%' }} /><span className="mon-skel" style={{ width: '80%' }} /></div>
      </div>
    </>
  )
}

function Readings({ vehicle, obd, device }) {
  const state = connectionOf(obd, device)
  const banner = BANNER[state]
  const info = INFO[state]
  const fromObd = state === 'fresh' || state === 'stale'
  const tone = state === 'fresh' ? 'ok' : 'warn'
  const when = obd?.readAt ? formatDateTime(obd.readAt) : null

  const hasKm = obd?.km != null
  const hasOil = obd?.oilTemp != null
  const hasGps = obd?.gps != null

  return (
    <>
      <div className="mon-status">
        <span className="mon-status__icon"><Icon name="radio" size={25} /></span>
        <div>
          <h2>{banner.title}</h2>
          <p>{banner.text(obd)}</p>
        </div>
        <Link to={banner.to} className="acct-btn">{banner.action}</Link>
      </div>

      <div className="mon-values">
        <Parameter
          label="Quilometragem"
          icon="gauge"
          value={formatKm(hasKm ? obd.km : vehicle.km)}
          badge={hasKm
            ? <Badge tone={tone}>{state === 'fresh' ? 'Atualizado' : 'Último valor recebido'}</Badge>
            : <Badge tone="muted">Valor inicial</Badge>}
          lines={hasKm
            ? ['Origem: dispositivo OBD', when]
            : ['Origem: valor indicado ao adicionar o veículo', 'Última atualização: sem leituras OBD']}
        />
        <Parameter
          label="Temperatura do óleo"
          icon="droplet"
          value={hasOil ? `${obd.oilTemp.toLocaleString('pt-PT')} °C` : null}
          badge={hasOil
            ? <Badge tone={tone}>{state === 'fresh' ? 'Atualizado' : 'Último valor recebido'}</Badge>
            : <Badge tone="muted">Sem leitura</Badge>}
          lines={hasOil && fromObd
            ? ['Origem: dispositivo OBD', when]
            : ['Origem: dados automáticos', 'Ainda não foram recebidas leituras.']}
        />
      </div>

      <div className="mon-bottom">
        <section className="mon-card">
          <h2>Localização GPS</h2>
          <div className="mon-gps">
            <Icon name={hasGps ? 'mapPin' : 'mapPinOff'} size={28} />
            <div>
              <strong>{hasGps ? formatCoordinates(obd.gps) : 'Indisponível'}</strong>
              <p>
                {hasGps
                  ? `Última posição recebida: ${when}`
                  : `Sem posição GPS recebida para a ${vehicle.brand} ${vehicle.model}.${state === 'unpaired' ? ' Nenhum dispositivo emparelhado.' : ''}`}
              </p>
            </div>
          </div>
          {hasGps && <Link to="/localizacao" className="link-arrow mon-card__link">Ver no mapa <Icon name="arrowRight" size={16} /></Link>}
        </section>
        <aside className="mon-info" role="note">
          <Icon name="info" size={19} />
          <div>
            <strong>{info.title}</strong>
            <p>{info.text}</p>
          </div>
        </aside>
      </div>
    </>
  )
}

export default function Monitoring() {
  const { vehicles, loading: garageLoading, error, selected, selectVehicle } = useOutletContext()
  const { loading, obdState, obdDevice } = useVehicleDetail(selected?.id)

  if (garageLoading) return <p className="muted">A carregar…</p>

  return (
    <div className="acct mon">
      {loading && selected && (
        <div className="mon-loading" role="status">
          <strong>A carregar os dados…</strong>
          <span>A obter a última leitura da {vehicleLabel(selected)}.</span>
        </div>
      )}

      <div className="acct__head">
        <h1>Monitorização do veículo</h1>
        <p>O estado da ligação e os dados mais recentes, com a sua origem e hora.</p>
      </div>

      {error && <div className="form-error" role="alert">{error}</div>}

      {!selected ? (
        <div className="acct-card">
          <h2>Ainda não tem veículos</h2>
          <p className="muted">Adicione um veículo para acompanhar a quilometragem e a ligação ao dispositivo OBD.</p>
          <Link to="/veiculos/novo" className="acct-btn acct-btn--primary acct-btn--self">Adicionar veículo</Link>
        </div>
      ) : (
        <>
          <VehicleSelect vehicles={vehicles} value={selected.id} onChange={selectVehicle} />

          {loading ? <Skeleton /> : <Readings key={selected.id} vehicle={selected} obd={obdState} device={obdDevice} />}
        </>
      )}
    </div>
  )
}
