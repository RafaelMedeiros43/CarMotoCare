import { Link, useOutletContext, useParams } from 'react-router-dom'
import useApi from '../../hooks/useApi.js'
import useRemote from '../../hooks/useRemote.js'
import { daysUntil, formatDate, formatEuro, formatPlateDots } from '../../lib/format.js'
import { resultLabel, severityLabel, validityText } from '../../lib/inspection.js'
import '../../styles/maintenance.css'
import './inspection.css'

// Figma: "28 Detalhes da inspeção" ( aprovada e reprovada)
// Dados: GET /vehicles/{id}/inspections/{inspectionId} (resultado, validade, valor pago e deficiências)

export default function InspectionDetail() {
  const { vehicleId, inspectionId } = useParams()
  const { vehicles, loading: garageLoading } = useOutletContext()
  const api = useApi()
  const { loading, data: inspection, error, reload } = useRemote(() => api.getInspection(vehicleId, inspectionId), `${vehicleId}/${inspectionId}`)

  if (garageLoading) return <p className="muted">A carregar…</p>
  const vehicle = vehicles.find((v) => v.id === vehicleId)
  const vehicleText = vehicle ? `${vehicle.brand} ${vehicle.model} · ${formatPlateDots(vehicle.plate)}` : ''

  const back = <Link to="/inspecao" className="acct-btn acct-btn--primary">Voltar à inspeção</Link>

  if (error) {
    return (
      <div className="acct mnt">
        <div className="mnt-head"><div className="acct__head"><h1>Detalhes da inspeção</h1></div>{back}</div>
        <div className="form-error" role="alert">
          {error.status === 404 ? 'Não encontrámos esta inspeção' : `Não foi possível carregar a inspeção ${error.message}`}{' '}
          {error.status !== 404 && <button type="button" className="link-arrow" onClick={reload}>Tentar de novo</button>}
        </div>
      </div>
    )
  }
  if (loading || !inspection) {
    return (
      <div className="acct mnt">
        <div className="mnt-head"><div className="acct__head"><h1>Detalhes da inspeção</h1><p>A carregar…</p></div>{back}</div>
        <div className="mon-skel ins-block-skel" role="status" />
      </div>
    )
  }

  const i = inspection
  const approved = i.result === 'APROVADA'
  const days = i.validUntil ? daysUntil(i.validUntil) : null
  const expired = approved && days != null && days < 0
  const subtitle = `${vehicleText} · inspeção de ${formatDate(i.date)}`
  const graves = i.deficiencies.filter((d) => d.severity === 'GRAVE').length

  return (
    <div className="acct mnt">
      <div className="mnt-head">
        <div className="acct__head">
          <h1>Detalhes da inspeção</h1>
          <p>Inspeção periódica de {formatDate(i.date)} · {vehicleText}</p>
        </div>
        {back}
      </div>

      <section className="cst-card ins-valid">
        <span>{approved ? 'Válida até' : 'Validade'}</span>
        {approved && i.validUntil ? (
          <>
            <strong className={expired ? 'is-late' : ''}>{formatDate(i.validUntil)}</strong>
            <small className={expired ? 'is-late' : ''}>{validityText(days)}</small>
          </>
        ) : (
          <>
            <strong className="is-late">Sem validade</strong>
            <small className="is-late">Necessária nova inspeção</small>
          </>
        )}
      </section>

      <section className="acct-card mnt-card">
        <h2>Resultado e pagamento da inspeção</h2>
        <p className="mnt-card__sub">{subtitle}</p>
        <div className="mnt-table ins-pay" role="table" aria-label="Resultado e pagamento">
          <div className="mnt-table__head" role="row"><span>Descrição</span><span>Data</span><span>Estado</span><span className="is-end">Valor</span></div>
          <div className="mnt-table__row" role="row">
            <div className="mnt-table__what"><strong>Taxa de inspeção periódica</strong><small>Taxa de inspeção · Centro de inspeções</small></div>
            <span className="mnt-table__muted">{formatDate(i.date)}</span>
            <span className={approved ? 'ins-ok' : 'ins-bad'}>{resultLabel(i.result)}</span>
            <b className="is-end">{formatEuro(i.amount)}</b>
          </div>
        </div>
        <div className="mnt-card__foot"><span>Total pago</span><b className="ins-total">{formatEuro(i.amount)}</b></div>
      </section>

      <section className="acct-card mnt-card">
        <h2>Deficiências detetadas</h2>
        <p className="mnt-card__sub">{subtitle}</p>
        <div className="mnt-table ins-def" role="table" aria-label="Deficiências detetadas">
          <div className="mnt-table__head" role="row"><span>Descrição</span><span>Gravidade</span></div>
          {i.deficiencies.map((d, n) => (
            <div className="mnt-table__row" role="row" key={n}>
              <div className="mnt-table__what"><strong>{d.description}</strong></div>
              <span className={d.severity === 'GRAVE' ? 'ins-bad' : 'mnt-table__muted'}>{severityLabel(d.severity)}</span>
            </div>
          ))}
          {graves === 0 && (
            <div className="mnt-table__row" role="row">
              <div className="mnt-table__what"><strong>Deficiências graves ou perigosas</strong><small>Nenhuma detetada</small></div>
              <span className="mnt-table__muted">—</span>
            </div>
          )}
        </div>
        <div className="mnt-card__foot"><span>Total de deficiências detetadas</span><b className="ins-total">{i.deficiencies.length}</b></div>
      </section>
    </div>
  )
}
