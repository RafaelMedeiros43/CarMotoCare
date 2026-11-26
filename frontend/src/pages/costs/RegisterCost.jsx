import { useState } from 'react'
import { Link, useNavigate, useOutletContext } from 'react-router-dom'
import Field from '../../components/ui/Field.jsx'
import Icon from '../../components/ui/Icons.jsx'
import SelectField from '../../components/ui/SelectField.jsx'
import useApi from '../../hooks/useApi.js'
import useRemote from '../../hooks/useRemote.js'
import { oneYearAfter, sum } from '../../lib/costs.js'
import { formatEuro } from '../../lib/format.js'
import { parseEuro, todayIso, typeTitle } from '../../lib/maintenance.js'
import { vehicleLabel } from '../../lib/obd.js'
import { dateToIso, maskDate } from '../../lib/vehicleForm.js'
import '../../styles/maintenance.css'
import '../../styles/costs.css'

// Figma: "23 Registar custo", "23b Registar custo — Yamaha MT-07" e "…Erro de validação"
// Dados: POST /vehicles/{id}/expenses (pagar IUC ou seguro) e GET /vehicles/{id}/maintenances (cartão "Custos já incluídos")
// O contrato pede também `validUntil` (nova validade) e o Figma não tem esse campo: enviamos 1 ano depois da data do pagamento

const CATEGORIES = [['IUC', 'IUC'], ['SEGURO', 'Seguro']]
const todayField = () => { const [y, m, d] = todayIso().split('-'); return `${d} / ${m} / ${y}` }

export default function RegisterCost() {
  const { vehicles, loading: garageLoading, selected, selectVehicle, reload } = useOutletContext()
  const api = useApi()
  const navigate = useNavigate()
  const [form, setForm] = useState({ category: '', description: '', date: todayField(), amount: '' })
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)
  const { data: maintenances, error: listError } = useRemote(() => api.listMaintenances(selected.id), selected?.id ?? null)

  if (garageLoading) return <p className="muted">A carregar…</p>
  if (!selected) {
    return (
      <div className="acct mnt">
        <div className="acct__head"><h1>Registar custo</h1><p>Ainda não tem veículos na garagem</p></div>
        <Link to="/veiculos/novo" className="acct-btn acct-btn--primary acct-btn--self">Adicionar veículo</Link>
      </div>
    )
  }

  const name = `${selected.brand} ${selected.model}`
  const setField = (field, value) => {
    setForm((f) => ({ ...f, [field]: value }))
    setErrors((e) => (e[field] ? { ...e, [field]: undefined } : e))
  }

  const onSubmit = async (e) => {
    e.preventDefault()
    setFormError('')
    const found = {}
    if (!form.category) found.category = 'Selecione uma categoria'
    const date = dateToIso(form.date)
    if (!date) found.date = 'Use o formato DD / MM / AAAA com uma data real'
    else if (date > todayIso()) found.date = 'A data não pode ser no futuro'
    const amount = parseEuro(form.amount)
    if (!(amount > 0)) found.amount = 'Introduza um valor superior a 0,00 €'
    setErrors(found)
    if (Object.keys(found).length) { setFormError('Corrija os campos assinalados e tente novamente'); return }

    setSaving(true)
    try {
      await api.payExpense(selected.id, {
        category: form.category, date, amount,
        ...(form.description.trim() && { description: form.description.trim() }),
        validUntil: oneYearAfter(date),
      })
      await reload() // fecha o alerta do prazo: o veículo traz os alertas abertos
      navigate('/custos', { state: { saved: { category: form.category, amount, vehicleType: selected.type, vehicleName: name } } })
    } catch (err) {
      const mapped = {}
      for (const { field, message } of err.fieldErrors ?? []) if (['category', 'date', 'amount', 'description'].includes(field)) mapped[field] = message
      setErrors(mapped)
      setFormError(Object.keys(mapped).length
        ? 'Corrija os campos assinalados e tente novamente'
        : err.status === 404 || err.status === 405 ? 'O registo de IUC e seguro ainda não está disponível no servidor' : err.message)
      setSaving(false)
    }
  }

  return (
    <div className="acct mnt">
      <div className="acct__head">
        <h1>Registar custo</h1>
        <p>Registe um custo associado ao IUC ou ao seguro</p>
      </div>

      {formError && (
        <div className="mnt-alert" role="alert">
          <strong>Não foi possível guardar o custo</strong>
          <span>{formError}</span>
        </div>
      )}

      <form className="cst-form" onSubmit={onSubmit} noValidate>
        <section className="acct-card mnt-form__main">
          <h2>Registo de custo</h2>
          <SelectField id="vehicle-select" label="Veículo" value={selected.id} onChange={selectVehicle} disabled={saving} options={vehicles.map((v) => [v.id, vehicleLabel(v)])} />
          <SelectField id="category" label="Categoria" value={form.category} onChange={(v) => setField('category', v)} disabled={saving}
            placeholder="Selecione uma categoria" options={CATEGORIES} error={errors.category} describedBy="category-hint" />
          <p id="category-hint" className="addv-hint mnt-form__hint">Opções: IUC · Seguro</p>

          <Field name="description" label="Descrição/referência" error={errors.description}>
            {(d) => <input id="description" name="description" value={form.description} maxLength={255} disabled={saving} placeholder="Ex: IUC 2026, seguro anual" autoComplete="off"
              onChange={(e) => setField('description', e.target.value)} aria-describedby={d} />}
          </Field>

          <div className="mnt-form__two">
            <Field name="date" label="Data" error={errors.date}>
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

          <div className="cst-warn" role="note">
            <Icon name="clock" size={20} />
            <div>
              <strong>Evite contar o mesmo custo duas vezes</strong>
              <p>Um custo incluído ao registar uma manutenção já faz parte do acumulado. Não volte a introduzir esse valor aqui</p>
            </div>
          </div>

          <div className="mnt-form__actions">
            <Link to="/custos" className="acct-btn" aria-disabled={saving}>Cancelar</Link>
            <button type="submit" className="acct-btn acct-btn--primary" disabled={saving}>{saving ? 'A guardar…' : 'Guardar custo'}</button>
          </div>
        </section>

        <aside className="acct-card cst-included">
          <h2>Custos já incluídos</h2>
          <p className="mnt-card__sub">{name} · Todos os registos</p>
          {listError ? <p className="muted">Não foi possível carregar os custos já incluídos</p>
            : !maintenances ? <p className="muted">A carregar…</p>
            : maintenances.length === 0 ? <p className="muted">Ainda não há custos de manutenção registados</p>
            : (
              <ul>
                {maintenances.map((m) => (
                  <li key={m.id}>
                    <span>{(m.type === 'OUTRO' && m.notes) || typeTitle(m.type)}</span><b>{formatEuro(m.cost)}</b>
                    <small className="mon-badge mon-badge--ok">Já incluído na manutenção</small>
                  </li>
                ))}
              </ul>
            )}
          <p className="cst-included__total"><span>Total já incluído</span><b>{formatEuro(sum(maintenances ?? [], 'cost'))}</b></p>
          <p className="cst-included__note">IUC e seguro podem ser registados independentemente da existência de manutenções sem custo</p>
        </aside>
      </form>
    </div>
  )
}
