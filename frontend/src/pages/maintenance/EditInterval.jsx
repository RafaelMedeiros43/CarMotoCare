import { useState } from 'react'
import { Link, useNavigate, useOutletContext, useParams } from 'react-router-dom'
import Icon from '../../components/ui/Icons.jsx'
import useApi from '../../hooks/useApi.js'
import useRemote from '../../hooks/useRemote.js'
import { formatDate, formatKm, formatPlateDots, formatYearMonth } from '../../lib/format.js'
import { addMonths, monthsText, todayIso } from '../../lib/maintenance.js'
import { dateToIso, maskDate, maskKm } from '../../lib/vehicleForm.js'
import '../../styles/maintenance.css'

// Figma: "20 Definir intervalo de manutenção" (rota /manutencao/pecas/:partId)
// Dados: GET /vehicles/{id}/parts (para encontrar a peça) e PATCH /vehicles/{id}/parts/{partId}
// { intervalKm?, intervalMonths?, lastServiceDate?, lastServiceKm? }; null desativa um limite (pelo menos um ativo)

const isoToField = (iso) => { const [y, m, d] = iso.split('-'); return `${d} / ${m} / ${y}` }

export default function EditInterval() {
  const { partId } = useParams()
  const { selected, loading: garageLoading } = useOutletContext()
  const api = useApi()
  const { loading, data, error } = useRemote(() => api.listParts(selected.id), selected?.id ?? null)

  if (garageLoading || (selected && loading)) return <p className="muted">A carregar…</p>

  const part = data?.find((p) => p.id === partId)
  if (!selected || error || !part) {
    return (
      <div className="acct mnt">
        <Link to="/manutencao/pecas" className="mnt-back"><Icon name="arrowLeft" size={15} /> Voltar a peças e intervalos</Link>
        <section className="acct-card mnt-empty">
          <h2>Peça não encontrada</h2>
          <p>{error ? error.message : 'Esta peça não existe no plano do veículo selecionado'}</p>
          <Link to="/manutencao/pecas" className="acct-btn acct-btn--primary">Ver peças e intervalos</Link>
        </section>
      </div>
    )
  }
  // key: se a peça mudar, o formulário recomeça com os valores dela
  return <IntervalForm key={part.id} vehicle={selected} part={part} />
}

function IntervalForm({ vehicle, part }) {
  const api = useApi()
  const navigate = useNavigate()
  const hasLast = part.lastServiceDate != null && part.lastServiceKm != null

  const [byTime, setByTime] = useState(part.intervalMonths != null)
  const [months, setMonths] = useState(part.intervalMonths != null ? String(part.intervalMonths) : '12')
  const [byKm, setByKm] = useState(part.intervalKm != null)
  const [kmInterval, setKmInterval] = useState(part.intervalKm != null ? String(part.intervalKm) : '10000')
  const [reference, setReference] = useState(hasLast ? 'LAST' : 'OTHER')
  const [refDate, setRefDate] = useState(part.lastServiceDate ? isoToField(part.lastServiceDate) : '')
  const [refKm, setRefKm] = useState(part.lastServiceKm != null ? String(part.lastServiceKm) : '')
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)

  const clear = (name) => setErrors((e) => (e[name] ? { ...e, [name]: undefined } : e))

  // Valores efetivos (a referência é a do último serviço, a menos que escolha outra)
  const useOther = reference === 'OTHER'
  const baseDate = useOther ? dateToIso(refDate) : part.lastServiceDate
  const baseKm = useOther ? (refKm === '' ? null : Number(refKm)) : part.lastServiceKm
  const monthsN = byTime && /^\d+$/.test(months) && Number(months) >= 1 ? Number(months) : null
  const kmN = byKm && /^\d+$/.test(kmInterval) && Number(kmInterval) >= 1 ? Number(kmInterval) : null
  const targetDate = monthsN && baseDate ? addMonths(baseDate, monthsN) : null
  const targetKm = kmN && baseKm != null ? baseKm + kmN : null

  const onSubmit = async (e) => {
    e.preventDefault()
    setFormError('')
    const found = {}
    if (!byTime && !byKm) found.limits = 'Ative pelo menos um limite: por tempo ou por quilometragem'
    if (byTime && monthsN === null) found.months = 'Indique os meses (1 ou mais)'
    if (byKm && kmN === null) found.km = 'Indique os quilómetros (1 ou mais)'
    if (useOther) {
      if (!refDate) found.refDate = 'Indique a data de referência'
      else if (!baseDate) found.refDate = 'Use o formato DD / MM / AAAA com uma data real'
      else if (baseDate > todayIso()) found.refDate = 'A data não pode ser no futuro'
      if (refKm === '') found.refKm = 'Indique a quilometragem de referência'
      else if (Number(refKm) > vehicle.km) found.refKm = `Não pode ser superior à quilometragem atual (${formatKm(vehicle.km)}).`
    }
    setErrors(found)
    if (Object.keys(found).length) return

    // Só envia o que mudou
    const body = {}
    const newKm = byKm ? kmN : null
    const newMonths = byTime ? monthsN : null
    if (newKm !== part.intervalKm) body.intervalKm = newKm
    if (newMonths !== part.intervalMonths) body.intervalMonths = newMonths
    if (useOther) {
      if (baseDate !== part.lastServiceDate) body.lastServiceDate = baseDate
      if (baseKm !== part.lastServiceKm) body.lastServiceKm = baseKm
    }
    if (Object.keys(body).length === 0) { navigate('/manutencao/pecas'); return }

    setSaving(true)
    try {
      await api.updatePart(vehicle.id, part.id, body)
      navigate('/manutencao/pecas')
    } catch (err) {
      const map = { intervalKm: 'km', intervalMonths: 'months', lastServiceDate: 'refDate', lastServiceKm: 'refKm' }
      const mapped = {}
      for (const { field, message } of err.fieldErrors ?? []) mapped[map[field] ?? 'limits'] = message
      setErrors(mapped)
      setFormError(Object.keys(mapped).length ? 'Corrija os campos assinalados.' : err.message)
      setSaving(false)
    }
  }

  return (
    <div className="acct mnt">
      <div>
        <Link to="/manutencao/pecas" className="mnt-back"><Icon name="arrowLeft" size={15} /> Voltar a peças e intervalos</Link>
        <div className="acct__head mnt-head--tight">
          <h1>Editar intervalo de manutenção</h1>
          <p>Escolha quando repetir uma manutenção, por tempo, quilometragem ou ambos</p>
        </div>
      </div>

      <form className="mnt-interval" onSubmit={onSubmit} noValidate>
        <div className="acct-card mnt-interval__main">
          <section className="mnt-step">
            <h2><span>1</span>Veículo e manutenção</h2>
            <div className="acct-field addv-field">
              <label htmlFor="vehicle-select">Veículo</label>
              <span className="mon-select__box">
                <select id="vehicle-select" value={vehicle.id} disabled aria-describedby="vehicle-km">
                  <option value={vehicle.id}>{vehicle.brand} {vehicle.model} · {formatYearMonth(vehicle.firstRegistration)} · {formatPlateDots(vehicle.plate)}</option>
                </select>
                <Icon name="chevronDown" size={18} />
              </span>
              <p id="vehicle-km" className="addv-hint">Quilometragem atual: {formatKm(vehicle.km)}</p>
            </div>
            <div className="acct-field addv-field">
              <label htmlFor="part-name">Manutenção a repetir</label>
              <span className="mnt-input mnt-input--locked">
                <input id="part-name" value={part.name} readOnly aria-describedby="part-hint" />
                <Icon name="lock" size={17} />
              </span>
              <p id="part-hint" className="addv-hint">Definida pelo plano do veículo. Só pode alterar o intervalo e a referência</p>
            </div>
          </section>

          <section className="mnt-step">
            <h2><span>2</span>Quando repetir</h2>
            <p className="mnt-step__lead">Ative um ou os dois limites e defina o seu intervalo</p>
            <div className="mnt-form__two">
              <div className="acct-field addv-field">
                <label className="mnt-check"><input type="checkbox" checked={byTime} onChange={(e) => { setByTime(e.target.checked); clear('limits') }} disabled={saving} /> Por tempo</label>
                <span className="mnt-input">
                  <input id="months" value={months} inputMode="numeric" disabled={!byTime || saving} aria-label="Meses entre manutenções"
                    aria-invalid={errors.months ? 'true' : undefined} onChange={(e) => { setMonths(e.target.value.replace(/\D/g, '').slice(0, 3)); clear('months') }} />
                  <i>meses</i>
                </span>
                {errors.months && <p className="addv-error" role="alert">{errors.months}</p>}
              </div>
              <div className="acct-field addv-field">
                <label className="mnt-check"><input type="checkbox" checked={byKm} onChange={(e) => { setByKm(e.target.checked); clear('limits') }} disabled={saving} /> Por quilometragem</label>
                <span className="mnt-input">
                  <input id="km-interval" value={kmInterval} inputMode="numeric" disabled={!byKm || saving} aria-label="Quilómetros entre manutenções"
                    aria-invalid={errors.km ? 'true' : undefined} onChange={(e) => { setKmInterval(maskKm(e.target.value)); clear('km') }} />
                  <i>km</i>
                </span>
                {errors.km && <p className="addv-error" role="alert">{errors.km}</p>}
              </div>
            </div>
            {errors.limits && <p className="addv-error" role="alert">{errors.limits}</p>}
            {byTime && byKm && (
              <p className="mnt-note"><Icon name="info" size={17} /> Com os dois limites definidos, aplica-se o que ocorrer primeiro</p>
            )}
          </section>

          <section className="mnt-step">
            <h2><span>3</span>A partir de que referência?</h2>
            <p className="mnt-step__lead">O próximo prazo é calculado a partir desta data e quilometragem</p>
            <div className="mnt-radios" role="radiogroup" aria-label="Referência do cálculo">
              <label className={!hasLast ? 'is-disabled' : undefined}>
                <input type="radio" name="reference" value="LAST" checked={reference === 'LAST'} disabled={!hasLast || saving} onChange={() => { setReference('LAST'); setErrors({}) }} /> Última intervenção
              </label>
              <label>
                <input type="radio" name="reference" value="OTHER" checked={useOther} disabled={saving} onChange={() => setReference('OTHER')} /> Definir outra referência
              </label>
            </div>
            <p className="mnt-note mnt-note--plain">
              <Icon name="history" size={17} />
              {hasLast ? `Do histórico: ${part.name} · ${formatDate(part.lastServiceDate)}` : 'Esta peça ainda não tem serviço registado: indique a referência'}
            </p>
            <div className="mnt-form__two">
              <div className="acct-field addv-field">
                <label htmlFor="ref-date">Data de referência</label>
                <span className="mnt-input">
                  <input id="ref-date" value={refDate} inputMode="numeric" placeholder="DD / MM / AAAA" disabled={!useOther || saving}
                    aria-invalid={errors.refDate ? 'true' : undefined} onChange={(e) => { setRefDate(maskDate(e.target.value)); clear('refDate') }} />
                  <Icon name="calendar" size={17} />
                </span>
                {errors.refDate && <p className="addv-error" role="alert">{errors.refDate}</p>}
              </div>
              <div className="acct-field addv-field">
                <label htmlFor="ref-km">Quilometragem de referência</label>
                <span className="mnt-input">
                  <input id="ref-km" value={refKm} inputMode="numeric" disabled={!useOther || saving}
                    aria-invalid={errors.refKm ? 'true' : undefined} onChange={(e) => { setRefKm(maskKm(e.target.value)); clear('refKm') }} />
                  <i>km</i>
                </span>
                {errors.refKm && <p className="addv-error" role="alert">{errors.refKm}</p>}
              </div>
            </div>
          </section>

          {formError && <div className="form-error" role="alert">{formError}</div>}
          <div className="mnt-interval__foot">
            <span>{part.intervalSource === 'UTILIZADOR' ? 'Intervalo definido por si' : 'Intervalo do catálogo'}</span>
            <div className="mnt-form__actions">
              <Link to="/manutencao/pecas" className="acct-btn" aria-disabled={saving}>Cancelar</Link>
              <button type="submit" className="acct-btn acct-btn--primary" disabled={saving}><Icon name="check" size={17} /> {saving ? 'A guardar…' : 'Guardar intervalo'}</button>
            </div>
          </div>
        </div>

        <aside className="mnt-interval__side">
          <section className="acct-card mnt-preview">
            <header>
              <small><Icon name="clock" size={14} /> PRÉ-VISUALIZAÇÃO DO INTERVALO</small>
              <h2>Próxima manutenção</h2>
              <strong>{part.name}</strong>
              <span>{vehicle.brand} {vehicle.model} · {formatPlateDots(vehicle.plate)}</span>
            </header>
            {monthsN && (
              <div className="mnt-preview__block">
                <small><Icon name="calendarDays" size={15} /> Por tempo · a cada {monthsText(monthsN)}</small>
                <b>{targetDate ? formatDate(targetDate) : '—'}</b>
                {targetDate && <span>{formatDate(baseDate)} + {monthsText(monthsN)}</span>}
              </div>
            )}
            {kmN && (
              <div className="mnt-preview__block">
                <small><Icon name="gauge" size={15} /> Por quilometragem · a cada {formatKm(kmN)}</small>
                <b>{targetKm != null ? formatKm(targetKm) : '—'}</b>
                {targetKm != null && (
                  <>
                    <span>{formatKm(baseKm)} + {formatKm(kmN)}</span>
                    <strong className="mnt-preview__left">{targetKm - vehicle.km > 0 ? `Faltam ${formatKm(targetKm - vehicle.km)}` : 'Já ultrapassado'}</strong>
                  </>
                )}
                <span>Quilometragem atual: {formatKm(vehicle.km)}</span>
              </div>
            )}
            {!monthsN && !kmN && <p className="mnt-preview__empty">Ative um limite para ver a próxima manutenção</p>}
            {monthsN && kmN && targetDate && targetKm != null && (
              <div className="mnt-preview__first">
                <strong>O que ocorrer primeiro</strong>
                <p>{formatDate(targetDate)} ou {formatKm(targetKm)}. A data em que atingirá a quilometragem depende da utilização do veículo</p>
              </div>
            )}
          </section>
          <div className="mnt-personal">
            <Icon name="info" size={18} />
            <div>
              <strong>Uma definição pessoal</strong>
              <p>Este intervalo é definido por si. Não constitui uma recomendação oficial do fabricante</p>
              <p>O próximo prazo desta peça é recalculado ao guardar</p>
            </div>
          </div>
        </aside>
      </form>
    </div>
  )
}
