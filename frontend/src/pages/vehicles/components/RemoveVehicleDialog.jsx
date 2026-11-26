import { useEffect, useRef, useState } from 'react'
import Icon from '../../../components/ui/Icons.jsx'
import { formatKm, formatPlateDots, formatYearMonth } from '../../../lib/format.js'
import '../vehicle-detail.css'

// Figma: "12 Remover veículo" / "12b". Confirmação antes de DELETE /vehicles/{id}
// `onConfirm` faz o pedido se lançar erro, a mensagem aparece aqui
export default function RemoveVehicleDialog({ vehicle, onClose, onConfirm }) {
  const ref = useRef(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    const dialog = ref.current
    if (!dialog) return
    if (vehicle && !dialog.open) dialog.showModal()
    if (!vehicle && dialog.open) dialog.close()
  }, [vehicle])

  const confirm = async () => {
    setBusy(true)
    setError('')
    try {
      await onConfirm(vehicle)
    } catch (e) {
      setError(e.message)
      setBusy(false)
    }
  }

  const isMoto = vehicle?.type === 'MOTO'
  return (
    <dialog
      ref={ref}
      className="rm-dialog"
      aria-labelledby="rm-title"
      onClose={() => { setError(''); setBusy(false); onClose() }}
      onCancel={(e) => busy && e.preventDefault()}
    >
      {vehicle && (
        <div className="rm-dialog__body">
          <div className="rm-dialog__head">
            <h2 id="rm-title">Remover este veículo?</h2>
            <button type="button" className="rm-dialog__close" onClick={onClose} disabled={busy} aria-label="Fechar">
              <Icon name="x" size={20} />
            </button>
          </div>
          <p className="rm-dialog__text">
            Confirme que pretende remover este veículo da sua garagem pessoal. Isto apaga também as
            manutenções, inspeções, prazos e alertas deste veículo e não pode ser desfeito
          </p>

          <div className="rm-dialog__vehicle">
            <span className="rm-dialog__icon"><Icon name={isMoto ? 'bike' : 'car'} size={32} /></span>
            <div>
              <strong>{vehicle.brand} {vehicle.model}</strong>
              <span>{isMoto ? 'Motociclo' : 'Automóvel'} · {formatYearMonth(vehicle.firstRegistration)} · {formatPlateDots(vehicle.plate)}</span>
              <span>{formatKm(vehicle.km)}</span>
            </div>
          </div>

          {error && <div className="form-error" role="alert">{error}</div>}

          <hr className="acct-sep" />
          <div className="acct-actions">
            <button type="button" className="acct-btn" onClick={onClose} disabled={busy}>Cancelar</button>
            <button type="button" className="acct-btn rm-dialog__danger" onClick={confirm} disabled={busy}>
              <Icon name="trash" size={17} />
              {busy ? 'A remover…' : 'Remover veículo'}
            </button>
          </div>
        </div>
      )}
    </dialog>
  )
}
