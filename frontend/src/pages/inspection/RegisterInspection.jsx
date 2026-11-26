import { useState } from 'react'
import { Link, useNavigate, useOutletContext } from 'react-router-dom'
import Field from '../../components/ui/Field.jsx'
import Icon from '../../components/ui/Icons.jsx'
import SelectField from '../../components/ui/SelectField.jsx'
import useApi from '../../hooks/useApi.js'
import useRemote from '../../hooks/useRemote.js'
import { formatDate, formatEuro } from '../../lib/format.js'
import { newestFirst, RESULT_OPTIONS, resultLabel, SEVERITY_OPTIONS } from '../../lib/inspection.js'
import { parseEuro, todayIso } from '../../lib/maintenance.js'
import { sum } from '../../lib/costs.js'
import { vehicleLabel } from '../../lib/obd.js'
import { dateToIso, maskDate } from '../../lib/vehicleForm.js'
import '../../styles/maintenance.css'
import '../../styles/costs.css'
import './inspection.css'

// Figma: "27c Registar inspeção"
// Dados: POST /vehicles/{id}/inspections ({ date, result, amount, deficiencies[] }); a km vem do veículo
// GET /vehicles/{id}/inspections alimenta o cartão "Inspeções registadas". O valor conta para o total de custos (categoria INSPECAO)

const todayField = () => { const [y, m, d] = todayIso().split('-'); return `${d} / ${m} / ${y}` }
const emptyDeficiency = () => ({ key: Math.random(), description: '', severity: 'LIGEIRA' })

export default function RegisterInspection() {
  const { vehicles, loading: garageLoading, selected, selectVehicle, reload } = useOutletContext()
  const api = useApi()
  const navigate = useNavigate()
  const [form, setForm] = useState({ date: todayField(), amount: '', result: 'APROVADA' })
  const [deficiencies, setDeficiencies] = useState([])
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)
  const { data: registered } = useRemote(() => api.listInspections(selected.id), selected?.id ?? null)

  if (garageLoading) return <p className="muted">A carregar…</p>
  if (!selected) {
    return (
      <div className="acct mnt">
        <div className="acct__head"><h1>Registar inspeção</h1><p>Ainda não tem veículos na garagem</p></div>
        <Link to="/veiculos/novo" className="acct-btn acct-btn--primary acct-btn--self">Adicionar veículo</Link>
      </div>
    )
  }

  const setField = (field, value) => {
    setForm((f) => ({ ...f, [field]: value }))
    setErrors((e) => (e[field] ? { ...e, [field]: undefined } : e))
  }
  const setDeficiency = (key, patch) => setDeficiencies((list) => list.map((d) => (d.key === key ? { ...d, ...patch } : d)))
  const amount = parseEuro(form.amount)
  const ready = dateToIso(form.date) && form.amount.trim() !== '' && amount >= 0
  const list = newestFirst(registered ?? [])

  const onSubmit = async (e) => {
    e.preventDefault()
    setFormError('')
    const found = {}
    const date = dateToIso(form.date)
    if (!date) found.date = 'Use o formato DD / MM / AAAA com uma data real'
    else if (date > todayIso()) found.date = 'A data não pode ser no futuro'
    if (form.amount.trim() === '' || !(amount >= 0)) found.amount = 'Introduza o valor pago na inspeção'
    if (deficiencies.some((d) => !d.description.trim())) found.deficiencies = 'Descreva cada deficiência ou remova a linha'
    setErrors(found)
    if (Object.keys(found).length) { setFormError('Corrija os campos assinalados e tente novamente'); return }

    setSaving(true)
    try {
      const created = await api.createInspection(selected.id, {
        date, result: form.result, amount,
        deficiencies: deficiencies.map((d) => ({ description: d.description.trim(), severity: d.severity })),
      })
      await reload() // a inspeção aprovada fecha o alerta e cria o prazo seguinte
      navigate(`/inspecao/${selected.id}/${created.id}`)
    } catch (err) {
      const mapped = {}
      for (const { field, message } of err.fieldErrors ?? []) if (['date', 'amount', 'result'].includes(field)) mapped[field] = message
      setErrors(mapped)
      setFormError(Object.keys(mapped).length
        ? 'Corrija os campos assinalados e tente novamente'
        : err.status === 404 || err.status === 405 ? 'O registo de inspeções ainda não está disponível no servidor' : err.message)
      setSaving(false)
    }
  }

  return (
    <div className="acct mnt">
      <div className="acct__head">
        <h1>Registar inspeção</h1>
        <p>Registe a inspeção periódica de um veículo, o resultado e o valor pago</p>
      </div>

      {formError && (
        <div className="mnt-alert" role="alert">
          <strong>Não foi possível guardar a inspeção</strong>
          <span>{formError}</span>
        </div>
      )}

      <form className="cst-form" onSubmit={onSubmit} noValidate>
        <section className="acct-card mnt-form__main">
          <h2>Registo de inspeção</h2>
          <SelectField id="vehicle-select" label="Veículo" value={selected.id} onChange={selectVehicle} disabled={saving} options={vehicles.map((v) => [v.id, vehicleLabel(v)])} />

          <div className="mnt-form__two">
            <Field name="date" label="Data da inspeção" error={errors.date}>
              {(d) => <input id="date" name="date" value={form.date} inputMode="numeric" placeholder="DD / MM / AAAA" disabled={saving}
                onChange={(e) => setField('date', maskDate(e.target.value))} aria-invalid={errors.date ? 'true' : undefined} aria-describedby={d} />}
            </Field>
            <Field name="amount" label="Valor" error={errors.amount}>
              {(d) => (
                <span className="mnt-input">
                  <input id="amount" name="amount" value={form.amount} inputMode="decimal" placeholder="0,00" disabled={saving}
                    onChange={(e) => setField('amount', e.target.value)} aria-invalid={errors.amount ? 'true' : undefined} aria-describedby={d} />
                  <i>€</i>
                </span>
              )}
            </Field>
          </div>

          <SelectField id="result" label="Resultado da inspeção" value={form.result} onChange={(v) => setField('result', v)} disabled={saving} options={RESULT_OPTIONS} error={errors.result} />

          <fieldset className="ins-defs">
            <legend>Deficiências detetadas (opcional)</legend>
            {deficiencies.map((d) => (
              <div className="ins-defs__row" key={d.key}>
                <label>
                  <span>Descrição</span>
                  <input value={d.description} maxLength={255} disabled={saving} placeholder="Ex: Desgaste irregular dos pneus traseiros" autoComplete="off"
                    onChange={(e) => { setDeficiency(d.key, { description: e.target.value }); setErrors((x) => ({ ...x, deficiencies: undefined })) }} />
                </label>
                <label>
                  <span>Gravidade</span>
                  <select value={d.severity} disabled={saving} onChange={(e) => setDeficiency(d.key, { severity: e.target.value })}>
                    {SEVERITY_OPTIONS.map(([v, text]) => <option key={v} value={v}>{text}</option>)}
                  </select>
                </label>
                <button type="button" className="acct-btn ins-defs__remove" aria-label="Remover deficiência" disabled={saving}
                  onClick={() => setDeficiencies((list) => list.filter((x) => x.key !== d.key))}><Icon name="x" size={16} /></button>
              </div>
            ))}
            {errors.deficiencies && <p className="addv-error" role="alert">{errors.deficiencies}</p>}
            <button type="button" className="ins-add" disabled={saving} onClick={() => setDeficiencies((list) => [...list, emptyDeficiency()])}>+ Adicionar deficiência</button>
            <p className="addv-hint">Indique cada deficiência apontada na inspeção e a sua gravidade (ligeira ou grave)</p>
          </fieldset>

          <div className="mnt-form__actions">
            <Link to="/inspecao" className="acct-btn" aria-disabled={saving}>Cancelar</Link>
            <button type="submit" className="acct-btn acct-btn--primary" disabled={saving || !ready}>{saving ? 'A guardar…' : 'Guardar inspeção'}</button>
          </div>
        </section>

        <aside className="acct-card cst-included">
          <h2>Inspeções registadas</h2>
          <p className="mnt-card__sub">{selected.brand} {selected.model} · Todos os registos</p>
          {list.length === 0 ? <p className="ins-none">Ainda sem inspeções registadas</p> : (
            <ul className="ins-reg">
              {list.map((i) => (
                <li key={i.id}>
                  <span>{formatDate(i.date)}</span><b>{formatEuro(i.amount)}</b>
                  <em className={`mon-badge ${i.result === 'APROVADA' ? 'mon-badge--ok' : 'rem-tag--late'}`}>{resultLabel(i.result)}</em>
                </li>
              ))}
            </ul>
          )}
          <div className="ins-reg__total"><span>Total registado</span><b>{formatEuro(sum(list, 'amount'))}</b></div>
          <p className="ins-note">O valor da inspeção conta para o total de custos do veículo</p>
        </aside>
      </form>
    </div>
  )
}
