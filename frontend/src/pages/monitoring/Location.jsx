import { Link, useOutletContext } from 'react-router-dom'
import Icon from '../../components/ui/Icons.jsx'
import VehicleSelect from '../../components/ui/VehicleSelect.jsx'
import useVehicleDetail from '../../hooks/useVehicleDetail.js'
import { formatCoordinates, formatDateTime, formatPlateDots } from '../../lib/format.js'
import { connectionOf } from '../../lib/obd.js'
import './obd.css'

// Figma: "17 Localização"
// Dados: GET /vehicles/{id}/obd-state (gps: { latitude, longitude } ou nulo). O mapa é o do OpenStreetMap (incorporado,
// sem chave de API); o nome do local não vem da API, por isso mostram-se as coordenadas.

const mapUrl = ({ latitude, longitude }) => {
  const d = 0.006
  const bbox = [longitude - d, latitude - d, longitude + d, latitude + d].join(',')
  return `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${latitude},${longitude}`
}

export default function Location() {
  const { vehicles, loading: garageLoading, selected, selectVehicle } = useOutletContext()
  const { loading, obdState, obdDevice } = useVehicleDetail(selected?.id)

  if (garageLoading) return <p className="muted">A carregar…</p>

  return (
    <div className="acct obdp">
      <div className="acct__head">
        <h1>Localização do veículo</h1>
        <p>Última posição enviada pelo dispositivo OBD do veículo.</p>
      </div>

      {!selected ? (
        <section className="acct-card obd-empty">
          <h2>Ainda não tem veículos</h2>
          <p>Adicione um veículo e emparelhe um dispositivo OBD para ver a localização.</p>
          <Link to="/veiculos/novo" className="acct-btn acct-btn--primary">Adicionar veículo</Link>
        </section>
      ) : (
        <>
          <div className="loc-top">
            <VehicleSelect vehicles={vehicles} value={selected.id} onChange={selectVehicle} />
            {!loading && (
              <span className={`mon-badge ${obdState?.gps ? 'mon-badge--ok' : 'mon-badge--muted'}`}>
                {obdState?.gps ? 'GPS disponibilizado pelo dispositivo' : 'GPS indisponível'}
              </span>
            )}
          </div>

          {loading ? <p className="muted">A obter a última posição…</p> : (
            <Position vehicle={selected} obd={obdState} state={connectionOf(obdState, obdDevice)} />
          )}
        </>
      )}
    </div>
  )
}

function Position({ vehicle, obd, state }) {
  const gps = obd?.gps
  const name = `${vehicle.brand} ${vehicle.model}`
  const stale = state === 'stale'

  return (
    <>
      <div className="loc-grid">
        <div className="loc-map">
          {gps ? (
            <>
              <iframe title={`Mapa com a última posição de ${name}`} src={mapUrl(gps)} loading="lazy" referrerPolicy="no-referrer" />
              <span className="loc-map__chip"><Icon name="clock" size={16} /> Última posição recebida · {new Date(obd.readAt).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' })}</span>
              <span className="loc-map__chip loc-map__chip--vehicle">{name} · {formatPlateDots(vehicle.plate)}</span>
            </>
          ) : (
            <div className="loc-map__empty">
              <Icon name="mapPinOff" size={36} />
              <strong>Sem posição disponível</strong>
              <span>{state === 'unpaired' ? 'Emparelhe um dispositivo OBD para receber a localização.' : 'O dispositivo ainda não enviou uma posição GPS.'}</span>
              {state === 'unpaired' && <Link to="/dispositivo/emparelhar" className="acct-btn acct-btn--primary">Emparelhar dispositivo OBD</Link>}
            </div>
          )}
        </div>

        <section className="acct-card loc-data">
          <h2>Dados da posição</h2>
          <div><small>Veículo selecionado</small><strong>{name}</strong><span>{formatPlateDots(vehicle.plate)}</span></div>
          <div>
            <small>Posição</small>
            <strong className="loc-data__coords">{gps ? formatCoordinates(gps) : 'Indisponível'}</strong>
          </div>
          {gps && (
            <div>
              <small>Último dado de localização</small>
              <strong>{formatDateTime(obd.readAt)}</strong>
              <span className={`mon-badge ${stale ? 'mon-badge--warn' : 'mon-badge--ok'}`}>{stale ? 'Última posição' : 'Atualizada'}</span>
            </div>
          )}
          <p>Origem: GPS do dispositivo OBD. A posição é atualizada sempre que entra na aplicação.</p>
        </section>
      </div>

      <aside className="loc-warn" role="note">
        <Icon name="clock" size={20} />
        <div>
          <strong>A localização depende dos dados recebidos</strong>
          <p>O mapa mostra a última posição enviada pelo dispositivo. Sem leituras recentes, mantém-se a última posição conhecida.</p>
        </div>
      </aside>
    </>
  )
}
